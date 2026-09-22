<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\Values;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Type\Definition\Argument;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Introspection;
use GraphQL\Validator\QueryValidationContext;

/**
 * @phpstan-import-type ASTAndDefs from QuerySecurityRule
 */
class QueryComplexity extends QuerySecurityRule
{
    protected int $maxQueryComplexity;

    protected int $queryComplexity;

    /** @var array<string, mixed> */
    protected array $rawVariableValues = [];

    /**
     * Compute the worst-case complexity of variables that were not provided a value?
     *
     * Enable this to compute the complexity of operations without variable values,
     * such as when validating a persisted operation manifest. Variables that were
     * not provided a value are then considered unknown:
     * - fields with `@include(if: $var)` or `@skip(if: $var)` count as included,
     *   only literal `@include(if: false)` and `@skip(if: true)` exclude a field
     * - field arguments that depend on such a variable are omitted from the arguments
     *   passed to `complexity` functions, even when their definition has a default value,
     *   because the value used at runtime is unknown
     *
     * Variables that were provided a value are always used as given, and providing
     * an invalid value still results in an error.
     */
    public bool $assumeWorstCaseForUnprovidedVariables = false;

    /** @var NodeList<VariableDefinitionNode> */
    protected NodeList $variableDefs;

    /** @phpstan-var ASTAndDefs */
    protected \ArrayObject $fieldNodeAndDefs;

    protected QueryValidationContext $context;

    /** @var array<string, mixed>|null Lazily coerced variable values; reset per document. */
    private ?array $coercedVariableValues = null;

    /** @throws \InvalidArgumentException */
    public function __construct(int $maxQueryComplexity)
    {
        $this->setMaxQueryComplexity($maxQueryComplexity);
    }

    public function getVisitor(QueryValidationContext $context): array
    {
        $this->queryComplexity = 0;
        $this->context = $context;
        $this->variableDefs = new NodeList([]);
        $this->fieldNodeAndDefs = new \ArrayObject();
        $this->coercedVariableValues = null;

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
                NodeKind::DOCUMENT => [
                    'leave' => function (DocumentNode $document) use ($context): void {
                        $errors = $context->getErrors();

                        if ($errors !== []) {
                            return;
                        }

                        if ($this->maxQueryComplexity === self::DISABLED) {
                            return;
                        }

                        foreach ($document->definitions as $definition) {
                            if (! $definition instanceof OperationDefinitionNode) {
                                continue;
                            }

                            $this->queryComplexity = $this->fieldComplexity($definition->selectionSet);

                            if ($this->queryComplexity > $this->maxQueryComplexity) {
                                $context->reportError(
                                    new Error(static::maxQueryComplexityErrorMessage(
                                        $this->maxQueryComplexity,
                                        $this->queryComplexity
                                    ))
                                );

                                return;
                            }
                        }
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
                // Exclude __schema field and all nested content from complexity calculation
                if ($node->name->value === Introspection::SCHEMA_FIELD_NAME) {
                    return 0;
                }

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
     * Will the given field be executed at all, given the directives placed upon it?
     *
     * @throws \Exception
     * @throws \ReflectionException
     * @throws InvariantViolation
     */
    protected function directiveExcludesField(FieldNode $node): bool
    {
        foreach ($node->directives as $directiveNode) {
            $directiveName = $directiveNode->name->value;
            if ($directiveName !== Directive::INCLUDE_NAME && $directiveName !== Directive::SKIP_NAME) {
                continue;
            }

            if ($this->conditionDependsOnUnprovidedVariable($directiveNode)) {
                // Without a variable value, the condition can not be evaluated.
                // The worst case is that the field is included.
                continue;
            }

            if ($directiveName === Directive::INCLUDE_NAME) {
                $includeArguments = Values::getArgumentValues(
                    Directive::includeDirective(),
                    $directiveNode,
                    $this->getCoercedVariableValues()
                );
                assert(is_bool($includeArguments['if']), 'ensured by query validation');

                if (! $includeArguments['if']) {
                    return true;
                }
            } else {
                $skipArguments = Values::getArgumentValues(
                    Directive::skipDirective(),
                    $directiveNode,
                    $this->getCoercedVariableValues()
                );
                assert(is_bool($skipArguments['if']), 'ensured by query validation');

                if ($skipArguments['if']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Is the condition of the given `@include`/`@skip` directive a variable without a value?
     *
     * @throws \Exception
     * @throws InvariantViolation
     */
    private function conditionDependsOnUnprovidedVariable(DirectiveNode $directiveNode): bool
    {
        if (! $this->assumeWorstCaseForUnprovidedVariables) {
            return false;
        }

        foreach ($directiveNode->arguments as $argumentNode) {
            if ($argumentNode->name->value === 'if') {
                return $this->dependsOnUnprovidedVariable($argumentNode->value);
            }
        }

        return false;
    }

    /**
     * Does the given value reference a variable that was not provided a value?
     *
     * @throws \Exception
     * @throws InvariantViolation
     */
    private function dependsOnUnprovidedVariable(ValueNode $value): bool
    {
        if ($value instanceof VariableNode) {
            return ! array_key_exists($value->name->value, $this->getCoercedVariableValues());
        }

        if ($value instanceof ListValueNode) {
            foreach ($value->values as $itemValue) {
                if ($this->dependsOnUnprovidedVariable($itemValue)) {
                    return true;
                }
            }
        }

        if ($value instanceof ObjectValueNode) {
            foreach ($value->fields as $fieldValue) {
                if ($this->dependsOnUnprovidedVariable($fieldValue->value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Coerce variable values once per document and cache them.
     *
     * @throws \Exception
     * @throws InvariantViolation
     *
     * @return array<string, mixed>
     */
    private function getCoercedVariableValues(): array
    {
        if ($this->coercedVariableValues !== null) {
            return $this->coercedVariableValues;
        }

        [$errors, $variableValues] = Values::getVariableValues(
            $this->context->getSchema(),
            $this->coercibleVariableDefs(),
            $this->getRawVariableValues()
        );
        if ($errors !== null && $errors !== []) {
            throw new Error(implode("\n\n", array_map(static fn (Error $error): string => $error->getMessage(), $errors)));
        }

        return $this->coercedVariableValues = $variableValues ?? [];
    }

    /**
     * The variable definitions that can be coerced with the raw variable values at hand.
     *
     * Unless the worst case is assumed, this is simply all of them - coercion reports an
     * error for variables that require a value but were not provided one. When assuming
     * the worst case, those variables are left out, so their values remain unknown.
     *
     * @return NodeList<VariableDefinitionNode>
     */
    private function coercibleVariableDefs(): NodeList
    {
        if (! $this->assumeWorstCaseForUnprovidedVariables) {
            return $this->variableDefs;
        }

        $rawVariableValues = $this->getRawVariableValues();

        $coercible = [];
        foreach ($this->variableDefs as $variableDef) {
            if (array_key_exists($variableDef->variable->name->value, $rawVariableValues)
                || $variableDef->defaultValue !== null
            ) {
                $coercible[] = $variableDef;
            }
        }

        return new NodeList($coercible);
    }

    /** @return array<string, mixed> */
    public function getRawVariableValues(): array
    {
        return $this->rawVariableValues;
    }

    /** @param array<string, mixed>|null $rawVariableValues */
    public function setRawVariableValues(?array $rawVariableValues = null): void
    {
        $this->rawVariableValues = $rawVariableValues ?? [];
    }

    /**
     * @throws \Exception
     * @throws Error
     * @throws InvariantViolation
     *
     * @return array<string, mixed>
     */
    protected function buildFieldArguments(FieldNode $node): array
    {
        $fieldDef = $this->fieldDefinition($node);
        if (! $fieldDef instanceof FieldDefinition) {
            return [];
        }

        $variableValues = $this->getCoercedVariableValues();

        if (! $this->assumeWorstCaseForUnprovidedVariables) {
            return Values::getArgumentValues($fieldDef, $node, $variableValues);
        }

        // Arguments that depend on a variable without a value can not be coerced,
        // so they are left out and complexity functions get to decide what to make
        // of their absence.
        $unknownArgumentNames = [];
        $argumentValueMap = [];
        foreach ($node->arguments as $argumentNode) {
            $argumentName = $argumentNode->name->value;

            if ($this->dependsOnUnprovidedVariable($argumentNode->value)) {
                $unknownArgumentNames[$argumentName] = true;
                continue;
            }

            $argumentValueMap[$argumentName] = $argumentNode->value;
        }

        if ($unknownArgumentNames !== []) {
            // Coercion is done as if the field did not define the unknown arguments at all.
            // Were they left in, coercion would substitute the default value of their
            // definition - hiding that the value is unknown - or fail when they are required.
            $fieldDef = clone $fieldDef;
            $fieldDef->args = array_values(array_filter(
                $fieldDef->args,
                static fn (Argument $argument): bool => ! isset($unknownArgumentNames[$argument->name])
            ));
        }

        return Values::getArgumentValuesForMap($fieldDef, $argumentValueMap, $variableValues, $node);
    }

    public function getMaxQueryComplexity(): int
    {
        return $this->maxQueryComplexity;
    }

    /**
     * Complexity of the first operation exceeding the defined limit, or, in case no operation
     * exceeds the limit, complexity of the last defined operation.
     */
    public function getQueryComplexity(): int
    {
        return $this->queryComplexity;
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
