<?php declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
use GraphQL\Validator\ValidationCache;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\String\Exception\InvalidArgumentException;

class PsrValidationCacheAdapter implements ValidationCache
{
    private const KEY_PREFIX = 'gql_validation_';

    private int $ttl;

    private CacheInterface $cache;

    public function __construct(
        CacheInterface $cache,
        int $ttlSeconds = 300
    ) {
        $this->ttl = $ttlSeconds;
        $this->cache = $cache;
    }

    /** @throws InvalidArgumentException */
    public function isValidated(Schema $schema, DocumentNode $ast): bool
    {
        try {
            $key = $this->buildKey($schema, $ast);

            /** @phpstan-ignore missingType.checkedException */
            return $this->cache->has($key);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @throws InvalidArgumentException */
    public function markValidated(Schema $schema, DocumentNode $ast): void
    {
        try {
            $key = $this->buildKey($schema, $ast);
            /** @phpstan-ignore missingType.checkedException */
            $this->cache->set($key, true, $this->ttl);
        } catch (\Throwable $e) {
            // ignore silently
        }
    }

    /**
     * @throws \GraphQL\Error\Error
     * @throws \GraphQL\Error\InvariantViolation
     * @throws \GraphQL\Error\SerializationError
     * @throws \JsonException
     */
    private function buildKey(Schema $schema, DocumentNode $ast): string
    {
        /**
         * NOTE: This default strategy generates a cache key by hashing the printed schema and AST.
         * You'll likely want to replace this with a more stable or efficient method for fingerprinting
         * the schema -- for example, a build-time hash, schema version number, or an environment-based identifier.
         */
        $schemaHash = md5(SchemaPrinter::doPrint($schema));
        $astHash = md5(serialize($ast));

        return self::KEY_PREFIX . $schemaHash . '_' . $astHash;
    }
}
