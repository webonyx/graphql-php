<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\UniqueInputFieldNames;

class UniqueInputFieldNamesTest extends TestCase
{
    // Validate: Unique input field names

    /**
     * @it input object with fields
     */
    public function testInputObjectWithFields()
    {
        $this->expectPassesRule(new UniqueInputFieldNames(), '
      {
        field(arg: { f: true })
      }
        ');
    }

    /**
     * @it same input object within two args
     */
    public function testSameInputObjectWithinTwoArgs()
    {
        $this->expectPassesRule(new UniqueInputFieldNames, '
      {
        field(arg1: { f: true }, arg2: { f: true })
      }
        ');
    }

    /**
     * @it multiple input object fields
     */
    public function testMultipleInputObjectFields()
    {
        $this->expectPassesRule(new UniqueInputFieldNames, '
      {
        field(arg: { f1: "value", f2: "value", f3: "value" })
      }
        ');
    }

    /**
     * @it allows for nested input objects with similar fields
     */
    public function testAllowsForNestedInputObjectsWithSimilarFields()
    {
        $this->expectPassesRule(new UniqueInputFieldNames, '
      {
        field(arg: {
          deep: {
            deep: {
              id: 1
            }
            id: 1
          }
          id: 1
        })
      }
        ');
    }

    /**
     * @it duplicate input object fields
     */
    public function testDuplicateInputObjectFields()
    {
        $this->expectFailsRule(new UniqueInputFieldNames, '
      {
        field(arg: { f1: "value", f1: "value" })
      }
        ', [
            $this->duplicateField('f1', 3, 22, 3, 35)
        ]);
    }

    /**
     * @it many duplicate input object fields
     */
    public function testManyDuplicateInputObjectFields()
    {
        $this->expectFailsRule(new UniqueInputFieldNames, '
      {
        field(arg: { f1: "value", f1: "value", f1: "value" })
      }
        ', [
            $this->duplicateField('f1', 3, 22, 3, 35),
            $this->duplicateField('f1', 3, 22, 3, 48)
        ]);
    }

    private function duplicateField($name, $l1, $c1, $l2, $c2)
    {
        return FormattedError::create(
            UniqueInputFieldNames::duplicateInputFieldMessage($name),
            [new SourceLocation($l1, $c1), new SourceLocation($l2, $c2)]
        );
    }
}
