<?php declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\SerializationError;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
use GraphQL\Validator\Rules\ValidationRule;
use GraphQL\Validator\ValidationCache;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class PsrValidationCacheAdapter implements ValidationCache
{
    private CacheInterface $cache;

    private int $ttlSeconds;

    public function __construct(
        CacheInterface $cache,
        int $ttlSeconds = 300
    ) {
        $this->cache = $cache;
        $this->ttlSeconds = $ttlSeconds;
    }

    /**
     * @param array<ValidationRule>|null $rules
     *
     * @throws \JsonException
     * @throws Error
     * @throws InvalidArgumentException&\Throwable
     * @throws InvariantViolation
     * @throws SerializationError
     */
    public function isValidated(Schema $schema, DocumentNode $ast, ?array $rules = null): bool
    {
        $key = $this->buildKey($schema, $ast);

        return $this->cache->has($key); // @phpstan-ignore missingType.checkedException (annotated as a union with Throwable)
    }

    /**
     * @param array<ValidationRule>|null $rules
     *
     * @throws \JsonException
     * @throws Error
     * @throws InvalidArgumentException&\Throwable
     * @throws InvariantViolation
     * @throws SerializationError
     */
    public function markValidated(Schema $schema, DocumentNode $ast, ?array $rules = null): void
    {
        $key = $this->buildKey($schema, $ast);

        $this->cache->set($key, true, $this->ttlSeconds); // @phpstan-ignore missingType.checkedException (annotated as a union with Throwable)
    }

    /**
     * @param array<ValidationRule>|null $rules
     *
     * @throws \JsonException
     * @throws Error
     * @throws InvariantViolation
     * @throws SerializationError
     */
    private function buildKey(Schema $schema, DocumentNode $ast, ?array $rules = null): string
    {
        /**
         * This default strategy generates a cache key by hashing the printed schema, AST, and any custom rules.
         * You'll likely want to replace this with a more stable or efficient method for fingerprinting the schema.
         * For example, you may use a build-time hash, schema version number, or an environment-based identifier.
         */
        $schemaHash = md5(SchemaPrinter::doPrint($schema));
        $astHash = md5(serialize($ast));
        $rulesHash = md5(serialize($rules));

        return "graphql_validation_{$schemaHash}_{$astHash}_{$rulesHash}";
    }
}
