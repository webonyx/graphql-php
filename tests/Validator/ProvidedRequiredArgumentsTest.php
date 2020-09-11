<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Utils\BuildSchema;
use GraphQL\Validator\Rules\ProvidedRequiredArguments;
use GraphQL\Validator\Rules\ProvidedRequiredArgumentsOnDirectives;

class ProvidedRequiredArgumentsTest extends ValidatorTestCase
{
    // Validate: Provided required arguments

    /**
     * @see it('ignores unknown arguments')
     */
    public function testIgnoresUnknownArguments(): void
    {
        // ignores unknown arguments
        $this->expectPassesRule(
            new ProvidedRequiredArguments(),
            '
      {
        dog {
          isHousetrained(unknownArgument: true)
        }
      }
        '
        );
    }

    // Valid non-nullable value:

    /**
     * @see it('Arg on optional arg')
     */
    public function testArgOnOptionalArg(): void
    {
        $this->expectPassesRule(
            new ProvidedRequiredArguments(),
            '
        {
          dog {
            isHousetrained(atOtherHomes: true)
          }
        }
        '
        );
    }

    /**
     * @see it('No Arg on optional arg')
     */
    public function testNoArgOnOptionalArg(): void
    {
        $this->expectPassesRule(
            new ProvidedRequiredArguments(),
            '
        {
          dog {
            isHousetrained
          }
        }
        '
        );
    }

    /**
     * @see it('No arg on non-null field with default')
     */
    public function testNoArgOnNonNullFieldWithDefault()
    {
        $this->expectPassesRule(
            new ProvidedRequiredArguments(),
            '
            {
              complicatedArgs {
                nonNullFieldWithDefault
              }
            }
            '
        );
    }

    /**
     * @see it('Multiple args')
     */
    public function testMultipleArgs(): void
    {
        $this->expectPassesRule(
            new ProvidedRequiredArguments(),
            '
        {
          complicatedArgs {
            multipleReqs(req1: 1, req2: 2)
          }
        }
        '
        );
    }

    /**
     * @see it('Multiple args reverse order')
     */
    public function testMultipleArgsReverseOrder(): void
    {
        $this->expectPassesRule(
            new ProvidedRequiredArguments(),
            '
        {
          complicatedArgs {
            multipleReqs(req2: 2, req1: 1)
          }
        }
        '
        );
    }

    /**
     * @see it('No args on multiple optional')
     */
    public function testNoArgsOnMultipleOptional(): void
    {
        $this->expectPassesRule(
            new ProvidedRequiredArguments(),
            '
        {
          complicatedArgs {
            multipleOpts
          }
        }
        '
        );
    }

    /**
     * @see it('One arg on multiple optional')
     */
    public function testOneArgOnMultipleOptional(): void
    {
        $this->expectPassesRule(
            new ProvidedRequiredArguments(),
            '
        {
          complicatedArgs {
            multipleOpts(opt1: 1)
          }
        }
        '
        );
    }

    /**
     * @see it('Second arg on multiple optional')
     */
    public function testSecondArgOnMultipleOptional(): void
    {
        $this->expectPassesRule(
            new ProvidedRequiredArguments(),
            '
        {
          complicatedArgs {
            multipleOpts(opt2: 1)
          }
        }
        '
        );
    }

    /**
     * @see it('Multiple reqs on mixedList')
     */
    public function testMultipleReqsOnMixedList(): void
    {
        $this->expectPassesRule(
            new ProvidedRequiredArguments(),
            '
        {
          complicatedArgs {
            multipleOptAndReq(req1: 3, req2: 4)
          }
        }
        '
        );
    }

    /**
     * @see it('Multiple reqs and one opt on mixedList')
     */
    public function testMultipleReqsAndOneOptOnMixedList(): void
    {
        $this->expectPassesRule(
            new ProvidedRequiredArguments(),
            '
        {
          complicatedArgs {
            multipleOptAndReq(req1: 3, req2: 4, opt1: 5)
          }
        }
        '
        );
    }

    /**
     * @see it('All reqs and opts on mixedList')
     */
    public function testAllReqsAndOptsOnMixedList(): void
    {
        $this->expectPassesRule(
            new ProvidedRequiredArguments(),
            '
        {
          complicatedArgs {
            multipleOptAndReq(req1: 3, req2: 4, opt1: 5, opt2: 6)
          }
        }
        '
        );
    }

    // Invalid non-nullable value

    /**
     * @see it('Missing one non-nullable argument')
     */
    public function testMissingOneNonNullableArgument(): void
    {
        $this->expectFailsRule(
            new ProvidedRequiredArguments(),
            '
        {
          complicatedArgs {
            multipleReqs(req2: 2)
          }
        }
        ',
            [$this->missingFieldArg('multipleReqs', 'req1', 'Int!', 4, 13)]
        );
    }

    private function missingFieldArg($fieldName, $argName, $typeName, $line, $column)
    {
        return FormattedError::create(
            ProvidedRequiredArguments::missingFieldArgMessage($fieldName, $argName, $typeName),
            [new SourceLocation($line, $column)]
        );
    }

    /**
     * @see it('Missing multiple non-nullable arguments')
     */
    public function testMissingMultipleNonNullableArguments(): void
    {
        $this->expectFailsRule(
            new ProvidedRequiredArguments(),
            '
        {
          complicatedArgs {
            multipleReqs
          }
        }
        ',
            [
                $this->missingFieldArg('multipleReqs', 'req1', 'Int!', 4, 13),
                $this->missingFieldArg('multipleReqs', 'req2', 'Int!', 4, 13),
            ]
        );
    }

    // Describe: Directive arguments

    /**
     * @see it('Incorrect value and missing argument')
     */
    public function testIncorrectValueAndMissingArgument(): void
    {
        $this->expectFailsRule(
            new ProvidedRequiredArguments(),
            '
        {
          complicatedArgs {
            multipleReqs(req1: "one")
          }
        }
        ',
            [
                $this->missingFieldArg('multipleReqs', 'req2', 'Int!', 4, 13),
            ]
        );
    }

    /**
     * @see it('ignores unknown directives')
     */
    public function testIgnoresUnknownDirectives(): void
    {
        $this->expectPassesRule(
            new ProvidedRequiredArguments(),
            '
        {
          dog @unknown
        }
        '
        );
    }

    /**
     * @see it('with directives of valid types')
     */
    public function testWithDirectivesOfValidTypes(): void
    {
        $this->expectPassesRule(
            new ProvidedRequiredArguments(),
            '
        {
          dog @include(if: true) {
            name
          }
          human @skip(if: false) {
            name
          }
        }
        '
        );
    }

    /**
     * @see it('with directive with missing types')
     */
    public function testWithDirectiveWithMissingTypes(): void
    {
        $this->expectFailsRule(
            new ProvidedRequiredArguments(),
            '
        {
          dog @include {
            name @skip
          }
        }
        ',
            [
                $this->missingDirectiveArg('include', 'if', 'Boolean!', 3, 15),
                $this->missingDirectiveArg('skip', 'if', 'Boolean!', 4, 18),
            ]
        );
    }

    // Describe: within SDL

    /**
     * @see it('Missing optional args on directive defined inside SDL')
     */
    public function testMissingOptionalArgsOnDirectiveDefinedInsideSDL()
    {
        $this->expectPassesRule(
            new ProvidedRequiredArgumentsOnDirectives(),
            '
                type Query {
                  foo: String @test
                }
        
                directive @test(arg1: String, arg2: String! = "") on FIELD_DEFINITION
            '
        );
    }

    /**
     * @see it('Missing arg on directive defined inside SDL')
     */
    public function testMissingArgOnDirectiveDefinedInsideSDL()
    {
        $this->expectFailsRule(
            new ProvidedRequiredArgumentsOnDirectives(),
            '
                type Query {
                  foo: String @test
                }
        
                directive @test(arg: String!) on FIELD_DEFINITION
            ',
            [$this->missingDirectiveArg('test', 'arg', 'String!', 3, 31)]
        );
    }

    /**
     * @see it('Missing arg on standard directive')
     */
    public function testMissingArgOnStandardDirective()
    {
        $this->expectFailsRule(
            new ProvidedRequiredArgumentsOnDirectives(),
            '
                type Query {
                  foo: String @include
                }
            ',
            [$this->missingDirectiveArg('include', 'if', 'Boolean!', 3, 31)]
        );
    }

    /**
     * @see it('Missing arg on overrided standard directive')
     */
    public function testMissingArgOnOverridedStandardDirective()
    {
        $this->expectFailsRule(
            new ProvidedRequiredArgumentsOnDirectives(),
            '
                type Query {
                  foo: String @deprecated
                }
                directive @deprecated(reason: String!) on FIELD
            ',
            [$this->missingDirectiveArg('deprecated', 'reason', 'String!', 3, 31)]
        );
    }

    /**
     * @see it('Missing arg on directive defined in schema extension')
     */
    public function testMissingArgOnDirectiveDefinedInSchemaExtension()
    {
        $schema = BuildSchema::build('
            type Query {
              foo: String
            }
        ');
        $this->expectInvalid(
            $schema,
            [new ProvidedRequiredArgumentsOnDirectives()],
            '
                directive @test(arg: String!) on OBJECT
        
                extend type Query  @test
            ',
            [$this->missingDirectiveArg('test', 'arg', 'String!', 4, 36)]
        );
    }

    /**
     * @see it('Missing arg on directive used in schema extension')
     */
    public function testMissingArgOnDirectiveUsedInSchemaExtension()
    {
        $schema = BuildSchema::build('
            directive @test(arg: String!) on OBJECT
    
            type Query {
              foo: String
            }
        ');
        $this->expectInvalid(
            $schema,
            [new ProvidedRequiredArgumentsOnDirectives()],
            '
                extend type Query @test
            ',
            [$this->missingDirectiveArg('test', 'arg', 'String!', 2, 35)]
        );
    }

    private function missingDirectiveArg($directiveName, $argName, $typeName, $line, $column)
    {
        return FormattedError::create(
            ProvidedRequiredArgumentsOnDirectives::missingDirectiveArgMessage($directiveName, $argName, $typeName),
            [new SourceLocation($line, $column)]
        );
    }
}
