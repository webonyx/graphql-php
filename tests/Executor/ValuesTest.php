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

class ValuesTest extends \PHPUnit_Framework_TestCase {

  public function testGetIDVariableValues()
  {
      $this->expectInputVariablesMatchOutputVariables(['idInput' => '123456789']);
      $this->assertEquals(
          ['idInput' => '123456789'],
          self::runTestCase(['idInput' => 123456789]),
          'Integer ID was not converted to string'
      );
  }

  public function testGetBooleanVariableValues()
  {
      $this->expectInputVariablesMatchOutputVariables(['boolInput' => true]);
      $this->expectInputVariablesMatchOutputVariables(['boolInput' => false]);
  }

  public function testGetIntVariableValues()
  {
      $this->expectInputVariablesMatchOutputVariables(['intInput' => -1]);
      $this->expectInputVariablesMatchOutputVariables(['intInput' => 0]);
      $this->expectInputVariablesMatchOutputVariables(['intInput' => 1]);

      // Test the int size limit
      $this->expectInputVariablesMatchOutputVariables(['intInput' => 2147483647]);
      $this->expectInputVariablesMatchOutputVariables(['intInput' => -2147483648]);
  }

  public function testGetStringVariableValues()
  {
      $this->expectInputVariablesMatchOutputVariables(['stringInput' => 'meow']);
      $this->expectInputVariablesMatchOutputVariables(['stringInput' => '']);
      $this->expectInputVariablesMatchOutputVariables(['stringInput' => '1']);
      $this->expectInputVariablesMatchOutputVariables(['stringInput' => '0']);
      $this->expectInputVariablesMatchOutputVariables(['stringInput' => 'false']);
      $this->expectInputVariablesMatchOutputVariables(['stringInput' => '1.2']);
  }

  public function testGetFloatVariableValues()
  {
      $this->expectInputVariablesMatchOutputVariables(['floatInput' => 1.2]);
      $this->expectInputVariablesMatchOutputVariables(['floatInput' => 1.0]);
      $this->expectInputVariablesMatchOutputVariables(['floatInput' => 1]);
      $this->expectInputVariablesMatchOutputVariables(['floatInput' => 0]);
      $this->expectInputVariablesMatchOutputVariables(['floatInput' => 1e3]);
  }

  public function testBooleanForIDVariableThrowsError()
  {
      $this->expectGraphQLError(['idInput' => true]);
  }

  public function testFloatForIDVariableThrowsError()
  {
      $this->expectGraphQLError(['idInput' => 1.0]);
  }

  public function testStringForBooleanVariableThrowsError()
  {
      $this->expectGraphQLError(['boolInput' => 'true']);
  }

  public function testIntForBooleanVariableThrowsError()
  {
      $this->expectGraphQLError(['boolInput' => 1]);
  }

  public function testFloatForBooleanVariableThrowsError()
  {
      $this->expectGraphQLError(['boolInput' => 1.0]);
  }

  public function testBooleanForIntVariableThrowsError()
  {
      $this->expectGraphQLError(['intInput' => true]);
  }

  public function testStringForIntVariableThrowsError()
  {
      $this->expectGraphQLError(['intInput' => 'true']);
  }

  public function testFloatForIntVariableThrowsError()
  {
      $this->expectGraphQLError(['intInput' => 1.0]);
  }

  public function testPositiveBigIntForIntVariableThrowsError()
  {
      $this->expectGraphQLError(['intInput' => 2147483648]);
  }

  public function testNegativeBigIntForIntVariableThrowsError()
  {
      $this->expectGraphQLError(['intInput' => -2147483649]);
  }

  public function testBooleanForStringVariableThrowsError()
  {
      $this->expectGraphQLError(['stringInput' => true]);
  }

  public function testIntForStringVariableThrowsError()
  {
      $this->expectGraphQLError(['stringInput' => 1]);
  }

  public function testFloatForStringVariableThrowsError()
  {
      $this->expectGraphQLError(['stringInput' => 1.0]);
  }

  public function testBooleanForFloatVariableThrowsError()
  {
      $this->expectGraphQLError(['floatInput' => true]);
  }

  public function testStringForFloatVariableThrowsError()
  {
      $this->expectGraphQLError(['floatInput' => '1.0']);
  }

  // Helpers for running test cases and making assertions

  private function expectInputVariablesMatchOutputVariables($variables)
  {
      $this->assertEquals(
          $variables,
          self::runTestCase($variables),
          'Output variables did not match input variables' . PHP_EOL . var_export($variables, true) . PHP_EOL
      );
  }

  private function expectGraphQLError($variables)
  {
      $this->setExpectedException(\GraphQL\Error\Error::class);
      self::runTestCase($variables);
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