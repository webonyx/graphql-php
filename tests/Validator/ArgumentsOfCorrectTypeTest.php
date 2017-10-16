<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\ArgumentsOfCorrectType;

class ArgumentsOfCorrectTypeTest extends TestCase
{
    function badValue($argName, $typeName, $value, $line, $column, $errors = null)
    {
        $realErrors = !$errors ? ["Expected type \"$typeName\", found $value."] : $errors;

        return FormattedError::create(
            ArgumentsOfCorrectType::badValueMessage($argName, $typeName, $value, $realErrors),
            [new SourceLocation($line, $column)]
        );
    }

    // Validate: Argument values of correct type
    // Valid values

    /**
     * @it Good int value
     */
    public function testGoodIntValue()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            intArgField(intArg: 2)
          }
        }
        ');
    }

    /**
     * @it Good negative int value
     */
    public function testGoodNegativeIntValue()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            intArgField(intArg: -2)
          }
        }
        ');
    }

    /**
     * @it Good boolean value
     */
    public function testGoodBooleanValue()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            booleanArgField(booleanArg: true)
          }
        }
      ');
    }

    /**
     * @it Good string value
     */
    public function testGoodStringValue()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            stringArgField(stringArg: "foo")
          }
        }
        ');
    }

    /**
     * @it Good float value
     */
    public function testGoodFloatValue()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            floatArgField(floatArg: 1.1)
          }
        }
        ');
    }

    public function testGoodNegativeFloatValue()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            floatArgField(floatArg: -1.1)
          }
        }
        ');
    }

    /**
     * @it Int into Float
     */
    public function testIntIntoFloat()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            floatArgField(floatArg: 1)
          }
        }
        ');
    }

    /**
     * @it Int into ID
     */
    public function testIntIntoID()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            idArgField(idArg: 1)
          }
        }
        ');
    }

    /**
     * @it String into ID
     */
    public function testStringIntoID()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            idArgField(idArg: "someIdString")
          }
        }
        ');
    }

    /**
     * @it Good enum value
     */
    public function testGoodEnumValue()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          dog {
            doesKnowCommand(dogCommand: SIT)
          }
        }
        ');
    }

    /**
     * @it Enum with null value
     */
    public function testEnumWithNullValue()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            enumArgField(enumArg: NO_FUR)
          }
        }
        ');
    }

    /**
     * @it null into nullable type
     */
    public function testNullIntoNullableType()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            intArgField(intArg: null)
          }
        }
        ');

        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          dog(a: null, b: null, c:{ requiredField: true, intField: null }) {
            name
          }
        }
        ');
    }

    // Invalid String values

    /**
     * @it Int into String
     */
    public function testIntIntoString()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            stringArgField(stringArg: 1)
          }
        }
        ', [
            $this->badValue('stringArg', 'String', '1', 4, 39)
        ]);
    }

    /**
     * @it Float into String
     */
    public function testFloatIntoString()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            stringArgField(stringArg: 1.0)
          }
        }
        ', [
            $this->badValue('stringArg', 'String', '1.0', 4, 39)
        ]);
    }

    /**
     * @it Boolean into String
     */
    public function testBooleanIntoString()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            stringArgField(stringArg: true)
          }
        }
        ', [
            $this->badValue('stringArg', 'String', 'true', 4, 39)
        ]);
    }

    /**
     * @it Unquoted String into String
     */
    public function testUnquotedStringIntoString()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            stringArgField(stringArg: BAR)
          }
        }
        ', [
            $this->badValue('stringArg', 'String', 'BAR', 4, 39)
        ]);
    }

    // Invalid Int values

    /**
     * @it String into Int
     */
    public function testStringIntoInt()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            intArgField(intArg: "3")
          }
        }
        ', [
            $this->badValue('intArg', 'Int', '"3"', 4, 33)
        ]);
    }

    /**
     * @it Big Int into Int
     */
    public function testBigIntIntoInt()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            intArgField(intArg: 829384293849283498239482938)
          }
        }
        ', [
            $this->badValue('intArg', 'Int', '829384293849283498239482938', 4, 33)
        ]);
    }

    /**
     * @it Unquoted String into Int
     */
    public function testUnquotedStringIntoInt()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            intArgField(intArg: FOO)
          }
        }
        ', [
            $this->badValue('intArg', 'Int', 'FOO', 4, 33)
        ]);
    }

    /**
     * @it Simple Float into Int
     */
    public function testSimpleFloatIntoInt()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            intArgField(intArg: 3.0)
          }
        }
        ', [
            $this->badValue('intArg', 'Int', '3.0', 4, 33)
        ]);
    }

    /**
     * @it Float into Int
     */
    public function testFloatIntoInt()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            intArgField(intArg: 3.333)
          }
        }
        ', [
            $this->badValue('intArg', 'Int', '3.333', 4, 33)
        ]);
    }

    // Invalid Float values

    /**
     * @it String into Float
     */
    public function testStringIntoFloat()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            floatArgField(floatArg: "3.333")
          }
        }
        ', [
            $this->badValue('floatArg', 'Float', '"3.333"', 4, 37)
        ]);
    }

    /**
     * @it Boolean into Float
     */
    public function testBooleanIntoFloat()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            floatArgField(floatArg: true)
          }
        }
        ', [
            $this->badValue('floatArg', 'Float', 'true', 4, 37)
        ]);
    }

    /**
     * @it Unquoted into Float
     */
    public function testUnquotedIntoFloat()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            floatArgField(floatArg: FOO)
          }
        }
        ', [
            $this->badValue('floatArg', 'Float', 'FOO', 4, 37)
        ]);
    }

    // Invalid Boolean value

    /**
     * @it Int into Boolean
     */
    public function testIntIntoBoolean()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            booleanArgField(booleanArg: 2)
          }
        }
        ', [
            $this->badValue('booleanArg', 'Boolean', '2', 4, 41)
        ]);
    }

    /**
     * @it Float into Boolean
     */
    public function testFloatIntoBoolean()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            booleanArgField(booleanArg: 1.0)
          }
        }
        ', [
            $this->badValue('booleanArg', 'Boolean', '1.0', 4, 41)
        ]);
    }

    /**
     * @it String into Boolean
     */
    public function testStringIntoBoolean()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            booleanArgField(booleanArg: "true")
          }
        }
        ', [
            $this->badValue('booleanArg', 'Boolean', '"true"', 4, 41)
        ]);
    }

    /**
     * @it Unquoted into Boolean
     */
    public function testUnquotedIntoBoolean()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            booleanArgField(booleanArg: TRUE)
          }
        }
        ', [
            $this->badValue('booleanArg', 'Boolean', 'TRUE', 4, 41)
        ]);
    }

    // Invalid ID value

    /**
     * @it Float into ID
     */
    public function testFloatIntoID()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            idArgField(idArg: 1.0)
          }
        }
        ', [
            $this->badValue('idArg', 'ID', '1.0', 4, 31)
        ]);
    }

    /**
     * @it Boolean into ID
     */
    public function testBooleanIntoID()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            idArgField(idArg: true)
          }
        }
        ', [
            $this->badValue('idArg', 'ID', 'true', 4, 31)
        ]);
    }

    /**
     * @it Unquoted into ID
     */
    public function testUnquotedIntoID()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            idArgField(idArg: SOMETHING)
          }
        }
        ', [
            $this->badValue('idArg', 'ID', 'SOMETHING', 4, 31)
        ]);
    }

    // Invalid Enum value

    /**
     * @it Int into Enum
     */
    public function testIntIntoEnum()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          dog {
            doesKnowCommand(dogCommand: 2)
          }
        }
        ', [
            $this->badValue('dogCommand', 'DogCommand', '2', 4, 41)
        ]);
    }

    /**
     * @it Float into Enum
     */
    public function testFloatIntoEnum()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          dog {
            doesKnowCommand(dogCommand: 1.0)
          }
        }
        ', [
            $this->badValue('dogCommand', 'DogCommand', '1.0', 4, 41)
        ]);
    }

    /**
     * @it String into Enum
     */
    public function testStringIntoEnum()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          dog {
            doesKnowCommand(dogCommand: "SIT")
          }
        }
        ', [
            $this->badValue('dogCommand', 'DogCommand', '"SIT"', 4, 41)
        ]);
    }

    /**
     * @it Boolean into Enum
     */
    public function testBooleanIntoEnum()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          dog {
            doesKnowCommand(dogCommand: true)
          }
        }
        ', [
            $this->badValue('dogCommand', 'DogCommand', 'true', 4, 41)
        ]);
    }

    /**
     * @it Unknown Enum Value into Enum
     */
    public function testUnknownEnumValueIntoEnum()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          dog {
            doesKnowCommand(dogCommand: JUGGLE)
          }
        }
        ', [
            $this->badValue('dogCommand', 'DogCommand', 'JUGGLE', 4, 41)
        ]);
    }

    /**
     * @it Different case Enum Value into Enum
     */
    public function testDifferentCaseEnumValueIntoEnum()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          dog {
            doesKnowCommand(dogCommand: sit)
          }
        }
        ', [
            $this->badValue('dogCommand', 'DogCommand', 'sit', 4, 41)
        ]);
    }

    // Valid List value

    /**
     * @it Good list value
     */
    public function testGoodListValue()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            stringListArgField(stringListArg: ["one", "two"])
          }
        }
        ');
    }

    /**
     * @it Empty list value
     */
    public function testEmptyListValue()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            stringListArgField(stringListArg: [])
          }
        }
        ');
    }

    /**
     * @it Null value
     */
    public function testNullValue()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            stringListArgField(stringListArg: null)
          }
        }
        ');
    }

    /**
     * @it Single value into List
     */
    public function testSingleValueIntoList()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            stringListArgField(stringListArg: "one")
          }
        }
        ');
    }

    // Invalid List value

    /**
     * @it Incorrect item type
     */
    public function testIncorrectItemtype()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            stringListArgField(stringListArg: ["one", 2])
          }
        }
        ', [
            $this->badValue('stringListArg', '[String]', '["one", 2]', 4, 47, [
                'In element #1: Expected type "String", found 2.'
            ]),
        ]);
    }

    /**
     * @it Single value of incorrect type
     */
    public function testSingleValueOfIncorrectType()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            stringListArgField(stringListArg: 1)
          }
        }
        ', [
            $this->badValue('stringListArg', 'String', '1', 4, 47),
        ]);
    }

    // Valid non-nullable value

    /**
     * @it Arg on optional arg
     */
    public function testArgOnOptionalArg()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          dog {
            isHousetrained(atOtherHomes: true)
          }
        }
        ');
    }

    /**
     * @it No Arg on optional arg
     */
    public function testNoArgOnOptionalArg()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          dog {
            isHousetrained
          }
        }
        ');
    }

    /**
     * @it Multiple args
     */
    public function testMultipleArgs()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            multipleReqs(req1: 1, req2: 2)
          }
        }
        ');
    }

    /**
     * @it Multiple args reverse order
     */
    public function testMultipleArgsReverseOrder()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            multipleReqs(req2: 2, req1: 1)
          }
        }
        ');
    }

    /**
     * @it No args on multiple optional
     */
    public function testNoArgsOnMultipleOptional()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            multipleOpts
          }
        }
        ');
    }

    /**
     * @it One arg on multiple optional
     */
    public function testOneArgOnMultipleOptional()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType, '
        {
          complicatedArgs {
            multipleOpts(opt1: 1)
          }
        }
        ');
    }

    /**
     * @it Second arg on multiple optional
     */
    public function testSecondArgOnMultipleOptional()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType, '
        {
          complicatedArgs {
            multipleOpts(opt2: 1)
          }
        }
        ');
    }

    /**
     * @it Multiple reqs on mixedList
     */
    public function testMultipleReqsOnMixedList()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType, '
        {
          complicatedArgs {
            multipleOptAndReq(req1: 3, req2: 4)
          }
        }
        ');
    }

    /**
     * @it Multiple reqs and one opt on mixedList
     */
    public function testMultipleReqsAndOneOptOnMixedList()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType, '
        {
          complicatedArgs {
            multipleOptAndReq(req1: 3, req2: 4, opt1: 5)
          }
        }
        ');
    }

    /**
     * @it All reqs and opts on mixedList
     */
    public function testAllReqsAndOptsOnMixedList()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType, '
        {
          complicatedArgs {
            multipleOptAndReq(req1: 3, req2: 4, opt1: 5, opt2: 6)
          }
        }
        ');
    }

    // Invalid non-nullable value

    /**
     * @it Incorrect value type
     */
    public function testIncorrectValueType()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType, '
        {
          complicatedArgs {
            multipleReqs(req2: "two", req1: "one")
          }
        }
        ', [
            $this->badValue('req2', 'Int', '"two"', 4, 32),
            $this->badValue('req1', 'Int', '"one"', 4, 45),
        ]);
    }

    /**
     * @it Incorrect value and missing argument
     */
    public function testIncorrectValueAndMissingArgument()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType, '
        {
          complicatedArgs {
            multipleReqs(req1: "one")
          }
        }
        ', [
            $this->badValue('req1', 'Int', '"one"', 4, 32),
        ]);
    }

    /**
     * @it Null value
     */
    public function testNullValue2()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            multipleReqs(req1: null)
          }
        }
        ', [
            $this->badValue('req1', 'Int!', 'null', 4, 32, [
                'Expected "Int!", found null.'
            ]),
        ]);
    }


    // Valid input object value

    /**
     * @it Optional arg, despite required field in type
     */
    public function testOptionalArgDespiteRequiredFieldInType()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType, '
        {
          complicatedArgs {
            complexArgField
          }
        }
        ');
    }

    /**
     * @it Partial object, only required
     */
    public function testPartialObjectOnlyRequired()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType, '
        {
          complicatedArgs {
            complexArgField(complexArg: { requiredField: true })
          }
        }
        ');
    }

    /**
     * @it Partial object, required field can be falsey
     */
    public function testPartialObjectRequiredFieldCanBeFalsey()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType, '
        {
          complicatedArgs {
            complexArgField(complexArg: { requiredField: false })
          }
        }
        ');
    }

    /**
     * @it Partial object, including required
     */
    public function testPartialObjectIncludingRequired()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType, '
        {
          complicatedArgs {
            complexArgField(complexArg: { requiredField: true, intField: 4 })
          }
        }
        ');
    }

    /**
     * @it Full object
     */
    public function testFullObject()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType, '
        {
          complicatedArgs {
            complexArgField(complexArg: {
              requiredField: true,
              intField: 4,
              stringField: "foo",
              booleanField: false,
              stringListField: ["one", "two"]
            })
          }
        }
        ');
    }

    /**
     * @it Full object with fields in different order
     */
    public function testFullObjectWithFieldsInDifferentOrder()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            complexArgField(complexArg: {
              stringListField: ["one", "two"],
              booleanField: false,
              requiredField: true,
              stringField: "foo",
              intField: 4,
            })
          }
        }
        ');
    }

    // Invalid input object value

    /**
     * @it Partial object, missing required
     */
    public function testPartialObjectMissingRequired()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType, '
        {
          complicatedArgs {
            complexArgField(complexArg: { intField: 4 })
          }
        }
        ', [
            $this->badValue('complexArg', 'ComplexInput', '{intField: 4}', 4, 41, [
                'In field "requiredField": Expected "Boolean!", found null.'
            ]),
        ]);
    }

    /**
     * @it Partial object, invalid field type
     */
    public function testPartialObjectInvalidFieldType()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType, '
        {
          complicatedArgs {
            complexArgField(complexArg: {
              stringListField: ["one", 2],
              requiredField: true,
            })
          }
        }
        ', [
            $this->badValue(
                'complexArg',
                'ComplexInput',
                '{stringListField: ["one", 2], requiredField: true}',
                4,
                41,
                [ 'In field "stringListField": In element #1: Expected type "String", found 2.' ]
            ),
        ]);
    }

    /**
     * @it Partial object, unknown field arg
     */
    public function testPartialObjectUnknownFieldArg()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType, '
        {
          complicatedArgs {
            complexArgField(complexArg: {
              requiredField: true,
              unknownField: "value"
            })
          }
        }
        ', [
            $this->badValue(
                'complexArg',
                'ComplexInput',
                '{requiredField: true, unknownField: "value"}',
                4,
                41,
                [ 'In field "unknownField": Unknown field.' ]
            ),
        ]);
    }

    // Directive arguments

    /**
     * @it with directives of valid types
     */
    public function testWithDirectivesOfValidTypes()
    {
        $this->expectPassesRule(new ArgumentsOfCorrectType(), '
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
     * @it with directive with incorrect types
     */
    public function testWithDirectiveWithIncorrectTypes()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType, '
        {
          dog @include(if: "yes") {
            name @skip(if: ENUM)
          }
        }
      ', [
            $this->badValue('if', 'Boolean', '"yes"', 3, 28),
            $this->badValue('if', 'Boolean', 'ENUM', 4, 28),
        ]);
    }
}
