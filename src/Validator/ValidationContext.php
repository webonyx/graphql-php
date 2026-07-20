<?php declare(strict_types=1);

namespace GraphQL\Validator;

use GraphQL\Error\Error;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Schema;

interface ValidationContext
{
    public function reportError(Error $error): void;

    /** @return list<Error> */
    public function getErrors(): array;

    public function getDocument(): DocumentNode;

    public function getSchema(): ?Schema;
}
