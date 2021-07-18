<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use ArrayObject;
use GraphQL\Error\Error;
use GraphQL\Executor\Values;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Validator\ValidationContext;

use function array_map;
use function count;
use function implode;
use function method_exists;
use function sprintf;

class QueryComplexity extends QuerySecurityRule
{
    protected int $maxQueryComplexity;

    /** @var array<string, mixed> */
    protected array $rawVariableValues = [];

    /** @var NodeList<VariableDefinitionNode> */
    protected NodeList $variableDefs;

    protected ArrayObject $fieldNodeAndDefs;

    protected ValidationContext $context;

    protected int $complexity;

    public function __construct($maxQueryComplexity)
    {
        $this->setMaxQueryComplexity($maxQueryComplexity);
    }

    public function getVisitor(ValidationContext $context)
    {
        $this->context = $context;

        // @phpstan-ignore-next-line Initializing with an empty array does not set the generic type
        $this->variableDefs     = new NodeList([]);
        $this->fieldNodeAndDefs = new ArrayObject();
        $this->complexity       = 0;

        return $this->invokeIfNeeded(
            $context,
            [
                NodeKind::SELECTION_SET        => function (SelectionSetNode $selectionSet) use ($context): void {
                    $this->fieldNodeAndDefs = $this->collectFieldASTsAndDefs(
                        $context,
                        $context->getParentType(),
                        $selectionSet,
                        null,
                        $this->fieldNodeAndDefs
                    );
                },
                NodeKind::VARIABLE_DEFINITION  => function ($def): VisitorOperation {
                    $this->variableDefs[] = $def;

                    return Visitor::skipNode();
                },
                NodeKind::OPERATION_DEFINITION => [
                    'leave' => function (OperationDefinitionNode $operationDefinition) use ($context, &$complexity): void {
                        $errors = $context->getErrors();

                        if (count($errors) > 0) {
                            return;
                        }

                        $this->complexity = $this->fieldComplexity($operationDefinition, $complexity);

                        if ($this->getQueryComplexity() <= $this->getMaxQueryComplexity()) {
                            return;
                        }

                        $context->reportError(
                            new Error(static::maxQueryComplexityErrorMessage(
                                $this->getMaxQueryComplexity(),
                                $this->getQueryComplexity()
                            ))
                        );
                    },
                ],
            ]
        );
    }

    protected function fieldComplexity($node, $complexity = 0)
    {
        if (isset($node->selectionSet) && $node->selectionSet instanceof SelectionSetNode) {
            foreach ($node->selectionSet->selections as $childNode) {
                $complexity = $this->nodeComplexity($childNode, $complexity);
            }
        }

        return $complexity;
    }

    protected function nodeComplexity(Node $node, $complexity = 0)
    {
        switch (true) {
            case $node instanceof FieldNode:
                // default values
                $args         = [];
                $complexityFn = FieldDefinition::DEFAULT_COMPLEXITY_FN;

                // calculate children complexity if needed
                $childrenComplexity = 0;

                // node has children?
                if (isset($node->selectionSet)) {
                    $childrenComplexity = $this->fieldComplexity($node);
                }

                $astFieldInfo = $this->astFieldInfo($node);
                $fieldDef     = $astFieldInfo[1];

                if ($fieldDef instanceof FieldDefinition) {
                    if ($this->directiveExcludesField($node)) {
                        break;
                    }

                    $args = $this->buildFieldArguments($node);
                    //get complexity fn using fieldDef complexity
                    if (method_exists($fieldDef, 'getComplexityFn')) {
                        $complexityFn = $fieldDef->getComplexityFn();
                    }
                }

                $complexity += $complexityFn($childrenComplexity, $args);
                break;

            case $node instanceof InlineFragmentNode:
                // node has children?
                if (isset($node->selectionSet)) {
                    $complexity = $this->fieldComplexity($node, $complexity);
                }

                break;

            case $node instanceof FragmentSpreadNode:
                $fragment = $this->getFragment($node);

                if ($fragment !== null) {
                    $complexity = $this->fieldComplexity($fragment, $complexity);
                }

                break;
        }

        return $complexity;
    }

    protected function astFieldInfo(FieldNode $field)
    {
        $fieldName    = $this->getFieldName($field);
        $astFieldInfo = [null, null];
        if (isset($this->fieldNodeAndDefs[$fieldName])) {
            foreach ($this->fieldNodeAndDefs[$fieldName] as $astAndDef) {
                if ($astAndDef[0] === $field) {
                    $astFieldInfo = $astAndDef;
                    break;
                }
            }
        }

        return $astFieldInfo;
    }

    protected function directiveExcludesField(FieldNode $node)
    {
        foreach ($node->directives as $directiveNode) {
            if ($directiveNode->name->value === 'deprecated') {
                return false;
            }

            [$errors, $variableValues] = Values::getVariableValues(
                $this->context->getSchema(),
                $this->variableDefs,
                $this->getRawVariableValues()
            );
            if (count($errors ?? []) > 0) {
                throw new Error(implode(
                    "\n\n",
                    array_map(
                        static function ($error) {
                            return $error->getMessage();
                        },
                        $errors
                    )
                ));
            }

            if ($directiveNode->name->value === 'include') {
                $directive = Directive::includeDirective();
                /** @var bool $directiveArgsIf */
                $directiveArgsIf = Values::getArgumentValues($directive, $directiveNode, $variableValues)['if'];

                return ! $directiveArgsIf;
            }

            if ($directiveNode->name->value === Directive::SKIP_NAME) {
                $directive = Directive::skipDirective();
                /** @var bool $directiveArgsIf */
                $directiveArgsIf = Values::getArgumentValues($directive, $directiveNode, $variableValues)['if'];

                return $directiveArgsIf;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawVariableValues(): array
    {
        return $this->rawVariableValues;
    }

    /**
     * @param array<string, mixed>|null $rawVariableValues
     */
    public function setRawVariableValues(?array $rawVariableValues = null): void
    {
        $this->rawVariableValues = $rawVariableValues ?? [];
    }

    protected function buildFieldArguments(FieldNode $node)
    {
        $rawVariableValues = $this->getRawVariableValues();
        $astFieldInfo      = $this->astFieldInfo($node);
        $fieldDef          = $astFieldInfo[1];

        $args = [];

        if ($fieldDef instanceof FieldDefinition) {
            [$errors, $variableValues] = Values::getVariableValues(
                $this->context->getSchema(),
                $this->variableDefs,
                $rawVariableValues
            );

            if (count($errors ?? []) > 0) {
                throw new Error(implode(
                    "\n\n",
                    array_map(
                        static function ($error) {
                            return $error->getMessage();
                        },
                        $errors
                    )
                ));
            }

            $args = Values::getArgumentValues($fieldDef, $node, $variableValues);
        }

        return $args;
    }

    public function getQueryComplexity()
    {
        return $this->complexity;
    }

    public function getMaxQueryComplexity()
    {
        return $this->maxQueryComplexity;
    }

    /**
     * Set max query complexity. If equal to 0 no check is done. Must be greater or equal to 0.
     */
    public function setMaxQueryComplexity($maxQueryComplexity)
    {
        $this->checkIfGreaterOrEqualToZero('maxQueryComplexity', $maxQueryComplexity);

        $this->maxQueryComplexity = (int) $maxQueryComplexity;
    }

    public static function maxQueryComplexityErrorMessage($max, $count)
    {
        return sprintf('Max query complexity should be %d but got %d.', $max, $count);
    }

    protected function isEnabled()
    {
        return $this->getMaxQueryComplexity() !== self::DISABLED;
    }
}
