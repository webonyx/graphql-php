<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Validator\Rules\LoneAnonymousOperation;

/**
 * @phpstan-import-type ErrorArray from ErrorHelper
 */
class LoneAnonymousOperationTest extends ValidatorTestCase
{
    // Validate: Anonymous operation must be alone

    /**
     * @see it('no operations')
     */
    public function testNoOperations(): void
    {
        $this->expectPassesRule(
            new LoneAnonymousOperation(),
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
            new LoneAnonymousOperation(),
            '
      {
        field
      }
        '
        );
    }

    /**
     * @see it('multiple named operations')
     */
    public function testMultipleNamedOperations(): void
    {
        $this->expectPassesRule(
            new LoneAnonymousOperation(),
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
     * @see it('anon operation with fragment')
     */
    public function testAnonOperationWithFragment(): void
    {
        $this->expectPassesRule(
            new LoneAnonymousOperation(),
            '
      {
        ...Foo
      }
      fragment Foo on Type {
        field
      }
        '
        );
    }

    /**
     * @see it('multiple anon operations')
     */
    public function testMultipleAnonOperations(): void
    {
        $this->expectFailsRule(
            new LoneAnonymousOperation(),
            '
      {
        fieldA
      }
      {
        fieldB
      }
        ',
            [
                $this->anonNotAlone(2, 7),
                $this->anonNotAlone(5, 7),
            ]
        );
    }

    /**
     * @phpstan-return ErrorArray
     */
    private function anonNotAlone(int $line, int $column): array
    {
        return ErrorHelper::create(
            LoneAnonymousOperation::anonOperationNotAloneMessage(),
            [new SourceLocation($line, $column)]
        );
    }

    /**
     * @see it('anon operation with a mutation')
     */
    public function testAnonOperationWithMutation(): void
    {
        $this->expectFailsRule(
            new LoneAnonymousOperation(),
            '
      {
        fieldA
      }
      mutation Foo {
        fieldB
      }
        ',
            [
                $this->anonNotAlone(2, 7),
            ]
        );
    }

    /**
     * @see it('anon operation with a subscription')
     */
    public function testAnonOperationWithSubscription(): void
    {
        $this->expectFailsRule(
            new LoneAnonymousOperation(),
            '
      {
        fieldA
      }
      subscription Foo {
        fieldB
      }
        ',
            [
                $this->anonNotAlone(2, 7),
            ]
        );
    }
}
