<?php

declare(strict_types=1);

namespace GraphQL\Tests\Executor;

use GraphQL\Executor\Values;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;
use function count;
use function var_export;
use const PHP_EOL;

class ValuesTest extends TestCase
{
    /** @var Schema */
    private static $schema;

    public function testGetIDVariableValues() : void
    {
        $this->expectInputVariablesMatchOutputVariables(['idInput' => '123456789']);
        $this->assertEquals(
            ['errors' => [], 'coerced' => ['idInput' => '123456789']],
            $this->runTestCase(['idInput' => 123456789]),
            'Integer ID was not converted to string'
        );
    }

    private function expectInputVariablesMatchOutputVariables($variables) : void
    {
        $this->assertEquals(
            $variables,
            $this->runTestCase($variables)['coerced'],
            'Output variables did not match input variables' . PHP_EOL . var_export($variables, true) . PHP_EOL
        );
    }

    /**
     * @param mixed[] $variables
     * @return mixed[]
     */
    private function runTestCase($variables) : array
    {
        return Values::getVariableValues(self::getSchema(), self::getVariableDefinitionNodes(), $variables);
    }

    private static function getSchema() : Schema
    {
        if (! self::$schema) {
            self::$schema = new Schema([
                'query' => new ObjectType([
                    'name'   => 'Query',
                    'fields' => [
                        'test' => [
                            'type' => Type::boolean(),
                            'args' => [
                                'idInput'     => Type::id(),
                                'boolInput'   => Type::boolean(),
                                'intInput'    => Type::int(),
                                'stringInput' => Type::string(),
                                'floatInput'  => Type::float(),
                            ],
                        ],
                    ],
                ]),
            ]);
        }

        return self::$schema;
    }

    /**
     * @return VariableDefinitionNode[]
     */
    private static function getVariableDefinitionNodes() : array
    {
        $idInputDefinition    = new VariableDefinitionNode([
            'variable' => new VariableNode(['name' => new NameNode(['value' => 'idInput'])]),
            'type'     => new NamedTypeNode(['name' => new NameNode(['value' => 'ID'])]),
        ]);
        $boolInputDefinition  = new VariableDefinitionNode([
            'variable' => new VariableNode(['name' => new NameNode(['value' => 'boolInput'])]),
            'type'     => new NamedTypeNode(['name' => new NameNode(['value' => 'Boolean'])]),
        ]);
        $intInputDefinition   = new VariableDefinitionNode([
            'variable' => new VariableNode(['name' => new NameNode(['value' => 'intInput'])]),
            'type'     => new NamedTypeNode(['name' => new NameNode(['value' => 'Int'])]),
        ]);
        $stringInputDefintion = new VariableDefinitionNode([
            'variable' => new VariableNode(['name' => new NameNode(['value' => 'stringInput'])]),
            'type'     => new NamedTypeNode(['name' => new NameNode(['value' => 'String'])]),
        ]);
        $floatInputDefinition = new VariableDefinitionNode([
            'variable' => new VariableNode(['name' => new NameNode(['value' => 'floatInput'])]),
            'type'     => new NamedTypeNode(['name' => new NameNode(['value' => 'Float'])]),
        ]);

        return [$idInputDefinition, $boolInputDefinition, $intInputDefinition, $stringInputDefintion, $floatInputDefinition];
    }

    public function testGetBooleanVariableValues() : void
    {
        $this->expectInputVariablesMatchOutputVariables(['boolInput' => true]);
        $this->expectInputVariablesMatchOutputVariables(['boolInput' => false]);
    }

    public function testGetIntVariableValues() : void
    {
        $this->expectInputVariablesMatchOutputVariables(['intInput' => -1]);
        $this->expectInputVariablesMatchOutputVariables(['intInput' => 0]);
        $this->expectInputVariablesMatchOutputVariables(['intInput' => 1]);

        // Test the int size limit
        $this->expectInputVariablesMatchOutputVariables(['intInput' => 2147483647]);
        $this->expectInputVariablesMatchOutputVariables(['intInput' => -2147483648]);
    }

    public function testGetStringVariableValues() : void
    {
        $this->expectInputVariablesMatchOutputVariables(['stringInput' => 'meow']);
        $this->expectInputVariablesMatchOutputVariables(['stringInput' => '']);
        $this->expectInputVariablesMatchOutputVariables(['stringInput' => '1']);
        $this->expectInputVariablesMatchOutputVariables(['stringInput' => '0']);
        $this->expectInputVariablesMatchOutputVariables(['stringInput' => 'false']);
        $this->expectInputVariablesMatchOutputVariables(['stringInput' => '1.2']);
    }

    public function testGetFloatVariableValues() : void
    {
        $this->expectInputVariablesMatchOutputVariables(['floatInput' => 1.2]);
        $this->expectInputVariablesMatchOutputVariables(['floatInput' => 1.0]);
        $this->expectInputVariablesMatchOutputVariables(['floatInput' => 1]);
        $this->expectInputVariablesMatchOutputVariables(['floatInput' => 0]);
        $this->expectInputVariablesMatchOutputVariables(['floatInput' => 1e3]);
    }

    public function testBooleanForIDVariableThrowsError() : void
    {
        $this->expectGraphQLError(['idInput' => true]);
    }

    private function expectGraphQLError($variables) : void
    {
        $result = $this->runTestCase($variables);
        $this->assertGreaterThan(0, count($result['errors']));
    }

    public function testFloatForIDVariableThrowsError() : void
    {
        $this->expectGraphQLError(['idInput' => 1.0]);
    }

    /**
     * Helpers for running test cases and making assertions
     */
    public function testStringForBooleanVariableThrowsError() : void
    {
        $this->expectGraphQLError(['boolInput' => 'true']);
    }

    public function testIntForBooleanVariableThrowsError() : void
    {
        $this->expectGraphQLError(['boolInput' => 1]);
    }

    public function testFloatForBooleanVariableThrowsError() : void
    {
        $this->expectGraphQLError(['boolInput' => 1.0]);
    }

    public function testStringForIntVariableThrowsError() : void
    {
        $this->expectGraphQLError(['intInput' => 'true']);
    }

    public function testPositiveBigIntForIntVariableThrowsError() : void
    {
        $this->expectGraphQLError(['intInput' => 2147483648]);
    }

    public function testNegativeBigIntForIntVariableThrowsError() : void
    {
        $this->expectGraphQLError(['intInput' => -2147483649]);
    }
}
