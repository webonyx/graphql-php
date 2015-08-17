<?php
namespace GraphQL\Validator;
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
     * @var array<string, FragmentDefinition>
     */
    private $_fragments;

    function __construct(Schema $schema, Document $ast, TypeInfo $typeInfo)
    {
        $this->_schema = $schema;
        $this->_ast = $ast;
        $this->_typeInfo = $typeInfo;
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
