<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\KnownArgumentNames;

class KnownArgumentNamesTest extends TestCase
{
    // Validate: Known argument names:

    /**
     * @it single arg is known
     */
    public function testSingleArgIsKnown()
    {
        $this->expectPassesRule(new KnownArgumentNames, '
      fragment argOnRequiredArg on Dog {
        doesKnowCommand(dogCommand: SIT)
      }
        ');
    }

    /**
     * @it multiple args are known
     */
    public function testMultipleArgsAreKnown()
    {
        $this->expectPassesRule(new KnownArgumentNames, '
      fragment multipleArgs on ComplicatedArgs {
        multipleReqs(req1: 1, req2: 2)
      }
        ');
    }

    /**
     * @it ignores args of unknown fields
     */
    public function testIgnoresArgsOfUnknownFields()
    {
        $this->expectPassesRule(new KnownArgumentNames, '
      fragment argOnUnknownField on Dog {
        unknownField(unknownArg: SIT)
      }
        ');
    }

    /**
     * @it multiple args in reverse order are known
     */
    public function testMultipleArgsInReverseOrderAreKnown()
    {
        $this->expectPassesRule(new KnownArgumentNames, '
      fragment multipleArgsReverseOrder on ComplicatedArgs {
        multipleReqs(req2: 2, req1: 1)
      }
        ');
    }

    /**
     * @it no args on optional arg
     */
    public function testNoArgsOnOptionalArg()
    {
        $this->expectPassesRule(new KnownArgumentNames, '
      fragment noArgOnOptionalArg on Dog {
        isHousetrained
      }
        ');
    }

    /**
     * @it args are known deeply
     */
    public function testArgsAreKnownDeeply()
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
     * @it directive args are known
     */
    public function testDirectiveArgsAreKnown()
    {
        $this->expectPassesRule(new KnownArgumentNames, '
      {
        dog @skip(if: true)
      }
        ');
    }

    /**
     * @it undirective args are invalid
     */
    public function testUndirectiveArgsAreInvalid()
    {
        $this->expectFailsRule(new KnownArgumentNames, '
      {
        dog @skip(unless: true)
      }
        ', [
            $this->unknownDirectiveArg('unless', 'skip', 3, 19),
        ]);
    }

    /**
     * @it invalid arg name
     */
    public function testInvalidArgName()
    {
        $this->expectFailsRule(new KnownArgumentNames, '
      fragment invalidArgName on Dog {
        doesKnowCommand(unknown: true)
      }
        ', [
            $this->unknownArg('unknown', 'doesKnowCommand', 'Dog', 3, 25),
        ]);
    }

    /**
     * @it unknown args amongst known args
     */
    public function testUnknownArgsAmongstKnownArgs()
    {
        $this->expectFailsRule(new KnownArgumentNames, '
      fragment oneGoodArgOneInvalidArg on Dog {
        doesKnowCommand(whoknows: 1, dogCommand: SIT, unknown: true)
      }
        ', [
            $this->unknownArg('whoknows', 'doesKnowCommand', 'Dog', 3, 25),
            $this->unknownArg('unknown', 'doesKnowCommand', 'Dog', 3, 55),
        ]);
    }

    /**
     * @it unknown args deeply
     */
    public function testUnknownArgsDeeply()
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
            $this->unknownArg('unknown', 'doesKnowCommand', 'Dog', 4, 27),
            $this->unknownArg('unknown', 'doesKnowCommand', 'Dog', 9, 31),
        ]);
    }

    private function unknownArg($argName, $fieldName, $typeName, $line, $column)
    {
        return FormattedError::create(
            KnownArgumentNames::unknownArgMessage($argName, $fieldName, $typeName),
            [new SourceLocation($line, $column)]
        );
    }

    private function unknownDirectiveArg($argName, $directiveName, $line, $column)
    {
        return FormattedError::create(
            KnownArgumentNames::unknownDirectiveArgMessage($argName, $directiveName),
            [new SourceLocation($line, $column)]
        );
    }
}
