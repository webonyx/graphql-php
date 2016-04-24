<?php
namespace GraphQL\Validator;

use GraphQL\Language\AST\FragmentSpread;
use GraphQL\Language\AST\HasSelectionSet;
use GraphQL\Language\AST\OperationDefinition;
use GraphQL\Language\AST\Variable;
use GraphQL\Language\Visitor;
use \SplObjectStorage;
use GraphQL\Error;
use GraphQL\Schema;
use GraphQL\Language\AST\Document;
use GraphQL\Language\AST\FragmentDefinition;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\CompositeType;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\OutputType;
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
    private $_schema;

    /**
     * @var Document
     */
    private $_ast;

    /**
     * @var TypeInfo
     */
    private $_typeInfo;

    /**
     * @var Error[]
     */
    private $_errors;

    /**
     * @var array<string, FragmentDefinition>
     */
    private $_fragments;

    /**
     * @var SplObjectStorage
     */
    private $_fragmentSpreads;

    /**
     * @var SplObjectStorage
     */
    private $_recursivelyReferencedFragments;

    /**
     * @var SplObjectStorage
     */
    private $_variableUsages;

    /**
     * @var SplObjectStorage
     */
    private $_recursiveVariableUsages;

    /**
     * ValidationContext constructor.
     *
     * @param Schema $schema
     * @param Document $ast
     * @param TypeInfo $typeInfo
     */
    function __construct(Schema $schema, Document $ast, TypeInfo $typeInfo)
    {
        $this->_schema = $schema;
        $this->_ast = $ast;
        $this->_typeInfo = $typeInfo;
        $this->_errors = [];
        $this->_fragmentSpreads = new SplObjectStorage();
        $this->_recursivelyReferencedFragments = new SplObjectStorage();
        $this->_variableUsages = new SplObjectStorage();
        $this->_recursiveVariableUsages = new SplObjectStorage();
    }

    /**
     * @param Error $error
     */
    function reportError(Error $error)
    {
        $this->_errors[] = $error;
    }

    /**
     * @return Error[]
     */
    function getErrors()
    {
        return $this->_errors;
    }

    /**
     * @return Schema
     */
    function getSchema()
    {
        return $this->_schema;
    }

    /**
     * @return Document
     */
    function getDocument()
    {
        return $this->_ast;
    }

    /**
     * @param $name
     * @return FragmentDefinition|null
     */
    function getFragment($name)
    {
        $fragments = $this->_fragments;
        if (!$fragments) {
            $this->_fragments = $fragments =
                array_reduce($this->getDocument()->definitions, function($frags, $statement) {
                    if ($statement->kind === Node::FRAGMENT_DEFINITION) {
                        $frags[$statement->name->value] = $statement;
                    }
                    return $frags;
                }, []);
        }
        return isset($fragments[$name]) ? $fragments[$name] : null;
    }

    /**
     * @param HasSelectionSet $node
     * @return FragmentSpread[]
     */
    function getFragmentSpreads(HasSelectionSet $node)
    {
        $spreads = isset($this->_fragmentSpreads[$node]) ? $this->_fragmentSpreads[$node] : null;
        if (!$spreads) {
            $spreads = [];
            $setsToVisit = [$node->selectionSet];
            while (!empty($setsToVisit)) {
                $set = array_pop($setsToVisit);

                for ($i = 0; $i < count($set->selections); $i++) {
                    $selection = $set->selections[$i];
                    if ($selection->kind === Node::FRAGMENT_SPREAD) {
                        $spreads[] = $selection;
                    } else if ($selection->selectionSet) {
                        $setsToVisit[] = $selection->selectionSet;
                    }
                }
            }
            $this->_fragmentSpreads[$node] = $spreads;
        }
        return $spreads;
    }

    /**
     * @param OperationDefinition $operation
     * @return FragmentDefinition[]
     */
    function getRecursivelyReferencedFragments(OperationDefinition $operation)
    {
        $fragments = isset($this->_recursivelyReferencedFragments[$operation]) ? $this->_recursivelyReferencedFragments[$operation] : null;

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
            $this->_recursivelyReferencedFragments[$operation] = $fragments;
        }
        return $fragments;
    }

    /**
     * @param HasSelectionSet $node
     * @return array List of ['node' => Variable, 'type' => ?InputObjectType]
     */
    function getVariableUsages(HasSelectionSet $node)
    {
        $usages = isset($this->_variableUsages[$node]) ? $this->_variableUsages[$node] : null;

        if (!$usages) {
            $newUsages = [];
            $typeInfo = new TypeInfo($this->_schema);
            Visitor::visit($node, Visitor::visitWithTypeInfo($typeInfo, [
                Node::VARIABLE_DEFINITION => function () {
                    return false;
                },
                Node::VARIABLE => function (Variable $variable) use (&$newUsages, $typeInfo) {
                    $newUsages[] = ['node' => $variable, 'type' => $typeInfo->getInputType()];
                }
            ]));
            $usages = $newUsages;
            $this->_variableUsages[$node] = $usages;
        }
        return $usages;
    }

    /**
     * @param OperationDefinition $operation
     * @return array List of ['node' => Variable, 'type' => ?InputObjectType]
     */
    function getRecursiveVariableUsages(OperationDefinition $operation)
    {
        $usages = isset($this->_recursiveVariableUsages[$operation]) ? $this->_recursiveVariableUsages[$operation] : null;

        if (!$usages) {
            $usages = $this->getVariableUsages($operation);
            $fragments = $this->getRecursivelyReferencedFragments($operation);

            $tmp = [$usages];
            for ($i = 0; $i < count($fragments); $i++) {
                $tmp[] = $this->getVariableUsages($fragments[$i]);
            }
            $usages = call_user_func_array('array_merge', $tmp);
            $this->_recursiveVariableUsages[$operation] = $usages;
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
        return $this->_typeInfo->getType();
    }

    /**
     * @return CompositeType
     */
    function getParentType()
    {
        return $this->_typeInfo->getParentType();
    }

    /**
     * @return InputType
     */
    function getInputType()
    {
        return $this->_typeInfo->getInputType();
    }

    /**
     * @return FieldDefinition
     */
    function getFieldDef()
    {
        return $this->_typeInfo->getFieldDef();
    }

    function getDirective()
    {
        return $this->_typeInfo->getDirective();
    }

    function getArgument()
    {
        return $this->_typeInfo->getArgument();
    }
}
