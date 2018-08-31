<?php
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

class ValuesTest extends TestCase
{
    public function testGetIDVariableValues() : void
    {
        $this->expectInputVariablesMatchOutputVariables(['idInput' => '123456789']);
        $this->assertEquals(
            ['errors'=> [], 'coerced' => ['idInput' => '123456789']],
            self::runTestCase(['idInput' => 123456789]),
            'Integer ID was not converted to string'
        );
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

    public function testFloatForIDVariableThrowsError() : void
    {
        $this->expectGraphQLError(['idInput' => 1.0]);
    }

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

    // Helpers for running test cases and making assertions

    private function expectInputVariablesMatchOutputVariables($variables)
    {
        $this->assertEquals(
            $variables,
            self::runTestCase($variables)['coerced'],
            'Output variables did not match input variables' . PHP_EOL . var_export($variables, true) . PHP_EOL
        );
    }

    private function expectGraphQLError($variables)
    {
        $result = self::runTestCase($variables);
        $this->assertGreaterThan(0, count($result['errors']));
    }

    private static $schema;

    private static function getSchema()
    {
        if (!self::$schema) {
            self::$schema = new Schema([
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
                                'floatInput' => Type::float()
                            ]
                        ],
                    ]
                ])
            ]);
        }
        return self::$schema;
    }

    private static function getVariableDefinitionNodes()
    {
        $idInputDefinition = new VariableDefinitionNode([
            'variable' => new VariableNode(['name' => new NameNode(['value' => 'idInput'])]),
            'type' => new NamedTypeNode(['name' => new NameNode(['value' => 'ID'])])
        ]);
        $boolInputDefinition = new VariableDefinitionNode([
            'variable' => new VariableNode(['name' => new NameNode(['value' => 'boolInput'])]),
            'type' => new NamedTypeNode(['name' => new NameNode(['value' => 'Boolean'])])
        ]);
        $intInputDefinition = new VariableDefinitionNode([
            'variable' => new VariableNode(['name' => new NameNode(['value' => 'intInput'])]),
            'type' => new NamedTypeNode(['name' => new NameNode(['value' => 'Int'])])
        ]);
        $stringInputDefintion = new VariableDefinitionNode([
            'variable' => new VariableNode(['name' => new NameNode(['value' => 'stringInput'])]),
            'type' => new NamedTypeNode(['name' => new NameNode(['value' => 'String'])])
        ]);
        $floatInputDefinition = new VariableDefinitionNode([
            'variable' => new VariableNode(['name' => new NameNode(['value' => 'floatInput'])]),
            'type' => new NamedTypeNode(['name' => new NameNode(['value' => 'Float'])])
        ]);
        return [$idInputDefinition, $boolInputDefinition, $intInputDefinition, $stringInputDefintion, $floatInputDefinition];
    }

    private function runTestCase($variables)
    {
        return Values::getVariableValues(self::getSchema(), self::getVariableDefinitionNodes(), $variables);
    }
}
