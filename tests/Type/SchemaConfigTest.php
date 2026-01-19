<?php
declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Type\SchemaConfig;
use PHPUnit\Framework\TestCase;

final class SchemaConfigTest extends TestCase
{
    public function testGetDescription(): void
    {
        $config = SchemaConfig::create([
            'description' => 'Sample schema',
        ]);
        self::assertSame('Sample schema', $config->getDescription());
    }
}