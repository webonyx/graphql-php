<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Validator\Rules\UniqueOperationNames;

class UniqueOperationNamesTest extends ValidatorTestCase
{
    // Validate: Unique operation names

    /**
     * @see it('no operations')
     */
    public function testNoOperations(): void
    {
        $this->expectPassesRule(
            new UniqueOperationNames(),
            '
      fragment fragA on Type {
        field
      }
        '
        );
    }

    /**
     * @see it('one anon operation')
     */
    public function testOneAnonOperation(): void
    {
        $this->expectPassesRule(
            new UniqueOperationNames(),
            '
      {
        field
      }
        '
        );
    }

    /**
     * @see it('one named operation')
     */
    public function testOneNamedOperation(): void
    {
        $this->expectPassesRule(
            new UniqueOperationNames(),
            '
      query Foo {
        field
      }
        '
        );
    }

    /**
     * @see it('multiple operations')
     */
    public function testMultipleOperations(): void
    {
        $this->expectPassesRule(
            new UniqueOperationNames(),
            '
      query Foo {
        field
      }

      query Bar {
        field
      }
        '
        );
    }

    /**
     * @see it('multiple operations of different types')
     */
    public function testMultipleOperationsOfDifferentTypes(): void
    {
        $this->expectPassesRule(
            new UniqueOperationNames(),
            '
      query Foo {
        field
      }

      mutation Bar {
        field
      }

      subscription Baz {
        field
      }
        '
        );
    }

    /**
     * @see it('fragment and operation named the same')
     */
    public function testFragmentAndOperationNamedTheSame(): void
    {
        $this->expectPassesRule(
            new UniqueOperationNames(),
            '
      query Foo {
        ...Foo
      }
      fragment Foo on Type {
        field
      }
        '
        );
    }

    /**
     * @see it('multiple operations of same name')
     */
    public function testMultipleOperationsOfSameName(): void
    {
        $this->expectFailsRule(
            new UniqueOperationNames(),
            '
      query Foo {
        fieldA
      }
      query Foo {
        fieldB
      }
        ',
            [$this->duplicateOp('Foo', 2, 13, 5, 13)]
        );
    }

    private function duplicateOp($opName, $l1, $c1, $l2, $c2)
    {
        return ErrorHelper::create(
            UniqueOperationNames::duplicateOperationNameMessage($opName),
            [new SourceLocation($l1, $c1), new SourceLocation($l2, $c2)]
        );
    }

    /**
     * @see it('multiple ops of same name of different types (mutation)')
     */
    public function testMultipleOpsOfSameNameOfDifferentTypesMutation(): void
    {
        $this->expectFailsRule(
            new UniqueOperationNames(),
            '
      query Foo {
        fieldA
      }
      mutation Foo {
        fieldB
      }
        ',
            [$this->duplicateOp('Foo', 2, 13, 5, 16)]
        );
    }

    /**
     * @see it('multiple ops of same name of different types (subscription)')
     */
    public function testMultipleOpsOfSameNameOfDifferentTypesSubscription(): void
    {
        $this->expectFailsRule(
            new UniqueOperationNames(),
            '
      query Foo {
        fieldA
      }
      subscription Foo {
        fieldB
      }
        ',
            [$this->duplicateOp('Foo', 2, 13, 5, 20)]
        );
    }
}
