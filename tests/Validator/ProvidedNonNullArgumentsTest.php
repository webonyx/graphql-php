<?php
namespace GraphQL\Validator;

use GraphQL\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\ProvidedNonNullArguments;

class ProvidedNonNullArgumentsTest extends TestCase
{
    // Validate: Provided required arguments
    public function testIgnoresUnknownArguments()
    {
        // ignores unknown arguments
        $this->expectPassesRule(new ProvidedNonNullArguments, '
      {
        dog {
          isHousetrained(unknownArgument: true)
        }
      }
        ');
    }

    // Valid non-nullable value:
    public function testArgOnOptionalArg()
    {
        $this->expectPassesRule(new ProvidedNonNullArguments, '
        {
          dog {
            isHousetrained(atOtherHomes: true)
          }
        }
        ');
    }

    public function testMultipleArgs()
    {
        $this->expectPassesRule(new ProvidedNonNullArguments, '
        {
          complicatedArgs {
            multipleReqs(req1: 1, req2: 2)
          }
        }
        ');
    }

    public function testMultipleArgsReverseOrder()
    {
        $this->expectPassesRule(new ProvidedNonNullArguments, '
        {
          complicatedArgs {
            multipleReqs(req2: 2, req1: 1)
          }
        }
        ');
    }

    public function testNoArgsOnMultipleOptional()
    {
        $this->expectPassesRule(new ProvidedNonNullArguments, '
        {
          complicatedArgs {
            multipleOpts
          }
        }
        ');
    }

    public function testOneArgOnMultipleOptional()
    {
        $this->expectPassesRule(new ProvidedNonNullArguments, '
        {
          complicatedArgs {
            multipleOpts(opt1: 1)
          }
        }
        ');
    }

    public function testSecondArgOnMultipleOptional()
    {
        $this->expectPassesRule(new ProvidedNonNullArguments, '
        {
          complicatedArgs {
            multipleOpts(opt2: 1)
          }
        }
        ');
    }

    public function testMultipleReqsOnMixedList()
    {
        $this->expectPassesRule(new ProvidedNonNullArguments, '
        {
          complicatedArgs {
            multipleOptAndReq(req1: 3, req2: 4)
          }
        }
        ');
    }

    public function testMultipleReqsAndOneOptOnMixedList()
    {
        $this->expectPassesRule(new ProvidedNonNullArguments, '
        {
          complicatedArgs {
            multipleOptAndReq(req1: 3, req2: 4, opt1: 5)
          }
        }
        ');
    }

    public function testAllReqsAndOptsOnMixedList()
    {
        $this->expectPassesRule(new ProvidedNonNullArguments, '
        {
          complicatedArgs {
            multipleOptAndReq(req1: 3, req2: 4, opt1: 5, opt2: 6)
          }
        }
        ');
    }

    // Invalid non-nullable value
    public function testMissingOneNonNullableArgument()
    {
        $this->expectFailsRule(new ProvidedNonNullArguments, '
        {
          complicatedArgs {
            multipleReqs(req2: 2)
          }
        }
        ', [
            $this->missingFieldArg('multipleReqs', 'req1', 'Int!', 4, 13)
        ]);
    }

    public function testMissingMultipleNonNullableArguments()
    {
        $this->expectFailsRule(new ProvidedNonNullArguments, '
        {
          complicatedArgs {
            multipleReqs
          }
        }
        ', [
            $this->missingFieldArg('multipleReqs', 'req1', 'Int!', 4, 13),
            $this->missingFieldArg('multipleReqs', 'req2', 'Int!', 4, 13),
        ]);
    }

    public function testIncorrectValueAndMissingArgument()
    {
        $this->expectFailsRule(new ProvidedNonNullArguments, '
        {
          complicatedArgs {
            multipleReqs(req1: "one")
          }
        }
        ', [
            $this->missingFieldArg('multipleReqs', 'req2', 'Int!', 4, 13),
        ]);
    }

    // Directive arguments
    public function testIgnoresUnknownDirectives()
    {
        $this->expectPassesRule(new ProvidedNonNullArguments, '
        {
          dog @unknown
        }
        ');
    }

    public function testWithDirectivesOfValidTypes()
    {
        $this->expectPassesRule(new ProvidedNonNullArguments, '
        {
          dog @include(if: true) {
            name
          }
          human @skip(if: false) {
            name
          }
        }
        ');
    }

    public function testWithDirectiveWithMissingTypes()
    {
        $this->expectFailsRule(new ProvidedNonNullArguments, '
        {
          dog @include {
            name @skip
          }
        }
        ', [
            $this->missingDirectiveArg('include', 'if', 'Boolean!', 3, 15),
            $this->missingDirectiveArg('skip', 'if', 'Boolean!', 4, 18)
        ]);
    }

    private function missingFieldArg($fieldName, $argName, $typeName, $line, $column)
    {
        return FormattedError::create(
            ProvidedNonNullArguments::missingFieldArgMessage($fieldName, $argName, $typeName),
            [new SourceLocation($line, $column)]
        );
    }

    private function missingDirectiveArg($directiveName, $argName, $typeName, $line, $column)
    {
        return FormattedError::create(
            ProvidedNonNullArguments::missingDirectiveArgMessage($directiveName, $argName, $typeName),
            [new SourceLocation($line, $column)]
        );
    }
}
