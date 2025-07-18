<?php declare(strict_types=1);

namespace GraphQL\Validator;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Tests\PsrValidationCacheAdapter;
use GraphQL\Type\Schema;
use GraphQL\Validator\Rules\ValidationRule;

/**
 * Implement this interface and pass an instance to GraphQL::executeQuery to enable caching of successful query validations.
 *
 * This can improve performance by skipping validation for known-good query, schema, and rules combinations.
 * You are responsible for defining how cache keys are computed.
 *
 * @see PsrValidationCacheAdapter for a toy implementation.
 */
interface ValidationCache
{
    /**
     * Determine whether the given schema/AST/rules set has already been successfully validated.
     *
     * This method should return true if the query has previously passed validation for the provided schema.
     * Only successful validations should be considered "cached" — failed validations are not cached.
     *
     * @param array<ValidationRule>|null $rules
     *
     * @return bool true if validation for the given schema + AST + rules is already known to be valid; false otherwise
     */
    public function isValidated(Schema $schema, DocumentNode $ast, ?array $rules = null): bool;

    /**
     * @param array<ValidationRule>|null $rules
     *
     * Mark the given schema/AST/rules set as successfully validated.
     *
     * This is typically called after a query passes validation.
     * You should store enough information to recognize this combination on future requests.
     */
    public function markValidated(Schema $schema, DocumentNode $ast, ?array $rules = null): void;
}
