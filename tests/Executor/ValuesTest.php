<?php declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\Values;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

final class ValuesTest extends TestCase
{
    private static Schema $schema;

    public function testCollectsInputObjectParseValueErrorsFromMultipleVariableDefaults(): void
    {
        $safeError = new Error('Default rejected.', null, null, [], null, null, ['code' => 'DEFAULT_INVALID']);
        $unsafeError = new \TypeError('Private default detail.');
        $input = new InputObjectType([
            'name' => 'TestInput',
            'fields' => ['value' => Type::int()],
            'parseValue' => static function (array $value) use ($safeError, $unsafeError): void {
                if ($value['value'] === 1) {
                    throw $safeError;
                }

                throw $unsafeError;
            },
        ]);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'test' => [
                        'type' => Type::boolean(),
                        'args' => ['first' => $input, 'second' => $input],
                    ],
                ],
            ]),
        ]);
        $document = Parser::parse(<<<'GRAPHQL'
            query(
              $first: TestInput = {value: 1}
              $second: TestInput = {value: 2}
            ) {
              test(first: $first, second: $second)
            }
            GRAPHQL);
        $operation = $document->definitions[0];
        assert($operation instanceof OperationDefinitionNode);

        [$errors, $values] = Values::getVariableValues($schema, $operation->variableDefinitions, []);

        self::assertNull($values);
        self::assertNotNull($errors);
        self::assertCount(2, $errors);
        self::assertSame([$operation->variableDefinitions[0]], $errors[0]->getNodes());
        self::assertSame([$operation->variableDefinitions[1]], $errors[1]->getNodes());
        self::assertSame($safeError, $errors[0]->getPrevious());
        self::assertSame($unsafeError, $errors[1]->getPrevious());
        self::assertTrue($errors[0]->isClientSafe());
        self::assertFalse($errors[1]->isClientSafe());
        self::assertSame([
            'message' => 'Default rejected.',
            'locations' => [['line' => 2, 'column' => 3]],
            'extensions' => ['code' => 'DEFAULT_INVALID'],
        ], FormattedError::createFromException($errors[0]));
        self::assertSame([
            'message' => 'Internal server error',
            'locations' => [['line' => 3, 'column' => 3]],
        ], FormattedError::createFromException($errors[1]));
    }

    public function testGetIDVariableValues(): void
    {
        $this->expectInputVariablesMatchOutputVariables(['idInput' => '123456789']);
        self::assertEquals(
            [null, ['idInput' => '123456789']],
            $this->runTestCase(['idInput' => 123456789]),
            'Integer ID was not converted to string'
        );
    }

    /**
     * @param array<string, mixed> $variables
     *
     * @throws \Exception
     * @throws InvariantViolation
     */
    private function expectInputVariablesMatchOutputVariables(array $variables): void
    {
        self::assertEquals(
            $variables,
            $this->runTestCase($variables)[1],
            'Output variables did not match input variables' . "\n" . var_export($variables, true) . "\n"
        );
    }

    /**
     * @param array<string, mixed> $variables
     *
     * @throws \Exception
     * @throws InvariantViolation
     *
     * @return array{array<int, Error>, null}|array{null, array<string, mixed>}
     */
    private function runTestCase(array $variables): array
    {
        return Values::getVariableValues(self::getSchema(), self::getVariableDefinitionNodes(), $variables);
    }

    /** @throws InvariantViolation */
    private static function getSchema(): Schema
    {
        return self::$schema ??= new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'test' => [
                        'type' => Type::boolean(),
                        'args' => [
                            'idInput' => Type::id(),
                            'boolInput' => Type::boolean(),
                            'intInput' => Type::int(),
                            'stringInput' => Type::string(),
                            'floatInput' => Type::float(),
                        ],
                    ],
                ],
            ]),
        ]);
    }

    /** @return NodeList<VariableDefinitionNode> */
    private static function getVariableDefinitionNodes(): NodeList
    {
        $idInputDefinition = new VariableDefinitionNode([
            'variable' => new VariableNode(['name' => new NameNode(['value' => 'idInput'])]),
            'type' => new NamedTypeNode(['name' => new NameNode(['value' => 'ID'])]),
        ]);
        $boolInputDefinition = new VariableDefinitionNode([
            'variable' => new VariableNode(['name' => new NameNode(['value' => 'boolInput'])]),
            'type' => new NamedTypeNode(['name' => new NameNode(['value' => 'Boolean'])]),
        ]);
        $intInputDefinition = new VariableDefinitionNode([
            'variable' => new VariableNode(['name' => new NameNode(['value' => 'intInput'])]),
            'type' => new NamedTypeNode(['name' => new NameNode(['value' => 'Int'])]),
        ]);
        $stringInputDefinition = new VariableDefinitionNode([
            'variable' => new VariableNode(['name' => new NameNode(['value' => 'stringInput'])]),
            'type' => new NamedTypeNode(['name' => new NameNode(['value' => 'String'])]),
        ]);
        $floatInputDefinition = new VariableDefinitionNode([
            'variable' => new VariableNode(['name' => new NameNode(['value' => 'floatInput'])]),
            'type' => new NamedTypeNode(['name' => new NameNode(['value' => 'Float'])]),
        ]);

        return new NodeList([$idInputDefinition, $boolInputDefinition, $intInputDefinition, $stringInputDefinition, $floatInputDefinition]);
    }

    public function testGetBooleanVariableValues(): void
    {
        $this->expectInputVariablesMatchOutputVariables(['boolInput' => true]);
        $this->expectInputVariablesMatchOutputVariables(['boolInput' => false]);
    }

    public function testGetIntVariableValues(): void
    {
        $this->expectInputVariablesMatchOutputVariables(['intInput' => -1]);
        $this->expectInputVariablesMatchOutputVariables(['intInput' => 0]);
        $this->expectInputVariablesMatchOutputVariables(['intInput' => 1]);

        // Test the int size limit
        $this->expectInputVariablesMatchOutputVariables(['intInput' => 2147483647]);
        $this->expectInputVariablesMatchOutputVariables(['intInput' => -2147483648]);
    }

    public function testGetStringVariableValues(): void
    {
        $this->expectInputVariablesMatchOutputVariables(['stringInput' => 'meow']);
        $this->expectInputVariablesMatchOutputVariables(['stringInput' => '']);
        $this->expectInputVariablesMatchOutputVariables(['stringInput' => '1']);
        $this->expectInputVariablesMatchOutputVariables(['stringInput' => '0']);
        $this->expectInputVariablesMatchOutputVariables(['stringInput' => 'false']);
        $this->expectInputVariablesMatchOutputVariables(['stringInput' => '1.2']);
    }

    public function testGetFloatVariableValues(): void
    {
        $this->expectInputVariablesMatchOutputVariables(['floatInput' => 1.2]);
        $this->expectInputVariablesMatchOutputVariables(['floatInput' => 1.0]);
        $this->expectInputVariablesMatchOutputVariables(['floatInput' => 1]);
        $this->expectInputVariablesMatchOutputVariables(['floatInput' => 0]);
        $this->expectInputVariablesMatchOutputVariables(['floatInput' => 1e3]);
    }

    public function testBooleanForIDVariableThrowsError(): void
    {
        $this->expectGraphQLError(['idInput' => true]);
    }

    /**
     * @param array<string, mixed> $variables
     *
     * @throws \Exception
     * @throws InvariantViolation
     */
    private function expectGraphQLError(array $variables): void
    {
        $result = $this->runTestCase($variables);
        self::assertNotNull($result[0]);
        self::assertGreaterThan(0, count($result[0]));
    }

    public function testFloatForIDVariableThrowsError(): void
    {
        $this->expectGraphQLError(['idInput' => 1.0]);
    }

    /** Helpers for running test cases and making assertions. */
    public function testStringForBooleanVariableThrowsError(): void
    {
        $this->expectGraphQLError(['boolInput' => 'true']);
    }

    public function testIntForBooleanVariableThrowsError(): void
    {
        $this->expectGraphQLError(['boolInput' => 1]);
    }

    public function testFloatForBooleanVariableThrowsError(): void
    {
        $this->expectGraphQLError(['boolInput' => 1.0]);
    }

    public function testStringForIntVariableThrowsError(): void
    {
        $this->expectGraphQLError(['intInput' => 'true']);
    }

    public function testPositiveBigIntForIntVariableThrowsError(): void
    {
        $this->expectGraphQLError(['intInput' => 2147483648]);
    }

    public function testNegativeBigIntForIntVariableThrowsError(): void
    {
        $this->expectGraphQLError(['intInput' => -2147483649]);
    }
}
