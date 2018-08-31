<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\ProvidedNonNullArguments;

class ProvidedNonNullArgumentsTest extends ValidatorTestCase
{
    // Validate: Provided required arguments

    /**
     * @see it('ignores unknown arguments')
     */
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

    /**
     * @see it('Arg on optional arg')
     */
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

    /**
     * @see it('No Arg on optional arg')
     */
    public function testNoArgOnOptionalArg()
    {
        $this->expectPassesRule(new ProvidedNonNullArguments, '
        {
          dog {
            isHousetrained
          }
        }
        ');
    }

    /**
     * @see it('Multiple args')
     */
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

    /**
     * @see it('Multiple args reverse order')
     */
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

    /**
     * @see it('No args on multiple optional')
     */
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

    /**
     * @see it('One arg on multiple optional')
     */
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

    /**
     * @see it('Second arg on multiple optional')
     */
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

    /**
     * @see it('Multiple reqs on mixedList')
     */
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

    /**
     * @see it('Multiple reqs and one opt on mixedList')
     */
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

    /**
     * @see it('All reqs and opts on mixedList')
     */
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

    /**
     * @see it('Missing one non-nullable argument')
     */
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

    /**
     * @see it('Missing multiple non-nullable arguments')
     */
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

    /**
     * @see it('Incorrect value and missing argument')
     */
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

    // Describe: Directive arguments

    /**
     * @see it('ignores unknown directives')
     */
    public function testIgnoresUnknownDirectives()
    {
        $this->expectPassesRule(new ProvidedNonNullArguments, '
        {
          dog @unknown
        }
        ');
    }

    /**
     * @see it('with directives of valid types')
     */
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

    /**
     * @see it('with directive with missing types')
     */
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
