<?php declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\SerializationError;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
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
     * @throws \JsonException
     * @throws Error
     * @throws InvalidArgumentException&\Throwable
     * @throws InvariantViolation
     * @throws SerializationError
     */
    public function isValidated(Schema $schema, DocumentNode $ast): bool
    {
        $key = $this->buildKey($schema, $ast);

        return $this->cache->has($key); // @phpstan-ignore missingType.checkedException (annotated as a union with Throwable)
    }

    /**
     * @throws \JsonException
     * @throws Error
     * @throws InvalidArgumentException&\Throwable
     * @throws InvariantViolation
     * @throws SerializationError
     */
    public function markValidated(Schema $schema, DocumentNode $ast): void
    {
        $key = $this->buildKey($schema, $ast);

        $this->cache->set($key, true, $this->ttlSeconds); // @phpstan-ignore missingType.checkedException (annotated as a union with Throwable)
    }

    /**
     * @throws \JsonException
     * @throws Error
     * @throws InvariantViolation
     * @throws SerializationError
     */
    private function buildKey(Schema $schema, DocumentNode $ast): string
    {
        /**
         * This default strategy generates a cache key by hashing the printed schema and AST.
         * You'll likely want to replace this with a more stable or efficient method for fingerprinting the schema.
         * For example, you may use a build-time hash, schema version number, or an environment-based identifier.
         */
        $schemaHash = md5(SchemaPrinter::doPrint($schema));
        $astHash = md5(serialize($ast));

        return "graphql_validation_{$schemaHash}_{$astHash}";
    }
}
