<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\UniqueOperationNames;

class UniqueOperationNamesTest extends TestCase
{
    // Validate: Unique operation names

    /**
     * @it no operations
     */
    public function testNoOperations()
    {
        $this->expectPassesRule(new UniqueOperationNames(), '
      fragment fragA on Type {
        field
      }
        ');
    }

    /**
     * @it one anon operation
     */
    public function testOneAnonOperation()
    {
        $this->expectPassesRule(new UniqueOperationNames, '
      {
        field
      }
        ');
    }

    /**
     * @it one named operation
     */
    public function testOneNamedOperation()
    {
        $this->expectPassesRule(new UniqueOperationNames, '
      query Foo {
        field
      }
        ');
    }

    /**
     * @it multiple operations
     */
    public function testMultipleOperations()
    {
        $this->expectPassesRule(new UniqueOperationNames, '
      query Foo {
        field
      }

      query Bar {
        field
      }
        ');
    }

    /**
     * @it multiple operations of different types
     */
    public function testMultipleOperationsOfDifferentTypes()
    {
        $this->expectPassesRule(new UniqueOperationNames, '
      query Foo {
        field
      }

      mutation Bar {
        field
      }

      subscription Baz {
        field
      }
        ');
    }

    /**
     * @it fragment and operation named the same
     */
    public function testFragmentAndOperationNamedTheSame()
    {
        $this->expectPassesRule(new UniqueOperationNames, '
      query Foo {
        ...Foo
      }
      fragment Foo on Type {
        field
      }
        ');
    }

    /**
     * @it multiple operations of same name
     */
    public function testMultipleOperationsOfSameName()
    {
        $this->expectFailsRule(new UniqueOperationNames, '
      query Foo {
        fieldA
      }
      query Foo {
        fieldB
      }
        ', [
            $this->duplicateOp('Foo', 2, 13, 5, 13)
        ]);
    }

    /**
     * @it multiple ops of same name of different types (mutation)
     */
    public function testMultipleOpsOfSameNameOfDifferentTypes_Mutation()
    {
        $this->expectFailsRule(new UniqueOperationNames, '
      query Foo {
        fieldA
      }
      mutation Foo {
        fieldB
      }
        ', [
            $this->duplicateOp('Foo', 2, 13, 5, 16)
        ]);
    }

    /**
     * @it multiple ops of same name of different types (subscription)
     */
    public function testMultipleOpsOfSameNameOfDifferentTypes_Subscription()
    {
        $this->expectFailsRule(new UniqueOperationNames, '
      query Foo {
        fieldA
      }
      subscription Foo {
        fieldB
      }
        ', [
            $this->duplicateOp('Foo', 2, 13, 5, 20)
        ]);
    }

    private function duplicateOp($opName, $l1, $c1, $l2, $c2)
    {
        return FormattedError::create(
            UniqueOperationNames::duplicateOperationNameMessage($opName),
            [new SourceLocation($l1, $c1), new SourceLocation($l2, $c2)]
        );
    }
}
