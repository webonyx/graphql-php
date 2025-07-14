<?php


namespace GraphQL\Tests\Executor\TestClasses;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Tests\PsrValidationCacheAdapter;
use GraphQL\Type\Schema;

final class SpyValidationCacheAdapter extends PsrValidationCacheAdapter
{
    public int $isValidatedCalls = 0;
    public int $markValidatedCalls = 0;

    public function isValidated(Schema $schema, DocumentNode $ast): bool
    {
        $this->isValidatedCalls++;

        return parent::isValidated($schema, $ast);
    }

    public function markValidated(Schema $schema, DocumentNode $ast): void
    {
        $this->markValidatedCalls++;

        parent::markValidated($schema, $ast);
    }
}