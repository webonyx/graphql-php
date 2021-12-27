<?php

declare(strict_types=1);

namespace GraphQL\Tests\Type;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Error\InvariantViolation;
use GraphQL\Tests\TestCaseBase;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use stdClass;
use TypeError;

abstract class TypeLoaderTest extends TestCaseBase
{
    use ArraySubsetAsserts;

    protected ObjectType $query;

    protected ObjectType $mutation;

    /** @var callable(string): ?Type */
    protected $typeLoader;

    /** @var array<int, string> */
    protected array $calls;

    public function setUp(): void
    {
        $this->calls = [];
    }

    public function testSchemaAcceptsTypeLoader(): void
    {
        new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => ['a' => Type::string()],
            ]),
            'typeLoader' => static function (): void {
            },
        ]);
        self::assertDidNotCrash();
    }

    public function testSchemaRejectsNonCallableTypeLoader(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessageMatches('/callable.*, array given/');

        new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => ['a' => Type::string()],
            ]),
            'typeLoader' => [],
        ]);
    }

    public function testOnlyCallsLoaderOnce(): void
    {
        $schema = new Schema([
            'query' => $this->query,
            'typeLoader' => $this->typeLoader,
        ]);

        $schema->getType('Node');
        self::assertEquals(['Node'], $this->calls);

        $schema->getType('Node');
        self::assertEquals(['Node'], $this->calls);
    }

    public function testIgnoresNonExistentType(): void
    {
        $schema = new Schema([
            'query' => $this->query,
            'typeLoader' => static function (): void {
            },
        ]);

        self::assertNull($schema->getType('NonExistingType'));
    }

    public function testFailsOnNonType(): void
    {
        $notType = new stdClass();

        $schema = new Schema([
            'query' => $this->query,
            'typeLoader' => static fn (): stdClass => $notType,
        ]);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(Schema::typeLoaderNotType($notType));

        $schema->getType('Node');
    }
}
