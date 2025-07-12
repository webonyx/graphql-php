<?php declare(strict_types=1);

namespace GraphQL\Validator;

use GraphQL\GraphQL;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Schema;

/**
 * Implement this interface and pass an instance to GraphQL::executeQuery to cache validation of ASTs. The details
 * of how to compute any keys (or whether to validate at all) are left up to you.
 */
interface ValidationCache
{
    /**
     * Return true if the given schema + AST pair has previously been validated successfully.
     * Only successful validations are cached. A return value of false means the pair is either unknown or has not been validated yet.
     */
    public function isValidated(Schema $schema, DocumentNode $ast): bool;

    /**
     * Cache validation status for this schema/query.
     */
    public function markValidated(Schema $schema, DocumentNode $ast): void;
}