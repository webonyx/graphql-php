<?php

declare(strict_types=1);

namespace GraphQL\Validator;

use GraphQL\Error\Error;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Schema;

abstract class ASTValidationContext
{
    /** @var DocumentNode */
    protected $ast;

    /** @var Error[] */
    protected $errors;

    /** @var Schema */
    protected $schema;

    public function __construct(DocumentNode $ast, ?Schema $schema = null)
    {
        $this->ast    = $ast;
        $this->schema = $schema;
        $this->errors = [];
    }

    public function reportError(Error $error)
    {
        $this->errors[] = $error;
    }

    /**
     * @return Error[]
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return DocumentNode
     */
    public function getDocument()
    {
        return $this->ast;
    }

    public function getSchema() : ?Schema
    {
        return $this->schema;
    }
}
