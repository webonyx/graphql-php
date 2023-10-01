<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Type\Schema;
use GraphQL\Validator\Rules\UniqueDirectivesPerLocation;

/**
 * @phpstan-import-type ErrorArray from ErrorHelper
 */
final class UniqueDirectivesPerLocationTest extends ValidatorTestCase
{
    /**
     * @param array<int, array<string, mixed>> $errors
     *
     * @throws \Exception
     */
    private function expectSDLErrors(string $sdlString, Schema $schema = null, array $errors = []): void
    {
        $this->expectSDLErrorsFromRule(new UniqueDirectivesPerLocation(), $sdlString, $schema, $errors);
    }

    /** @see it('no directives') */
    public function testNoDirectives(): void
    {
        $this->expectPassesRule(
            new UniqueDirectivesPerLocation(),
            '
      fragment Test on Type {
        field
      }
        '
        );
    }

    /** @see it('unique directives in different locations') */
    public function testUniqueDirectivesInDifferentLocations(): void
    {
        $this->expectPassesRule(
            new UniqueDirectivesPerLocation(),
            '
      fragment Test on Type @directiveA {
        field @directiveB
      }
        '
        );
    }

    /** @see it('unique directives in same locations') */
    public function testUniqueDirectivesInSameLocations(): void
    {
        $this->expectPassesRule(
            new UniqueDirectivesPerLocation(),
            '
      fragment Test on Type @directiveA @directiveB {
        field @directiveA @directiveB
      }
        '
        );
    }

    /** @see it('same directives in different locations') */
    public function testSameDirectivesInDifferentLocations(): void
    {
        $this->expectPassesRule(
            new UniqueDirectivesPerLocation(),
            '
      fragment Test on Type @directiveA {
        field @directiveA
      }
        '
        );
    }

    /** @see it('same directives in similar locations') */
    public function testSameDirectivesInSimilarLocations(): void
    {
        $this->expectPassesRule(
            new UniqueDirectivesPerLocation(),
            '
      fragment Test on Type {
        field @directive
        field @directive
      }
        '
        );
    }

    /** @see it('repeatable directives in same location', () => { */
    public function testRepeatableDirectivesInSameLocation(): void
    {
        $this->expectPassesRule(
            new UniqueDirectivesPerLocation(),
            '
      fragment Test on Type @repeatable @repeatable {
        field @repeatable @repeatable
      }
        '
        );
    }

    /** @see it('unknown directives must be ignored', () => { */
    public function testUnknownDirectivesMustBeIgnored(): void
    {
        $this->expectPassesRule(
            new UniqueDirectivesPerLocation(),
            '
	      type Test @unknown @unknown {
            field: String! @unknown @unknown
          }
          extend type Test @unknown {
            anotherField: String!
          }
        '
        );
    }

    /** @see it('duplicate directives in one location') */
    public function testDuplicateDirectivesInOneLocation(): void
    {
        $this->expectFailsRule(
            new UniqueDirectivesPerLocation(),
            '
      fragment Test on Type {
        field @directive @directive
      }
        ',
            [$this->duplicateDirective('directive', 3, 15, 3, 26)]
        );
    }

    /** @phpstan-return ErrorArray */
    private function duplicateDirective(string $directiveName, int $l1, int $c1, int $l2, int $c2)
    {
        return ErrorHelper::create(
            UniqueDirectivesPerLocation::duplicateDirectiveMessage($directiveName),
            [new SourceLocation($l1, $c1), new SourceLocation($l2, $c2)],
        );
    }

    /** @see it('many duplicate directives in one location') */
    public function testManyDuplicateDirectivesInOneLocation(): void
    {
        $this->expectFailsRule(
            new UniqueDirectivesPerLocation(),
            '
      fragment Test on Type {
        field @directive @directive @directive
      }
        ',
            [
                $this->duplicateDirective('directive', 3, 15, 3, 26),
                $this->duplicateDirective('directive', 3, 15, 3, 37),
            ]
        );
    }

    /** @see it('different duplicate directives in one location') */
    public function testDifferentDuplicateDirectivesInOneLocation(): void
    {
        $this->expectFailsRule(
            new UniqueDirectivesPerLocation(),
            '
      fragment Test on Type {
        field @directiveA @directiveB @directiveA @directiveB
      }
        ',
            [
                $this->duplicateDirective('directiveA', 3, 15, 3, 39),
                $this->duplicateDirective('directiveB', 3, 27, 3, 51),
            ]
        );
    }

    /** @see it('duplicate directives in many locations') */
    public function testDuplicateDirectivesInManyLocations(): void
    {
        $this->expectFailsRule(
            new UniqueDirectivesPerLocation(),
            '
      fragment Test on Type @directive @directive {
        field @directive @directive
      }
        ',
            [
                $this->duplicateDirective('directive', 2, 29, 2, 40),
                $this->duplicateDirective('directive', 3, 15, 3, 26),
            ]
        );
    }

    /** @see it('duplicate directives on SDL definitions') */
    public function testDuplicateDirectivesOnSDLDefinitions(): void
    {
        $this->expectSDLErrors(
            '
      directive @nonRepeatable on
        SCHEMA | SCALAR | OBJECT | INTERFACE | UNION | INPUT_OBJECT

      schema @nonRepeatable @nonRepeatable { query: Dummy }

      scalar TestScalar @nonRepeatable @nonRepeatable
      type TestObject @nonRepeatable @nonRepeatable
      interface TestInterface @nonRepeatable @nonRepeatable
      union TestUnion @nonRepeatable @nonRepeatable
      input TestInput @nonRepeatable @nonRepeatable
    ',
            null,
            [
                $this->duplicateDirective('nonRepeatable', 5, 14, 5, 29),
                $this->duplicateDirective('nonRepeatable', 7, 25, 7, 40),
                $this->duplicateDirective('nonRepeatable', 8, 23, 8, 38),
                $this->duplicateDirective('nonRepeatable', 9, 31, 9, 46),
                $this->duplicateDirective('nonRepeatable', 10, 23, 10, 38),
                $this->duplicateDirective('nonRepeatable', 11, 23, 11, 38),
            ]
        );
    }
}
