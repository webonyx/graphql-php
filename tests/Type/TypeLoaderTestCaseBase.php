<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use GraphQL\Error\InvariantViolation;
use GraphQL\Tests\TestCaseBase;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

abstract class TypeLoaderTestCaseBase extends TestCaseBase
{
    use ArraySubsetAsserts;

    protected ObjectType $query;

    protected ObjectType $mutation;

    /** @var callable(string): ((Type&NamedType)|null) */
    protected $typeLoader;

    /** @var array<int, string> */
    protected array $calls;

    protected function setUp(): void
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
            'typeLoader' => static fn () => null,
        ]);
        $this->assertDidNotCrash();
    }

    public function testSchemaRejectsNonCallableTypeLoader(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessageMatches('/callable.*, array given/');

        // @phpstan-ignore-next-line intentionally wrong
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
        self::assertSame(['Node'], $this->calls);

        $schema->getType('Node');
        self::assertSame(['Node'], $this->calls);
    }

    public function testIgnoresNonExistentType(): void
    {
        $schema = new Schema([
            'query' => $this->query,
            'typeLoader' => static fn () => null,
        ]);

        self::assertNull($schema->getType('NonExistingType'));
    }

    public function testFailsOnNonType(): void
    {
        $notType = new \stdClass();

        // @phpstan-ignore-next-line intentionally wrong
        $schema = new Schema([
            'query' => $this->query,
            'typeLoader' => static fn (): \stdClass => $notType,
        ]);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(Schema::typeLoaderNotType($notType));

        $schema->getType('Node');
    }
}
