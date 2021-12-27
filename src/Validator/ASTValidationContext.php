<?php

declare(strict_types=1);

namespace GraphQL\Validator;

use GraphQL\Error\Error;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Schema;

abstract class ASTValidationContext
{
    protected DocumentNode $ast;

    /** @var array<int, Error> */
    protected array $errors = [];

    protected ?Schema $schema;

    public function __construct(DocumentNode $ast, ?Schema $schema = null)
    {
        $this->ast = $ast;
        $this->schema = $schema;
    }

    public function reportError(Error $error): void
    {
        $this->errors[] = $error;
    }

    /**
     * @return array<int, Error>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getDocument(): DocumentNode
    {
        return $this->ast;
    }

    public function getSchema(): ?Schema
    {
        return $this->schema;
    }
}
