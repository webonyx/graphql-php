<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\Tests\TestCaseBase;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

/**
 * Additional tests that ensure the proper usage of lazy type loading in the schema.
 */
final class LazyValidationTest extends TestCaseBase
{
    public function testRejectsDifferentQueryInstance(): void
    {
        $typeLoader = static fn (): ObjectType => new ObjectType([
            'name' => 'Query',
            'fields' => [
                'test' => Type::string(),
            ],
        ]);

        $schema = new Schema([
            'query' => $typeLoader(),
            'typeLoader' => $typeLoader,
        ]);

        $this->expectExceptionObject(new InvariantViolation(
            'Type loader returns different instance for Query than field/argument definitions. Make sure you always return the same instance for the same type name.'
        ));
        $schema->assertValid();
    }

    public function testRejectsDifferentFieldTypeInstance(): void
    {
        $typeLoader = static function (string $name) use (&$query): ?ObjectType {
            if ($name === 'Query') {
                return $query;
            }

            return new ObjectType([
                'name' => 'Test',
                'fields' => [
                    'test' => Type::string(),
                ],
            ]);
        };

        $query = new ObjectType([
            'name' => 'Query',
            'fields' => static fn (): array => [
                'test' => $typeLoader('Test'),
            ],
        ]);

        $schema = new Schema([
            'query' => $query,
            'typeLoader' => $typeLoader,
        ]);

        $this->expectExceptionObject(new InvariantViolation(
            'Found duplicate type in schema at Query.test: Test. Ensure the type loader returns the same instance. See https://webonyx.github.io/graphql-php/type-definitions/#type-registry.'
        ));
        $schema->assertValid();
    }
}
