<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\ValuesOfCorrectType;

class ValuesOfCorrectTypeTest extends ValidatorTestCase
{
    /**
     * @see it('Good int value')
     */
    public function testGoodIntValue() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            intArgField(intArg: 2)
          }
        }
        '
        );
    }

    /**
     * @see it('Good negative int value')
     */
    public function testGoodNegativeIntValue() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            intArgField(intArg: -2)
          }
        }
        '
        );
    }

    /**
     * @see it('Good boolean value')
     */
    public function testGoodBooleanValue() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            booleanArgField(booleanArg: true)
          }
        }
      '
        );
    }

    // Validate: Values of correct type
    // Valid values

    /**
     * @see it('Good string value')
     */
    public function testGoodStringValue() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            stringArgField(stringArg: "foo")
          }
        }
        '
        );
    }

    /**
     * @see it('Good float value')
     */
    public function testGoodFloatValue() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            floatArgField(floatArg: 1.1)
          }
        }
        '
        );
    }

    public function testGoodNegativeFloatValue() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            floatArgField(floatArg: -1.1)
          }
        }
        '
        );
    }

    /**
     * @see it('Int into Float')
     */
    public function testIntIntoFloat() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            floatArgField(floatArg: 1)
          }
        }
        '
        );
    }

    /**
     * @see it('Int into ID')
     */
    public function testIntIntoID() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            idArgField(idArg: 1)
          }
        }
        '
        );
    }

    /**
     * @see it('String into ID')
     */
    public function testStringIntoID() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            idArgField(idArg: "someIdString")
          }
        }
        '
        );
    }

    /**
     * @see it('Good enum value')
     */
    public function testGoodEnumValue() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          dog {
            doesKnowCommand(dogCommand: SIT)
          }
        }
        '
        );
    }

    /**
     * @see it('Enum with null value')
     */
    public function testEnumWithNullValue() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            enumArgField(enumArg: NO_FUR)
          }
        }
        '
        );
    }

    /**
     * @see it('null into nullable type')
     */
    public function testNullIntoNullableType() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            intArgField(intArg: null)
          }
        }
        '
        );

        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          dog(a: null, b: null, c:{ requiredField: true, intField: null }) {
            name
          }
        }
        '
        );
    }

    /**
     * @see it('Int into String')
     */
    public function testIntIntoString() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            stringArgField(stringArg: 1)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "stringArgField" argument "stringArg" requires type String, found 1.', 4, 39),
            ]
        );
    }

    private function badValue($typeName, $value, $line, $column, $message = null)
    {
        return FormattedError::create(
            ValuesOfCorrectType::badValueMessage(
                $typeName,
                $value,
                $message
            ),
            [new SourceLocation($line, $column)]
        );
    }

    private function badValueWithMessage($message, $line, $column)
    {
        return FormattedError::create($message, [new SourceLocation($line, $column)]);
    }

    /**
     * @see it('Float into String')
     */
    public function testFloatIntoString() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            stringArgField(stringArg: 1.0)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "stringArgField" argument "stringArg" requires type String, found 1.0.', 4, 39),
            ]
        );
    }

    // Invalid String values

    /**
     * @see it('Boolean into String')
     */
    public function testBooleanIntoString() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            stringArgField(stringArg: true)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "stringArgField" argument "stringArg" requires type String, found true.', 4, 39),
            ]
        );
    }

    /**
     * @see it('Unquoted String into String')
     */
    public function testUnquotedStringIntoString() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            stringArgField(stringArg: BAR)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "stringArgField" argument "stringArg" requires type String, found BAR.', 4, 39),
            ]
        );
    }

    /**
     * @see it('String into Int')
     */
    public function testStringIntoInt() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            intArgField(intArg: "3")
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "intArgField" argument "intArg" requires type Int, found "3".', 4, 33),
            ]
        );
    }

    /**
     * @see it('Big Int into Int')
     */
    public function testBigIntIntoInt() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            intArgField(intArg: 829384293849283498239482938)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "intArgField" argument "intArg" requires type Int, found 829384293849283498239482938.', 4, 33),
            ]
        );
    }

    // Invalid Int values

    /**
     * @see it('Unquoted String into Int')
     */
    public function testUnquotedStringIntoInt() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            intArgField(intArg: FOO)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "intArgField" argument "intArg" requires type Int, found FOO.', 4, 33),
            ]
        );
    }

    /**
     * @see it('Simple Float into Int')
     */
    public function testSimpleFloatIntoInt() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            intArgField(intArg: 3.0)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "intArgField" argument "intArg" requires type Int, found 3.0.', 4, 33),
            ]
        );
    }

    /**
     * @see it('Float into Int')
     */
    public function testFloatIntoInt() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            intArgField(intArg: 3.333)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "intArgField" argument "intArg" requires type Int, found 3.333.', 4, 33),
            ]
        );
    }

    /**
     * @see it('String into Float')
     */
    public function testStringIntoFloat() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            floatArgField(floatArg: "3.333")
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "floatArgField" argument "floatArg" requires type Float, found "3.333".', 4, 37),
            ]
        );
    }

    /**
     * @see it('Boolean into Float')
     */
    public function testBooleanIntoFloat() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            floatArgField(floatArg: true)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "floatArgField" argument "floatArg" requires type Float, found true.', 4, 37),
            ]
        );
    }

    // Invalid Float values

    /**
     * @see it('Unquoted into Float')
     */
    public function testUnquotedIntoFloat() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            floatArgField(floatArg: FOO)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "floatArgField" argument "floatArg" requires type Float, found FOO.', 4, 37),
            ]
        );
    }

    /**
     * @see it('Int into Boolean')
     */
    public function testIntIntoBoolean() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            booleanArgField(booleanArg: 2)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "booleanArgField" argument "booleanArg" requires type Boolean, found 2.', 4, 41),
            ]
        );
    }

    /**
     * @see it('Float into Boolean')
     */
    public function testFloatIntoBoolean() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            booleanArgField(booleanArg: 1.0)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "booleanArgField" argument "booleanArg" requires type Boolean, found 1.0.', 4, 41),
            ]
        );
    }

    // Invalid Boolean value

    /**
     * @see it('String into Boolean')
     */
    public function testStringIntoBoolean() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            booleanArgField(booleanArg: "true")
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "booleanArgField" argument "booleanArg" requires type Boolean, found "true".', 4, 41),
            ]
        );
    }

    /**
     * @see it('Unquoted into Boolean')
     */
    public function testUnquotedIntoBoolean() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            booleanArgField(booleanArg: TRUE)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "booleanArgField" argument "booleanArg" requires type Boolean, found TRUE.', 4, 41),
            ]
        );
    }

    /**
     * @see it('Float into ID')
     */
    public function testFloatIntoID() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            idArgField(idArg: 1.0)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "idArgField" argument "idArg" requires type ID, found 1.0.', 4, 31),
            ]
        );
    }

    /**
     * @see it('Boolean into ID')
     */
    public function testBooleanIntoID() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            idArgField(idArg: true)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "idArgField" argument "idArg" requires type ID, found true.', 4, 31),
            ]
        );
    }

    // Invalid ID value

    /**
     * @see it('Unquoted into ID')
     */
    public function testUnquotedIntoID() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            idArgField(idArg: SOMETHING)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "idArgField" argument "idArg" requires type ID, found SOMETHING.', 4, 31),
            ]
        );
    }

    /**
     * @see it('Int into Enum')
     */
    public function testIntIntoEnum() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          dog {
            doesKnowCommand(dogCommand: 2)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "doesKnowCommand" argument "dogCommand" requires type DogCommand, found 2.', 4, 41),
            ]
        );
    }

    /**
     * @see it('Float into Enum')
     */
    public function testFloatIntoEnum() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          dog {
            doesKnowCommand(dogCommand: 1.0)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "doesKnowCommand" argument "dogCommand" requires type DogCommand, found 1.0.', 4, 41),
            ]
        );
    }

    // Invalid Enum value

    /**
     * @see it('String into Enum')
     */
    public function testStringIntoEnum() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          dog {
            doesKnowCommand(dogCommand: "SIT")
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "doesKnowCommand" argument "dogCommand" requires type DogCommand, found "SIT"; Did you mean the enum value SIT?', 4, 41),
            ]
        );
    }

    /**
     * @see it('Boolean into Enum')
     */
    public function testBooleanIntoEnum() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          dog {
            doesKnowCommand(dogCommand: true)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "doesKnowCommand" argument "dogCommand" requires type DogCommand, found true.', 4, 41),
            ]
        );
    }

    /**
     * @see it('Unknown Enum Value into Enum')
     */
    public function testUnknownEnumValueIntoEnum() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          dog {
            doesKnowCommand(dogCommand: JUGGLE)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "doesKnowCommand" argument "dogCommand" requires type DogCommand, found JUGGLE.', 4, 41),
            ]
        );
    }

    /**
     * @see it('Different case Enum Value into Enum')
     */
    public function testDifferentCaseEnumValueIntoEnum() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          dog {
            doesKnowCommand(dogCommand: sit)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "doesKnowCommand" argument "dogCommand" requires type DogCommand, found sit; Did you mean the enum value SIT?', 4, 41),
            ]
        );
    }

    /**
     * @see it('Good list value')
     */
    public function testGoodListValue() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            stringListArgField(stringListArg: ["one", null, "two"])
          }
        }
        '
        );
    }

    /**
     * @see it('Empty list value')
     */
    public function testEmptyListValue() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            stringListArgField(stringListArg: [])
          }
        }
        '
        );
    }

    // Valid List value

    /**
     * @see it('Null value')
     */
    public function testNullValue() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            stringListArgField(stringListArg: null)
          }
        }
        '
        );
    }

    /**
     * @see it('Single value into List')
     */
    public function testSingleValueIntoList() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            stringListArgField(stringListArg: "one")
          }
        }
        '
        );
    }

    /**
     * @see it('Incorrect item type')
     */
    public function testIncorrectItemtype() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            stringListArgField(stringListArg: ["one", 2])
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "stringListArgField" argument "stringListArg" requires type String, found 2.', 4, 55),
            ]
        );
    }

    /**
     * @see it('Single value of incorrect type')
     */
    public function testSingleValueOfIncorrectType() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            stringListArgField(stringListArg: 1)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "stringListArgField" argument "stringListArg" requires type [String], found 1.', 4, 47),
            ]
        );
    }

    // Invalid List value

    /**
     * @see it('Arg on optional arg')
     */
    public function testArgOnOptionalArg() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
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
    public function testNoArgOnOptionalArg() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          dog {
            isHousetrained
          }
        }
        '
        );
    }

    // Valid non-nullable value

    /**
     * @see it('Multiple args')
     */
    public function testMultipleArgs() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
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
    public function testMultipleArgsReverseOrder() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
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
    public function testNoArgsOnMultipleOptional() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
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
    public function testOneArgOnMultipleOptional() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
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
    public function testSecondArgOnMultipleOptional() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
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
    public function testMultipleReqsOnMixedList() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
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
    public function testMultipleReqsAndOneOptOnMixedList() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
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
    public function testAllReqsAndOptsOnMixedList() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            multipleOptAndReq(req1: 3, req2: 4, opt1: 5, opt2: 6)
          }
        }
        '
        );
    }

    /**
     * @see it('Incorrect value type')
     */
    public function testIncorrectValueType() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            multipleReqs(req2: "two", req1: "one")
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "multipleReqs" argument "req2" requires type Int!, found "two".', 4, 32),
                $this->badValueWithMessage('Field "multipleReqs" argument "req1" requires type Int!, found "one".', 4, 45),
            ]
        );
    }

    /**
     * @see it('Incorrect value and missing argument (ProvidedRequiredArguments)')
     */
    public function testIncorrectValueAndMissingArgumentProvidedRequiredArguments() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            multipleReqs(req1: "one")
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "multipleReqs" argument "req1" requires type Int!, found "one".', 4, 32),
            ]
        );
    }

    // Invalid non-nullable value

    /**
     * @see it('Null value')
     */
    public function testNullValue2() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            multipleReqs(req1: null)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "multipleReqs" argument "req1" requires type Int!, found null.', 4, 32),
            ]
        );
    }

    /**
     * @see it('Optional arg, despite required field in type')
     */
    public function testOptionalArgDespiteRequiredFieldInType() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            complexArgField
          }
        }
        '
        );
    }

    /**
     * @see it('Partial object, only required')
     */
    public function testPartialObjectOnlyRequired() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            complexArgField(complexArg: { requiredField: true })
          }
        }
        '
        );
    }

    // DESCRIBE: Valid input object value

    /**
     * @see it('Partial object, required field can be falsey')
     */
    public function testPartialObjectRequiredFieldCanBeFalsey() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            complexArgField(complexArg: { requiredField: false })
          }
        }
        '
        );
    }

    /**
     * @see it('Partial object, including required')
     */
    public function testPartialObjectIncludingRequired() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            complexArgField(complexArg: { requiredField: true, intField: 4 })
          }
        }
        '
        );
    }

    /**
     * @see it('Full object')
     */
    public function testFullObject() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
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
        '
        );
    }

    /**
     * @see it('Full object with fields in different order')
     */
    public function testFullObjectWithFieldsInDifferentOrder() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
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
        '
        );
    }

    /**
     * @see it('Partial object, missing required')
     */
    public function testPartialObjectMissingRequired() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            complexArgField(complexArg: { intField: 4 })
          }
        }
        ',
            [
                $this->requiredField('ComplexInput', 'requiredField', 'Boolean!', 4, 41),
            ]
        );
    }

    private function requiredField($typeName, $fieldName, $fieldTypeName, $line, $column)
    {
        return FormattedError::create(
            ValuesOfCorrectType::requiredFieldMessage(
                $typeName,
                $fieldName,
                $fieldTypeName
            ),
            [new SourceLocation($line, $column)]
        );
    }

    // DESCRIBE: Invalid input object value

    /**
     * @see it('Partial object, invalid field type')
     */
    public function testPartialObjectInvalidFieldType() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            complexArgField(complexArg: {
              stringListField: ["one", 2],
              requiredField: true,
            })
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "complexArgField" argument "complexArg" requires type String, found 2.', 5, 40),
            ]
        );
    }

    /**
     * @see it('Partial object, null to non-null field')
     */
    public function testPartialObjectNullToNonNullField()
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            complexArgField(complexArg: {
              requiredField: true,
              nonNullField: null,
            })
          }
        }
      ',
            [$this->badValueWithMessage('Field "complexArgField" argument "complexArg" requires type Boolean!, found null.', 6, 29)]
        );
    }

    /**
     * @see it('Partial object, unknown field arg')
     *
     * The sorting of equal elements has changed so that the test fails on php < 7
     *
     * @requires PHP 7.0
     */
    public function testPartialObjectUnknownFieldArg() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            complexArgField(complexArg: {
              requiredField: true,
              unknownField: "value"
            })
          }
        }
        ',
            [
                $this->unknownField(
                    'ComplexInput',
                    'unknownField',
                    6,
                    15,
                    'Did you mean nonNullField, intField, or booleanField?'
                ),
            ]
        );
    }

    private function unknownField($typeName, $fieldName, $line, $column, $message = null)
    {
        return FormattedError::create(
            ValuesOfCorrectType::unknownFieldMessage(
                $typeName,
                $fieldName,
                $message
            ),
            [new SourceLocation($line, $column)]
        );
    }

    /**
     * @see it('reports original error for custom scalar which throws')
     */
    public function testReportsOriginalErrorForCustomScalarWhichThrows() : void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          invalidArg(arg: 123)
        }
        ',
            [
                $this->badValueWithMessage('Field "invalidArg" argument "arg" requires type Invalid, found 123; Invalid scalar is always invalid: 123', 3, 27),
            ]
        );

        self::assertEquals(
            'Field "invalidArg" argument "arg" requires type Invalid, found 123; Invalid scalar is always invalid: 123',
            $errors[0]->getMessage()
        );
    }

    /**
     * @see it('allows custom scalar to accept complex literals')
     */
    public function testAllowsCustomScalarToAcceptComplexLiterals() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        {
          test1: anyArg(arg: 123)
          test2: anyArg(arg: "abc")
          test3: anyArg(arg: [123, "abc"])
          test4: anyArg(arg: {deep: [123, "abc"]})
        }
        '
        );
    }

    // DESCRIBE: Directive arguments

    /**
     * @see it('with directives of valid types')
     */
    public function testWithDirectivesOfValidTypes() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
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
     * @see it('with directive with incorrect types')
     */
    public function testWithDirectiveWithIncorrectTypes() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          dog @include(if: "yes") {
            name @skip(if: ENUM)
          }
        }
        ',
            [
                $this->badValueWithMessage('Field "dog" argument "if" requires type Boolean!, found "yes".', 3, 28),
                $this->badValueWithMessage('Field "name" argument "if" requires type Boolean!, found ENUM.', 4, 28),
            ]
        );
    }

    // DESCRIBE: Variable default values

    /**
     * @see it('variables with valid default values')
     */
    public function testVariablesWithValidDefaultValues() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        query WithDefaultValues(
          $a: Int = 1,
          $b: String = "ok",
          $c: ComplexInput = { requiredField: true, intField: 3 }
          $d: Int! = 123
        ) {
          dog { name }
        }
        '
        );
    }

    /**
     * @see it('variables with valid default null values')
     */
    public function testVariablesWithValidDefaultNullValues() : void
    {
        $this->expectPassesRule(
            new ValuesOfCorrectType(),
            '
        query WithDefaultValues(
          $a: Int = null,
          $b: String = null,
          $c: ComplexInput = { requiredField: true, intField: null }
        ) {
          dog { name }
        }
        '
        );
    }

    /**
     * @see it('variables with invalid default null values')
     */
    public function testVariablesWithInvalidDefaultNullValues() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        query WithDefaultValues(
          $a: Int! = null,
          $b: String! = null,
          $c: ComplexInput = { requiredField: null, intField: null }
        ) {
          dog { name }
        }
        ',
            [
                $this->badValue('Int!', 'null', 3, 22),
                $this->badValue('String!', 'null', 4, 25),
                $this->badValue('Boolean!', 'null', 5, 47),
            ]
        );
    }

    /**
     * @see it('variables with invalid default values')
     */
    public function testVariablesWithInvalidDefaultValues() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        query InvalidDefaultValues(
          $a: Int = "one",
          $b: String = 4,
          $c: ComplexInput = "notverycomplex"
        ) {
          dog { name }
        }
        ',
            [
                $this->badValue('Int', '"one"', 3, 21),
                $this->badValue('String', '4', 4, 24),
                $this->badValue('ComplexInput', '"notverycomplex"', 5, 30),
            ]
        );
    }

    /**
     * @see it('variables with complex invalid default values')
     */
    public function testVariablesWithComplexInvalidDefaultValues() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        query WithDefaultValues(
          $a: ComplexInput = { requiredField: 123, intField: "abc" }
        ) {
          dog { name }
        }
        ',
            [
                $this->badValue('Boolean!', '123', 3, 47),
                $this->badValue('Int', '"abc"', 3, 62),
            ]
        );
    }

    /**
     * @see it('complex variables missing required field')
     */
    public function testComplexVariablesMissingRequiredField() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        query MissingRequiredField($a: ComplexInput = {intField: 3}) {
          dog { name }
        }
        ',
            [
                $this->requiredField('ComplexInput', 'requiredField', 'Boolean!', 2, 55),
            ]
        );
    }

    /**
     * @see it('list variables with invalid item')
     */
    public function testListVariablesWithInvalidItem() : void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        query InvalidItem($a: [String] = ["one", 2]) {
          dog { name }
        }
        ',
            [
                $this->badValue('String', '2', 2, 50),
            ]
        );
    }
}
