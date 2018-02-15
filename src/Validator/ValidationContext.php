<?php
namespace GraphQL\Validator;

use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\HasSelectionSet;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\Visitor;
use \SplObjectStorage;
use GraphQL\Error\Error;
use GraphQL\Type\Schema;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Type\Definition\CompositeType;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\TypeInfo;

/**
 * An instance of this class is passed as the "this" context to all validators,
 * allowing access to commonly useful contextual information from within a
 * validation rule.
 */
class ValidationContext
{
    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var DocumentNode
     */
    private $ast;

    /**
     * @var TypeInfo
     */
    private $typeInfo;

    /**
     * @var Error[]
     */
    private $errors;

    /**
     * @var FragmentDefinitionNode[]
     */
    private $fragments;

    /**
     * @var SplObjectStorage
     */
    private $fragmentSpreads;

    /**
     * @var SplObjectStorage
     */
    private $recursivelyReferencedFragments;

    /**
     * @var SplObjectStorage
     */
    private $variableUsages;

    /**
     * @var SplObjectStorage
     */
    private $recursiveVariableUsages;

    /**
     * ValidationContext constructor.
     *
     * @param Schema $schema
     * @param DocumentNode $ast
     * @param TypeInfo $typeInfo
     */
    function __construct(Schema $schema, DocumentNode $ast, TypeInfo $typeInfo)
    {
        $this->schema = $schema;
        $this->ast = $ast;
        $this->typeInfo = $typeInfo;
        $this->errors = [];
        $this->fragmentSpreads = new SplObjectStorage();
        $this->recursivelyReferencedFragments = new SplObjectStorage();
        $this->variableUsages = new SplObjectStorage();
        $this->recursiveVariableUsages = new SplObjectStorage();
    }

    /**
     * @param Error $error
     */
    function reportError(Error $error)
    {
        $this->errors[] = $error;
    }

    /**
     * @return Error[]
     */
    function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return Schema
     */
    function getSchema()
    {
        return $this->schema;
    }

    /**
     * @return DocumentNode
     */
    function getDocument()
    {
        return $this->ast;
    }

    /**
     * @param string $name
     * @return FragmentDefinitionNode|null
     */
    function getFragment($name)
    {
        $fragments = $this->fragments;
        if (!$fragments) {
            $fragments = [];
            foreach ($this->getDocument()->definitions as $statement) {
                if ($statement->kind === NodeKind::FRAGMENT_DEFINITION) {
                    $fragments[$statement->name->value] = $statement;
                }
            }
            $this->fragments = $fragments;
        }
        return isset($fragments[$name]) ? $fragments[$name] : null;
    }

    /**
     * @param HasSelectionSet $node
     * @return FragmentSpreadNode[]
     */
    function getFragmentSpreads(HasSelectionSet $node)
    {
        $spreads = isset($this->fragmentSpreads[$node]) ? $this->fragmentSpreads[$node] : null;
        if (!$spreads) {
            $spreads = [];
            $setsToVisit = [$node->selectionSet];
            while (!empty($setsToVisit)) {
                $set = array_pop($setsToVisit);

                for ($i = 0; $i < count($set->selections); $i++) {
                    $selection = $set->selections[$i];
                    if ($selection->kind === NodeKind::FRAGMENT_SPREAD) {
                        $spreads[] = $selection;
                    } else if ($selection->selectionSet) {
                        $setsToVisit[] = $selection->selectionSet;
                    }
                }
            }
            $this->fragmentSpreads[$node] = $spreads;
        }
        return $spreads;
    }

    /**
     * @param OperationDefinitionNode $operation
     * @return FragmentDefinitionNode[]
     */
    function getRecursivelyReferencedFragments(OperationDefinitionNode $operation)
    {
        $fragments = isset($this->recursivelyReferencedFragments[$operation]) ? $this->recursivelyReferencedFragments[$operation] : null;

        if (!$fragments) {
            $fragments = [];
            $collectedNames = [];
            $nodesToVisit = [$operation];
            while (!empty($nodesToVisit)) {
                $node = array_pop($nodesToVisit);
                $spreads = $this->getFragmentSpreads($node);
                for ($i = 0; $i < count($spreads); $i++) {
                    $fragName = $spreads[$i]->name->value;

                    if (empty($collectedNames[$fragName])) {
                        $collectedNames[$fragName] = true;
                        $fragment = $this->getFragment($fragName);
                        if ($fragment) {
                            $fragments[] = $fragment;
                            $nodesToVisit[] = $fragment;
                        }
                    }
                }
            }
            $this->recursivelyReferencedFragments[$operation] = $fragments;
        }
        return $fragments;
    }

    /**
     * @param HasSelectionSet $node
     * @return array List of ['node' => VariableNode, 'type' => ?InputObjectType]
     */
    function getVariableUsages(HasSelectionSet $node)
    {
        $usages = isset($this->variableUsages[$node]) ? $this->variableUsages[$node] : null;

        if (!$usages) {
            $newUsages = [];
            $typeInfo = new TypeInfo($this->schema);
            Visitor::visit($node, Visitor::visitWithTypeInfo($typeInfo, [
                NodeKind::VARIABLE_DEFINITION => function () {
                    return false;
                },
                NodeKind::VARIABLE => function (VariableNode $variable) use (&$newUsages, $typeInfo) {
                    $newUsages[] = ['node' => $variable, 'type' => $typeInfo->getInputType()];
                }
            ]));
            $usages = $newUsages;
            $this->variableUsages[$node] = $usages;
        }
        return $usages;
    }

    /**
     * @param OperationDefinitionNode $operation
     * @return array List of ['node' => VariableNode, 'type' => ?InputObjectType]
     */
    function getRecursiveVariableUsages(OperationDefinitionNode $operation)
    {
        $usages = isset($this->recursiveVariableUsages[$operation]) ? $this->recursiveVariableUsages[$operation] : null;

        if (!$usages) {
            $usages = $this->getVariableUsages($operation);
            $fragments = $this->getRecursivelyReferencedFragments($operation);

            $tmp = [$usages];
            for ($i = 0; $i < count($fragments); $i++) {
                $tmp[] = $this->getVariableUsages($fragments[$i]);
            }
            $usages = call_user_func_array('array_merge', $tmp);
            $this->recursiveVariableUsages[$operation] = $usages;
        }
        return $usages;
    }

    /**
     * Returns OutputType
     *
     * @return Type
     */
    function getType()
    {
        return $this->typeInfo->getType();
    }

    /**
     * @return CompositeType
     */
    function getParentType()
    {
        return $this->typeInfo->getParentType();
    }

    /**
     * @return InputType
     */
    function getInputType()
    {
        return $this->typeInfo->getInputType();
    }

    /**
     * @return InputType
     */
    function getParentInputType()
    {
        return $this->typeInfo->getParentInputType();
    }

    /**
     * @return FieldDefinition
     */
    function getFieldDef()
    {
        return $this->typeInfo->getFieldDef();
    }

    function getDirective()
    {
        return $this->typeInfo->getDirective();
    }

    function getArgument()
    {
        return $this->typeInfo->getArgument();
    }
}
