<?php declare(strict_types=1);

namespace GraphQL\Validator;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Tests\PsrValidationCacheAdapter;
use GraphQL\Type\Schema;
use GraphQL\Validator\Rules\ValidationRule;

/**
 * Implement this interface and pass an instance to GraphQL::executeQuery to enable caching of successful query validations.
 *
 * This can improve performance by skipping validation for known-good combinations of query, schema, and rules.
 * You are responsible for defining how cache keys are computed.
 *
 * Some things to keep in mind when generating keys:
 * - PHP's `serialize()` function is fast, but can't handle certain structures such as closures.
 * - If your `$schema` includes closures or is too large or complex to serialize,
 *   consider using a build-time version number or environment-based fingerprint instead.
 * - Keep in mind that there are internal `$rules` that are applied in addition to any you pass in,
 *   and it's possible these may shift or expand as the library evolves,
 *   so it might make sense to include the library version number in your keys.
 *
 * @see PsrValidationCacheAdapter for a simple reference implementation.
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
