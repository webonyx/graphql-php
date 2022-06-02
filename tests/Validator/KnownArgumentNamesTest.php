<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Utils\BuildSchema;
use GraphQL\Validator\Rules\KnownArgumentNames;
use GraphQL\Validator\Rules\KnownArgumentNamesOnDirectives;

/**
 * @phpstan-import-type ErrorArray from ErrorHelper
 */
class KnownArgumentNamesTest extends ValidatorTestCase
{
    // Validate: Known argument names:

    /**
     * @see it('single arg is known')
     */
    public function testSingleArgIsKnown(): void
    {
        $this->expectPassesRule(
            new KnownArgumentNames(),
            '
      fragment argOnRequiredArg on Dog {
        doesKnowCommand(dogCommand: SIT)
      }
        '
        );
    }

    /**
     * @see it('multiple args are known')
     */
    public function testMultipleArgsAreKnown(): void
    {
        $this->expectPassesRule(
            new KnownArgumentNames(),
            '
      fragment multipleArgs on ComplicatedArgs {
        multipleReqs(req1: 1, req2: 2)
      }
        '
        );
    }

    /**
     * @see it('ignores args of unknown fields')
     */
    public function testIgnoresArgsOfUnknownFields(): void
    {
        $this->expectPassesRule(
            new KnownArgumentNames(),
            '
      fragment argOnUnknownField on Dog {
        unknownField(unknownArg: SIT)
      }
        '
        );
    }

    /**
     * @see it('multiple args in reverse order are known')
     */
    public function testMultipleArgsInReverseOrderAreKnown(): void
    {
        $this->expectPassesRule(
            new KnownArgumentNames(),
            '
      fragment multipleArgsReverseOrder on ComplicatedArgs {
        multipleReqs(req2: 2, req1: 1)
      }
        '
        );
    }

    /**
     * @see it('no args on optional arg')
     */
    public function testNoArgsOnOptionalArg(): void
    {
        $this->expectPassesRule(
            new KnownArgumentNames(),
            '
      fragment noArgOnOptionalArg on Dog {
        isHousetrained
      }
        '
        );
    }

    /**
     * @see it('args are known deeply')
     */
    public function testArgsAreKnownDeeply(): void
    {
        $this->expectPassesRule(
            new KnownArgumentNames(),
            '
      {
        dog {
          doesKnowCommand(dogCommand: SIT)
        }
        human {
          pet {
            ... on Dog {
              doesKnowCommand(dogCommand: SIT)
            }
          }
        }
      }
        '
        );
    }

    /**
     * @see it('directive args are known')
     */
    public function testDirectiveArgsAreKnown(): void
    {
        $this->expectPassesRule(
            new KnownArgumentNames(),
            '
      {
        dog @skip(if: true)
      }
        '
        );
    }

    /**
     * @see it('undirective args are invalid')
     */
    public function testUndirectiveArgsAreInvalid(): void
    {
        $this->expectFailsRule(
            new KnownArgumentNames(),
            '
      {
        dog @skip(unless: true)
      }
        ',
            [
                $this->unknownDirectiveArg('unless', 'skip', [], 3, 19),
            ]
        );
    }

    /**
     * @param array<string> $suggestedArgs
     *
     * @phpstan-return ErrorArray
     */
    private function unknownDirectiveArg(string $argName, string $directiveName, array $suggestedArgs, int $line, int $column): array
    {
        return ErrorHelper::create(
            KnownArgumentNamesOnDirectives::unknownDirectiveArgMessage($argName, $directiveName, $suggestedArgs),
            [new SourceLocation($line, $column)]
        );
    }

    /**
     * @see it('misspelled directive args are reported')
     */
    public function testMisspelledDirectiveArgsAreReported(): void
    {
        $this->expectFailsRule(
            new KnownArgumentNames(),
            '
      {
        dog @skip(iff: true)
      }
        ',
            [
                $this->unknownDirectiveArg('iff', 'skip', ['if'], 3, 19),
            ]
        );
    }

    /**
     * @see it('invalid arg name')
     */
    public function testInvalidArgName(): void
    {
        $this->expectFailsRule(
            new KnownArgumentNames(),
            '
      fragment invalidArgName on Dog {
        doesKnowCommand(unknown: true)
      }
        ',
            [
                $this->unknownArg('unknown', 'doesKnowCommand', 'Dog', [], 3, 25),
            ]
        );
    }

    /**
     * @param array<string> $suggestedArgs
     *
     * @phpstan-return ErrorArray
     */
    private function unknownArg(string $argName, string $fieldName, string $typeName, array $suggestedArgs, int $line, int $column): array
    {
        return ErrorHelper::create(
            KnownArgumentNames::unknownArgMessage($argName, $fieldName, $typeName, $suggestedArgs),
            [new SourceLocation($line, $column)]
        );
    }

    /**
     * @see it('misspelled arg name is reported')
     */
    public function testMisspelledArgNameIsReported(): void
    {
        $this->expectFailsRule(
            new KnownArgumentNames(),
            '
      fragment invalidArgName on Dog {
        doesKnowCommand(dogcommand: true)
      }
        ',
            [
                $this->unknownArg('dogcommand', 'doesKnowCommand', 'Dog', ['dogCommand'], 3, 25),
            ]
        );
    }

    /**
     * @see it('unknown args amongst known args')
     */
    public function testUnknownArgsAmongstKnownArgs(): void
    {
        $this->expectFailsRule(
            new KnownArgumentNames(),
            '
      fragment oneGoodArgOneInvalidArg on Dog {
        doesKnowCommand(whoknows: 1, dogCommand: SIT, unknown: true)
      }
        ',
            [
                $this->unknownArg('whoknows', 'doesKnowCommand', 'Dog', [], 3, 25),
                $this->unknownArg('unknown', 'doesKnowCommand', 'Dog', [], 3, 55),
            ]
        );
    }

    /**
     * @see it('unknown args deeply')
     */
    public function testUnknownArgsDeeply(): void
    {
        $this->expectFailsRule(
            new KnownArgumentNames(),
            '
      {
        dog {
          doesKnowCommand(unknown: true)
        }
        human {
          pet {
            ... on Dog {
              doesKnowCommand(unknown: true)
            }
          }
        }
      }
        ',
            [
                $this->unknownArg('unknown', 'doesKnowCommand', 'Dog', [], 4, 27),
                $this->unknownArg('unknown', 'doesKnowCommand', 'Dog', [], 9, 31),
            ]
        );
    }

    // within SDL:

    /**
     * @see it('known arg on directive defined inside SDL')
     */
    public function testKnownArgOnDirectiveDefinedInsideSDL(): void
    {
        $this->expectPassesRule(
            new KnownArgumentNamesOnDirectives(),
            '
                type Query {
                  foo: String @test(arg: "")
                }
        
                directive @test(arg: String) on FIELD_DEFINITION
            '
        );
    }

    /**
     * @see it('unknown arg on directive defined inside SDL')
     */
    public function testUnknownArgOnDirectiveDefinedInsideSDL(): void
    {
        $this->expectFailsRule(
            new KnownArgumentNamesOnDirectives(),
            '
                type Query {
                  foo: String @test(unknown: "")
                }
        
                directive @test(arg: String) on FIELD_DEFINITION
            ',
            [
                $this->unknownDirectiveArg('unknown', 'test', [], 3, 37),
            ]
        );
    }

    /**
     * @see it('misspelled arg name is reported on directive defined inside SDL')
     */
    public function testMisspelledArgNameIsReportedOnDirectiveDefinedInsideSDL(): void
    {
        $this->expectFailsRule(
            new KnownArgumentNamesOnDirectives(),
            '
                type Query {
                  foo: String @test(agr: "")
                }
        
                directive @test(arg: String) on FIELD_DEFINITION
            ',
            [$this->unknownDirectiveArg('agr', 'test', ['arg'], 3, 37)]
        );
    }

    /**
     * @see it('unknown arg on standard directive')
     */
    public function testUnknownArgOnStandardDirective(): void
    {
        $this->expectFailsRule(
            new KnownArgumentNamesOnDirectives(),
            '
                type Query {
                  foo: String @deprecated(unknown: "")
                }
            ',
            [$this->unknownDirectiveArg('unknown', 'deprecated', [], 3, 43)]
        );
    }

    /**
     * @see it('unknown arg on overrided standard directive')
     */
    public function testUnknownArgOnOverriddenStandardDirective(): void
    {
        $this->expectFailsRule(
            new KnownArgumentNamesOnDirectives(),
            '
                type Query {
                  foo: String @deprecated(reason: "")
                }
                directive @deprecated(arg: String) on FIELD
            ',
            [$this->unknownDirectiveArg('reason', 'deprecated', [], 3, 43)]
        );
    }

    /**
     * @see it('unknown arg on directive defined in schema extension')
     */
    public function testUnknownArgOnDirectiveDefinedInSchemaExtension(): void
    {
        $schema = BuildSchema::build('
            type Query {
              foo: String
            }
        ');
        $sdl = '
            directive @test(arg: String) on OBJECT

            extend type Query  @test(unknown: "")
        ';
        $this->expectInvalid(
            $schema,
            [new KnownArgumentNamesOnDirectives()],
            $sdl,
            [$this->unknownDirectiveArg('unknown', 'test', [], 4, 38)]
        );
    }

    /**
     * @see it('unknown arg on directive used in schema extension')
     */
    public function testUnknownArgOnDirectiveUsedInSchemaExtension(): void
    {
        $schema = BuildSchema::build('
            directive @test(arg: String) on OBJECT
    
            type Query {
              foo: String
            }
        ');
        $sdl = '
            extend type Query @test(unknown: "")
        ';
        $this->expectInvalid(
            $schema,
            [new KnownArgumentNamesOnDirectives()],
            $sdl,
            [$this->unknownDirectiveArg('unknown', 'test', [], 2, 37)]
        );
    }
}
