<?php declare(strict_types=1);

namespace GraphQL\Validator;

use function array_merge;
use function array_pop;
use function count;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\HasSelectionSet;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\Argument;
use GraphQL\Type\Definition\CompositeType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\OutputType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\TypeInfo;
use SplObjectStorage;

/**
 * An instance of this class is passed as the "this" context to all validators,
 * allowing access to commonly useful contextual information from within a
 * validation rule.
 *
 * @phpstan-type VariableUsage array{node: VariableNode, type: (Type&InputType)|null, defaultValue: mixed}
 */
class ValidationContext extends ASTValidationContext
{
    private TypeInfo $typeInfo;

    /** @var array<string, FragmentDefinitionNode> */
    private array $fragments;

    /** @var SplObjectStorage<HasSelectionSet, array<int, FragmentSpreadNode>> */
    private SplObjectStorage $fragmentSpreads;

    /** @var SplObjectStorage<OperationDefinitionNode, array<int, FragmentDefinitionNode>> */
    private SplObjectStorage $recursivelyReferencedFragments;

    /** @var SplObjectStorage<HasSelectionSet, array<int, VariableUsage>> */
    private SplObjectStorage $variableUsages;

    /** @var SplObjectStorage<HasSelectionSet, array<int, VariableUsage>> */
    private SplObjectStorage $recursiveVariableUsages;

    public function __construct(Schema $schema, DocumentNode $ast, TypeInfo $typeInfo)
    {
        parent::__construct($ast, $schema);
        $this->typeInfo = $typeInfo;
        $this->fragmentSpreads = new SplObjectStorage();
        $this->recursivelyReferencedFragments = new SplObjectStorage();
        $this->variableUsages = new SplObjectStorage();
        $this->recursiveVariableUsages = new SplObjectStorage();
    }

    /**
     * @phpstan-return array<int, VariableUsage>
     */
    public function getRecursiveVariableUsages(OperationDefinitionNode $operation): array
    {
        $usages = $this->recursiveVariableUsages[$operation] ?? null;

        if (null === $usages) {
            $usages = $this->getVariableUsages($operation);
            $fragments = $this->getRecursivelyReferencedFragments($operation);

            $allUsages = [$usages];
            foreach ($fragments as $fragment) {
                $allUsages[] = $this->getVariableUsages($fragment);
            }

            $usages = array_merge(...$allUsages);
            $this->recursiveVariableUsages[$operation] = $usages;
        }

        return $usages;
    }

    /**
     * @param HasSelectionSet|Node $node
     *
     * @phpstan-return array<int, VariableUsage>
     */
    private function getVariableUsages(HasSelectionSet $node): array
    {
        if (! isset($this->variableUsages[$node])) {
            $usages = [];
            $typeInfo = new TypeInfo($this->schema);
            Visitor::visit(
                $node,
                Visitor::visitWithTypeInfo(
                    $typeInfo,
                    [
                        NodeKind::VARIABLE_DEFINITION => static fn (): bool => false,
                        NodeKind::VARIABLE => static function (VariableNode $variable) use (
                            &$usages,
                            $typeInfo
                        ): void {
                            $usages[] = [
                                'node' => $variable,
                                'type' => $typeInfo->getInputType(),
                                'defaultValue' => $typeInfo->getDefaultValue(),
                            ];
                        },
                    ]
                )
            );

            return $this->variableUsages[$node] = $usages;
        }

        return $this->variableUsages[$node];
    }

    /**
     * @return array<int, FragmentDefinitionNode>
     */
    public function getRecursivelyReferencedFragments(OperationDefinitionNode $operation): array
    {
        $fragments = $this->recursivelyReferencedFragments[$operation] ?? null;

        if (null === $fragments) {
            $fragments = [];
            $collectedNames = [];
            $nodesToVisit = [$operation];
            while (count($nodesToVisit) > 0) {
                $node = array_pop($nodesToVisit);
                $spreads = $this->getFragmentSpreads($node);
                foreach ($spreads as $spread) {
                    $fragName = $spread->name->value;

                    if ($collectedNames[$fragName] ?? false) {
                        continue;
                    }

                    $collectedNames[$fragName] = true;
                    $fragment = $this->getFragment($fragName);
                    if (null === $fragment) {
                        continue;
                    }

                    $fragments[] = $fragment;
                    $nodesToVisit[] = $fragment;
                }
            }

            $this->recursivelyReferencedFragments[$operation] = $fragments;
        }

        return $fragments;
    }

    /**
     * @param OperationDefinitionNode|FragmentDefinitionNode $node
     *
     * @return array<int, FragmentSpreadNode>
     */
    public function getFragmentSpreads(HasSelectionSet $node): array
    {
        $spreads = $this->fragmentSpreads[$node] ?? null;
        if (null === $spreads) {
            $spreads = [];

            /** @var array<int, SelectionSetNode> $setsToVisit */
            $setsToVisit = [$node->selectionSet];
            while (count($setsToVisit) > 0) {
                $set = array_pop($setsToVisit);

                for ($i = 0, $selectionCount = count($set->selections); $i < $selectionCount; ++$i) {
                    $selection = $set->selections[$i];
                    if ($selection instanceof FragmentSpreadNode) {
                        $spreads[] = $selection;
                    } elseif ($selection instanceof FieldNode || $selection instanceof InlineFragmentNode) {
                        if (null !== $selection->selectionSet) {
                            $setsToVisit[] = $selection->selectionSet;
                        }
                    } else {
                        throw InvariantViolation::shouldNotHappen();
                    }
                }
            }

            $this->fragmentSpreads[$node] = $spreads;
        }

        return $spreads;
    }

    public function getFragment(string $name): ?FragmentDefinitionNode
    {
        if (! isset($this->fragments)) {
            $fragments = [];
            foreach ($this->getDocument()->definitions as $statement) {
                if ($statement instanceof FragmentDefinitionNode) {
                    $fragments[$statement->name->value] = $statement;
                }
            }

            $this->fragments = $fragments;
        }

        return $this->fragments[$name] ?? null;
    }

    /**
     * @return (OutputType&Type)|null
     */
    public function getType(): ?OutputType
    {
        return $this->typeInfo->getType();
    }

    /**
     * @return (CompositeType&Type)|null
     */
    public function getParentType(): ?CompositeType
    {
        return $this->typeInfo->getParentType();
    }

    /**
     * @return (Type&InputType)|null
     */
    public function getInputType(): ?InputType
    {
        return $this->typeInfo->getInputType();
    }

    /**
     * @return (Type&InputType)|null
     */
    public function getParentInputType(): ?InputType
    {
        return $this->typeInfo->getParentInputType();
    }

    public function getFieldDef(): ?FieldDefinition
    {
        return $this->typeInfo->getFieldDef();
    }

    public function getDirective(): ?Directive
    {
        return $this->typeInfo->getDirective();
    }

    public function getArgument(): ?Argument
    {
        return $this->typeInfo->getArgument();
    }
}
