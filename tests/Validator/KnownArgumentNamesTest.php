<?php
namespace GraphQL\Validator;

use GraphQL\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\KnownArgumentNames;

class KnownArgumentNamesTest extends TestCase
{
    // Validate: Known argument names:
    public function testSingleArgIsKnown()
    {
        $this->expectPassesRule(new KnownArgumentNames, '
      fragment argOnRequiredArg on Dog {
        doesKnowCommand(dogCommand: SIT)
      }
        ');
    }

    public function testMultipleArgsAreKnown()
    {
        $this->expectPassesRule(new KnownArgumentNames, '
      fragment multipleArgs on ComplicatedArgs {
        multipleReqs(req1: 1, req2: 2)
      }
        ');
    }

    public function testMultipleArgsInReverseOrderAreKnown()
    {
        $this->expectPassesRule(new KnownArgumentNames, '
      fragment multipleArgsReverseOrder on ComplicatedArgs {
        multipleReqs(req2: 2, req1: 1)
      }
        ');
    }

    public function testNoArgsOnOptionalArg()
    {
        $this->expectPassesRule(new KnownArgumentNames, '
      fragment noArgOnOptionalArg on Dog {
        isHousetrained
      }
        ');
    }

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

    public function testArgsMayBeOnObjectButNotInterface()
    {
        $this->expectFailsRule(new KnownArgumentNames, '
      fragment nameSometimesHasArg on Being {
        name(surname: true)
        ... on Human {
          name(surname: true)
        }
        ... on Dog {
          name(surname: true)
        }
      }
        ', [
            $this->unknownArg('surname', 'name', 'Being', 3, 14),
            $this->unknownArg('surname', 'name', 'Dog', 8, 16)
        ]);
    }

    private function unknownArg($argName, $fieldName, $typeName, $line, $column)
    {
        return new FormattedError(
            Messages::unknownArgMessage($argName, $fieldName, $typeName),
            [new SourceLocation($line, $column)]
        );
    }
}
