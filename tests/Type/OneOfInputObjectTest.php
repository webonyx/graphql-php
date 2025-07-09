<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\GraphQL;
use GraphQL\Tests\TestCaseBase;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\Value;

final class OneOfInputObjectTest extends TestCaseBase
{
    public function testOneOfInputObjectBasicDefinition(): void
    {
        $oneOfInput = new InputObjectType([
            'name' => 'OneOfInput',
            'isOneOf' => true,
            'fields' => [
                'stringField' => Type::string(),
                'intField' => Type::int(),
            ],
        ]);

        self::assertTrue($oneOfInput->isOneOf());
        self::assertCount(2, $oneOfInput->getFields());
    }

    public function testOneOfInputObjectValidation(): void
    {
        $oneOfInput = new InputObjectType([
            'name' => 'OneOfInput',
            'isOneOf' => true,
            'fields' => [
                'stringField' => Type::string(),
                'intField' => Type::int(),
            ],
        ]);

        // Should not throw for valid oneOf input
        $oneOfInput->assertValid();
        $this->assertDidNotCrash();
    }

    public function testOneOfInputObjectRejectsNonNullFields(): void
    {
        $oneOfInput = new InputObjectType([
            'name' => 'OneOfInput',
            'isOneOf' => true,
            'fields' => [
                'stringField' => Type::nonNull(Type::string()), // This should fail
                'intField' => Type::int(),
            ],
        ]);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('OneOf input object type OneOfInput field stringField must be nullable');

        $oneOfInput->assertValid();
    }

    public function testOneOfInputObjectRejectsDefaultValues(): void
    {
        $oneOfInput = new InputObjectType([
            'name' => 'OneOfInput',
            'isOneOf' => true,
            'fields' => [
                'stringField' => [
                    'type' => Type::string(),
                    'defaultValue' => 'default', // This should fail
                ],
                'intField' => Type::int(),
            ],
        ]);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('OneOf input object type OneOfInput field stringField cannot have a default value');

        $oneOfInput->assertValid();
    }

    public function testOneOfInputObjectRequiresAtLeastOneField(): void
    {
        $oneOfInput = new InputObjectType([
            'name' => 'OneOfInput',
            'isOneOf' => true,
            'fields' => [], // Empty fields array should fail
        ]);

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('OneOf input object type OneOfInput must define one or more fields');

        $oneOfInput->assertValid();
    }

    public function testOneOfInputObjectSchemaValidation(): void
    {
        $oneOfInput = new InputObjectType([
            'name' => 'OneOfInput',
            'isOneOf' => true,
            'fields' => [
                'stringField' => Type::string(),
                'intField' => Type::int(),
            ],
        ]);

        $query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'test' => [
                    'type' => Type::string(),
                    'args' => [
                        'input' => $oneOfInput,
                    ],
                    'resolve' => static fn () => 'test',
                ],
            ],
        ]);

        $schema = new Schema([
            'query' => $query,
        ]);

        // Valid query with exactly one field
        $validQuery = '{ test(input: { stringField: "hello" }) }';
        $result = GraphQL::executeQuery($schema, $validQuery);
        self::assertEmpty($result->errors);

        // Invalid query with multiple fields
        $invalidQuery = '{ test(input: { stringField: "hello", intField: 42 }) }';
        $result = GraphQL::executeQuery($schema, $invalidQuery);
        self::assertNotEmpty($result->errors);
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('must specify exactly one field', $result->errors[0]->getMessage());

        // Invalid query with no fields
        $emptyQuery = '{ test(input: {}) }';
        $result = GraphQL::executeQuery($schema, $emptyQuery);
        self::assertNotEmpty($result->errors);
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('must specify exactly one field', $result->errors[0]->getMessage());

        // Invalid query with null field value
        $nullQuery = '{ test(input: { stringField: null }) }';
        $result = GraphQL::executeQuery($schema, $nullQuery);
        self::assertNotEmpty($result->errors);
        self::assertCount(1, $result->errors);
        self::assertStringContainsString('must be non-null', $result->errors[0]->getMessage());
    }

    public function testOneOfIntrospection(): void
    {
        $oneOfInput = new InputObjectType([
            'name' => 'OneOfInput',
            'isOneOf' => true,
            'fields' => [
                'stringField' => Type::string(),
                'intField' => Type::int(),
            ],
        ]);

        $regularInput = new InputObjectType([
            'name' => 'RegularInput',
            'fields' => [
                'stringField' => Type::string(),
                'intField' => Type::int(),
            ],
        ]);

        $query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'test' => [
                    'type' => Type::string(),
                    'resolve' => static fn () => 'test',
                ],
            ],
        ]);

        $schema = new Schema([
            'query' => $query,
            'types' => [$oneOfInput, $regularInput],
        ]);

        $introspectionQuery = '
            {
                __schema {
                    types {
                        name
                        isOneOf
                    }
                }
            }
        ';

        $result = GraphQL::executeQuery($schema, $introspectionQuery);
        self::assertEmpty($result->errors);

        $types = $result->data['__schema']['types'] ?? [];
        $oneOfType = null;
        $regularType = null;

        foreach ($types as $type) {
            if ($type['name'] === 'OneOfInput') {
                $oneOfType = $type;
            } elseif ($type['name'] === 'RegularInput') {
                $regularType = $type;
            }
        }

        self::assertNotNull($oneOfType);
        self::assertNotNull($regularType);
        self::assertTrue($oneOfType['isOneOf']);
        self::assertFalse($regularType['isOneOf']); // Should be false for regular input objects
    }

    public function testOneOfCoercionValidation(): void
    {
        $oneOfType = new InputObjectType([
            'name' => 'OneOfInput',
            'fields' => [
                'stringField' => Type::string(),
                'intField' => Type::int(),
            ],
            'isOneOf' => true,
        ]);

        // Test valid input (exactly one field)
        $validResult = Value::coerceInputValue(['stringField' => 'test'], $oneOfType);
        self::assertNull($validResult['errors']);
        self::assertEquals(['stringField' => 'test'], $validResult['value']);

        // Test invalid input (no fields)
        $noFieldsResult = Value::coerceInputValue([], $oneOfType);
        self::assertNotNull($noFieldsResult['errors']);
        self::assertCount(1, $noFieldsResult['errors']);
        self::assertEquals('OneOf input object "OneOfInput" must specify exactly one field.', $noFieldsResult['errors'][0]->getMessage());

        // Test invalid input (multiple fields)
        $multipleFieldsResult = Value::coerceInputValue(['stringField' => 'test', 'intField' => 42], $oneOfType);
        self::assertNotNull($multipleFieldsResult['errors']);
        self::assertCount(1, $multipleFieldsResult['errors']);
        self::assertEquals('OneOf input object "OneOfInput" must specify exactly one field.', $multipleFieldsResult['errors'][0]->getMessage());

        // Test invalid input (null field value)
        $nullFieldResult = Value::coerceInputValue(['stringField' => null], $oneOfType);
        self::assertNotNull($nullFieldResult['errors']);
        self::assertCount(1, $nullFieldResult['errors']);
        self::assertEquals('OneOf input object "OneOfInput" field "stringField" must be non-null.', $nullFieldResult['errors'][0]->getMessage());
    }
}
