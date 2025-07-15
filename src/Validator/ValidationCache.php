<?php declare(strict_types=1);

namespace GraphQL\Validator;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Tests\PsrValidationCacheAdapter;
use GraphQL\Type\Schema;

/**
 * Implement this interface and pass an instance to GraphQL::executeQuery to enable caching of successful query validations.
 *
 * This can improve performance by skipping validation for known-good query and schema combinations.
 * You are responsible for defining how cache keys are computed, and when validation should be skipped.
 *
 * @see PsrValidationCacheAdapter for a toy implementation.
 */
interface ValidationCache
{
    /**
     * Determine whether the given schema + AST pair has already been successfully validated.
     *
     * This method should return true if the query has previously passed validation for the provided schema.
     * Only successful validations should be considered "cached" — failed validations are not cached.
     *
     * Note: This allows for optimizations in systems where validation may not be necessary on every request —
     * for example, when using persisted queries that are known to be valid ahead of time. In such cases, you
     * can implement this method to always return true.
     *
     * @return bool True if validation for the given schema + AST is already known to be valid; false otherwise.
     */
    public function isValidated(Schema $schema, DocumentNode $ast): bool;

    /**
     * Mark the given schema + AST pair as successfully validated.
     *
     * This is typically called after a query passes validation.
     * You should store enough information to recognize this combination on future requests.
     */
    public function markValidated(Schema $schema, DocumentNode $ast): void;
}