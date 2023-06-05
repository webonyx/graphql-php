<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\Values;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Validator\QueryValidationContext;

/**
 * @phpstan-import-type ASTAndDefs from QuerySecurityRule
 */
class QueryComplexity extends QuerySecurityRule
{
    protected int $maxQueryComplexity;

    /** @var array<string, mixed> */
    protected array $rawVariableValues = [];

    /** @var NodeList<VariableDefinitionNode> */
    protected NodeList $variableDefs;

    /** @phpstan-var ASTAndDefs */
    protected \ArrayObject $fieldNodeAndDefs;

    protected QueryValidationContext $context;

    /** @throws \InvalidArgumentException */
    public function __construct(int $maxQueryComplexity)
    {
        $this->setMaxQueryComplexity($maxQueryComplexity);
    }

    public function getVisitor(QueryValidationContext $context): array
    {
        $this->context = $context;
        $this->variableDefs = new NodeList([]);
        $this->fieldNodeAndDefs = new \ArrayObject();

        return $this->invokeIfNeeded(
            $context,
            [
                NodeKind::SELECTION_SET => function (SelectionSetNode $selectionSet) use ($context): void {
                    $this->fieldNodeAndDefs = $this->collectFieldASTsAndDefs(
                        $context,
                        $context->getParentType(),
                        $selectionSet,
                        null,
                        $this->fieldNodeAndDefs
                    );
                },
                NodeKind::VARIABLE_DEFINITION => function ($def): VisitorOperation {
                    $this->variableDefs[] = $def;

                    return Visitor::skipNode();
                },
                NodeKind::OPERATION_DEFINITION => [
                    'leave' => function (OperationDefinitionNode $operationDefinition) use ($context): void {
                        $errors = $context->getErrors();

                        if ($errors !== []) {
                            return;
                        }

                        $complexity = $this->fieldComplexity($operationDefinition->selectionSet);

                        if ($complexity <= $this->maxQueryComplexity) {
                            return;
                        }

                        $context->reportError(
                            new Error(static::maxQueryComplexityErrorMessage(
                                $this->maxQueryComplexity,
                                $complexity
                            ))
                        );
                    },
                ],
            ]
        );
    }

    /** @throws \Exception */
    protected function fieldComplexity(SelectionSetNode $selectionSet): int
    {
        $complexity = 0;

        foreach ($selectionSet->selections as $selection) {
            $complexity += $this->nodeComplexity($selection);
        }

        return $complexity;
    }

    /** @throws \Exception */
    protected function nodeComplexity(SelectionNode $node): int
    {
        switch (true) {
            case $node instanceof FieldNode:
                if ($this->directiveExcludesField($node)) {
                    return 0;
                }

                $childrenComplexity = isset($node->selectionSet)
                    ? $this->fieldComplexity($node->selectionSet)
                    : 0;

                $fieldDef = $this->fieldDefinition($node);
                if ($fieldDef instanceof FieldDefinition && $fieldDef->complexityFn !== null) {
                    $fieldArguments = $this->buildFieldArguments($node);

                    return ($fieldDef->complexityFn)($childrenComplexity, $fieldArguments);
                }

                return $childrenComplexity + 1;

            case $node instanceof InlineFragmentNode:
                return $this->fieldComplexity($node->selectionSet);

            case $node instanceof FragmentSpreadNode:
                $fragment = $this->getFragment($node);

                if ($fragment !== null) {
                    return $this->fieldComplexity($fragment->selectionSet);
                }
        }

        return 0;
    }

    protected function fieldDefinition(FieldNode $field): ?FieldDefinition
    {
        foreach ($this->fieldNodeAndDefs[$this->getFieldName($field)] ?? [] as [$node, $def]) {
            if ($node === $field) {
                return $def;
            }
        }

        return null;
    }

    /**
     * @throws \Exception
     * @throws \ReflectionException
     * @throws InvariantViolation
     */
    protected function directiveExcludesField(FieldNode $node): bool
    {
        foreach ($node->directives as $directiveNode) {
            if ($directiveNode->name->value === Directive::DEPRECATED_NAME) {
                return false;
            }

            [$errors, $variableValues] = Values::getVariableValues(
                $this->context->getSchema(),
                $this->variableDefs,
                $this->getRawVariableValues()
            );
            if ($errors !== null && $errors !== []) {
                throw new Error(\implode(
                    "\n\n",
                    \array_map(
                        static fn (Error $error): string => $error->getMessage(),
                        $errors
                    )
                ));
            }

            if ($directiveNode->name->value === Directive::INCLUDE_NAME) {
                $includeArguments = Values::getArgumentValues(
                    Directive::includeDirective(),
                    $directiveNode,
                    $variableValues
                );
                assert(is_bool($includeArguments['if']), 'ensured by query validation');

                return ! $includeArguments['if'];
            }

            if ($directiveNode->name->value === Directive::SKIP_NAME) {
                $skipArguments = Values::getArgumentValues(
                    Directive::skipDirective(),
                    $directiveNode,
                    $variableValues
                );
                assert(is_bool($skipArguments['if']), 'ensured by query validation');

                return $skipArguments['if'];
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function getRawVariableValues(): array
    {
        return $this->rawVariableValues;
    }

    /** @param array<string, mixed>|null $rawVariableValues */
    public function setRawVariableValues(array $rawVariableValues = null): void
    {
        $this->rawVariableValues = $rawVariableValues ?? [];
    }

    /**
     * @throws \Exception
     * @throws Error
     *
     * @return array<string, mixed>
     */
    protected function buildFieldArguments(FieldNode $node): array
    {
        $rawVariableValues = $this->getRawVariableValues();
        $fieldDef = $this->fieldDefinition($node);

        /** @var array<string, mixed> $args */
        $args = [];

        if ($fieldDef instanceof FieldDefinition) {
            [$errors, $variableValues] = Values::getVariableValues(
                $this->context->getSchema(),
                $this->variableDefs,
                $rawVariableValues
            );

            if (is_array($errors) && $errors !== []) {
                throw new Error(\implode(
                    "\n\n",
                    \array_map(
                        static fn ($error) => $error->getMessage(),
                        $errors
                    )
                ));
            }

            $args = Values::getArgumentValues($fieldDef, $node, $variableValues);
        }

        return $args;
    }

    public function getMaxQueryComplexity(): int
    {
        return $this->maxQueryComplexity;
    }

    /**
     * Set max query complexity. If equal to 0 no check is done. Must be greater or equal to 0.
     *
     * @throws \InvalidArgumentException
     */
    public function setMaxQueryComplexity(int $maxQueryComplexity): void
    {
        $this->checkIfGreaterOrEqualToZero('maxQueryComplexity', $maxQueryComplexity);

        $this->maxQueryComplexity = $maxQueryComplexity;
    }

    public static function maxQueryComplexityErrorMessage(int $max, int $count): string
    {
        return "Max query complexity should be {$max} but got {$count}.";
    }

    protected function isEnabled(): bool
    {
        return $this->maxQueryComplexity !== self::DISABLED;
    }
}
