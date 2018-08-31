<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\KnownArgumentNames;

class KnownArgumentNamesTest extends ValidatorTestCase
{
    // Validate: Known argument names:

    /**
     * @see it('single arg is known')
     */
    public function testSingleArgIsKnown() : void
    {
        $this->expectPassesRule(new KnownArgumentNames, '
      fragment argOnRequiredArg on Dog {
        doesKnowCommand(dogCommand: SIT)
      }
        ');
    }

    /**
     * @see it('multiple args are known')
     */
    public function testMultipleArgsAreKnown() : void
    {
        $this->expectPassesRule(new KnownArgumentNames, '
      fragment multipleArgs on ComplicatedArgs {
        multipleReqs(req1: 1, req2: 2)
      }
        ');
    }

    /**
     * @see it('ignores args of unknown fields')
     */
    public function testIgnoresArgsOfUnknownFields() : void
    {
        $this->expectPassesRule(new KnownArgumentNames, '
      fragment argOnUnknownField on Dog {
        unknownField(unknownArg: SIT)
      }
        ');
    }

    /**
     * @see it('multiple args in reverse order are known')
     */
    public function testMultipleArgsInReverseOrderAreKnown() : void
    {
        $this->expectPassesRule(new KnownArgumentNames, '
      fragment multipleArgsReverseOrder on ComplicatedArgs {
        multipleReqs(req2: 2, req1: 1)
      }
        ');
    }

    /**
     * @see it('no args on optional arg')
     */
    public function testNoArgsOnOptionalArg() : void
    {
        $this->expectPassesRule(new KnownArgumentNames, '
      fragment noArgOnOptionalArg on Dog {
        isHousetrained
      }
        ');
    }

    /**
     * @see it('args are known deeply')
     */
    public function testArgsAreKnownDeeply() : void
    {
        $this->expectPassesRule(new KnownArgumentNames, '
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
        ');
    }

    /**
     * @see it('directive args are known')
     */
    public function testDirectiveArgsAreKnown() : void
    {
        $this->expectPassesRule(new KnownArgumentNames, '
      {
        dog @skip(if: true)
      }
        ');
    }

    /**
     * @see it('undirective args are invalid')
     */
    public function testUndirectiveArgsAreInvalid() : void
    {
        $this->expectFailsRule(new KnownArgumentNames, '
      {
        dog @skip(unless: true)
      }
        ', [
            $this->unknownDirectiveArg('unless', 'skip', [], 3, 19),
        ]);
    }

    /**
     * @see it('misspelled directive args are reported')
     */
    public function testMisspelledDirectiveArgsAreReported() : void
    {
        $this->expectFailsRule(new KnownArgumentNames, '
      {
        dog @skip(iff: true)
      }
        ', [
            $this->unknownDirectiveArg('iff', 'skip', ['if'], 3, 19),
        ]);
    }

    /**
     * @see it('invalid arg name')
     */
    public function testInvalidArgName() : void
    {
        $this->expectFailsRule(new KnownArgumentNames, '
      fragment invalidArgName on Dog {
        doesKnowCommand(unknown: true)
      }
        ', [
            $this->unknownArg('unknown', 'doesKnowCommand', 'Dog', [],3, 25),
        ]);
    }

    /**
     * @see it('misspelled arg name is reported')
     */
    public function testMisspelledArgNameIsReported() : void
    {
        $this->expectFailsRule(new KnownArgumentNames, '
      fragment invalidArgName on Dog {
        doesKnowCommand(dogcommand: true)
      }
        ', [
            $this->unknownArg('dogcommand', 'doesKnowCommand', 'Dog', ['dogCommand'],3, 25),
        ]);
    }

    /**
     * @see it('unknown args amongst known args')
     */
    public function testUnknownArgsAmongstKnownArgs() : void
    {
        $this->expectFailsRule(new KnownArgumentNames, '
      fragment oneGoodArgOneInvalidArg on Dog {
        doesKnowCommand(whoknows: 1, dogCommand: SIT, unknown: true)
      }
        ', [
            $this->unknownArg('whoknows', 'doesKnowCommand', 'Dog', [], 3, 25),
            $this->unknownArg('unknown', 'doesKnowCommand', 'Dog', [], 3, 55),
        ]);
    }

    /**
     * @see it('unknown args deeply')
     */
    public function testUnknownArgsDeeply() : void
    {
        $this->expectFailsRule(new KnownArgumentNames, '
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
        ', [
            $this->unknownArg('unknown', 'doesKnowCommand', 'Dog', [], 4, 27),
            $this->unknownArg('unknown', 'doesKnowCommand', 'Dog', [], 9, 31),
        ]);
    }

    private function unknownArg($argName, $fieldName, $typeName, $suggestedArgs, $line, $column)
    {
        return FormattedError::create(
            KnownArgumentNames::unknownArgMessage($argName, $fieldName, $typeName, $suggestedArgs),
            [new SourceLocation($line, $column)]
        );
    }

    private function unknownDirectiveArg($argName, $directiveName, $suggestedArgs, $line, $column)
    {
        return FormattedError::create(
            KnownArgumentNames::unknownDirectiveArgMessage($argName, $directiveName, $suggestedArgs),
            [new SourceLocation($line, $column)]
        );
    }
}
