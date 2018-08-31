<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\ValuesOfCorrectType;

class ValuesOfCorrectTypeTest extends ValidatorTestCase
{
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

    private function requiredField($typeName, $fieldName, $fieldTypeName, $line, $column) {
        return FormattedError::create(
            ValuesOfCorrectType::requiredFieldMessage(
                $typeName,
                $fieldName,
                $fieldTypeName
            ),
            [new SourceLocation($line, $column)]
        );
    }

    private function unknownField($typeName, $fieldName, $line, $column, $message = null) {
        return FormattedError::create(
            ValuesOfCorrectType::unknownFieldMessage(
                $typeName,
                $fieldName,
                $message
            ),
            [new SourceLocation($line, $column)]
        );
    }

    // Validate: Values of correct type
    // Valid values

    /**
     * @see it('Good int value')
     */
    public function testGoodIntValue()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            intArgField(intArg: 2)
          }
        }
        ');
    }

    /**
     * @see it('Good negative int value')
     */
    public function testGoodNegativeIntValue()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            intArgField(intArg: -2)
          }
        }
        ');
    }

    /**
     * @see it('Good boolean value')
     */
    public function testGoodBooleanValue()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            booleanArgField(booleanArg: true)
          }
        }
      ');
    }

    /**
     * @see it('Good string value')
     */
    public function testGoodStringValue()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            stringArgField(stringArg: "foo")
          }
        }
        ');
    }

    /**
     * @see it('Good float value')
     */
    public function testGoodFloatValue()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            floatArgField(floatArg: 1.1)
          }
        }
        ');
    }

    public function testGoodNegativeFloatValue()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            floatArgField(floatArg: -1.1)
          }
        }
        ');
    }

    /**
     * @see it('Int into Float')
     */
    public function testIntIntoFloat()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            floatArgField(floatArg: 1)
          }
        }
        ');
    }

    /**
     * @see it('Int into ID')
     */
    public function testIntIntoID()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            idArgField(idArg: 1)
          }
        }
        ');
    }

    /**
     * @see it('String into ID')
     */
    public function testStringIntoID()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            idArgField(idArg: "someIdString")
          }
        }
        ');
    }

    /**
     * @see it('Good enum value')
     */
    public function testGoodEnumValue()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          dog {
            doesKnowCommand(dogCommand: SIT)
          }
        }
        ');
    }

    /**
     * @see it('Enum with null value')
     */
    public function testEnumWithNullValue()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            enumArgField(enumArg: NO_FUR)
          }
        }
        ');
    }

    /**
     * @see it('null into nullable type')
     */
    public function testNullIntoNullableType()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            intArgField(intArg: null)
          }
        }
        ');

        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          dog(a: null, b: null, c:{ requiredField: true, intField: null }) {
            name
          }
        }
        ');
    }

    // Invalid String values

    /**
     * @see it('Int into String')
     */
    public function testIntIntoString()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            stringArgField(stringArg: 1)
          }
        }
        ', [
            $this->badValue('String', '1', 4, 39)
        ]);
    }

    /**
     * @see it('Float into String')
     */
    public function testFloatIntoString()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            stringArgField(stringArg: 1.0)
          }
        }
        ', [
            $this->badValue('String', '1.0', 4, 39)
        ]);
    }

    /**
     * @see it('Boolean into String')
     */
    public function testBooleanIntoString()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            stringArgField(stringArg: true)
          }
        }
        ', [
            $this->badValue('String', 'true', 4, 39)
        ]);
    }

    /**
     * @see it('Unquoted String into String')
     */
    public function testUnquotedStringIntoString()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            stringArgField(stringArg: BAR)
          }
        }
        ', [
            $this->badValue('String', 'BAR', 4, 39)
        ]);
    }

    // Invalid Int values

    /**
     * @see it('String into Int')
     */
    public function testStringIntoInt()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            intArgField(intArg: "3")
          }
        }
        ', [
            $this->badValue('Int', '"3"', 4, 33)
        ]);
    }

    /**
     * @see it('Big Int into Int')
     */
    public function testBigIntIntoInt()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            intArgField(intArg: 829384293849283498239482938)
          }
        }
        ', [
            $this->badValue('Int', '829384293849283498239482938', 4, 33)
        ]);
    }

    /**
     * @see it('Unquoted String into Int')
     */
    public function testUnquotedStringIntoInt()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            intArgField(intArg: FOO)
          }
        }
        ', [
            $this->badValue('Int', 'FOO', 4, 33)
        ]);
    }

    /**
     * @see it('Simple Float into Int')
     */
    public function testSimpleFloatIntoInt()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            intArgField(intArg: 3.0)
          }
        }
        ', [
            $this->badValue('Int', '3.0', 4, 33)
        ]);
    }

    /**
     * @see it('Float into Int')
     */
    public function testFloatIntoInt()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            intArgField(intArg: 3.333)
          }
        }
        ', [
            $this->badValue('Int', '3.333', 4, 33)
        ]);
    }

    // Invalid Float values

    /**
     * @see it('String into Float')
     */
    public function testStringIntoFloat()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            floatArgField(floatArg: "3.333")
          }
        }
        ', [
            $this->badValue('Float', '"3.333"', 4, 37)
        ]);
    }

    /**
     * @see it('Boolean into Float')
     */
    public function testBooleanIntoFloat()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            floatArgField(floatArg: true)
          }
        }
        ', [
            $this->badValue('Float', 'true', 4, 37)
        ]);
    }

    /**
     * @see it('Unquoted into Float')
     */
    public function testUnquotedIntoFloat()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            floatArgField(floatArg: FOO)
          }
        }
        ', [
            $this->badValue('Float', 'FOO', 4, 37)
        ]);
    }

    // Invalid Boolean value

    /**
     * @see it('Int into Boolean')
     */
    public function testIntIntoBoolean()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            booleanArgField(booleanArg: 2)
          }
        }
        ', [
            $this->badValue('Boolean', '2', 4, 41)
        ]);
    }

    /**
     * @see it('Float into Boolean')
     */
    public function testFloatIntoBoolean()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            booleanArgField(booleanArg: 1.0)
          }
        }
        ', [
            $this->badValue('Boolean', '1.0', 4, 41)
        ]);
    }

    /**
     * @see it('String into Boolean')
     */
    public function testStringIntoBoolean()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            booleanArgField(booleanArg: "true")
          }
        }
        ', [
            $this->badValue('Boolean', '"true"', 4, 41)
        ]);
    }

    /**
     * @see it('Unquoted into Boolean')
     */
    public function testUnquotedIntoBoolean()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            booleanArgField(booleanArg: TRUE)
          }
        }
        ', [
            $this->badValue('Boolean', 'TRUE', 4, 41)
        ]);
    }

    // Invalid ID value

    /**
     * @see it('Float into ID')
     */
    public function testFloatIntoID()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            idArgField(idArg: 1.0)
          }
        }
        ', [
            $this->badValue('ID', '1.0', 4, 31)
        ]);
    }

    /**
     * @see it('Boolean into ID')
     */
    public function testBooleanIntoID()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            idArgField(idArg: true)
          }
        }
        ', [
            $this->badValue('ID', 'true', 4, 31)
        ]);
    }

    /**
     * @see it('Unquoted into ID')
     */
    public function testUnquotedIntoID()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            idArgField(idArg: SOMETHING)
          }
        }
        ', [
            $this->badValue('ID', 'SOMETHING', 4, 31)
        ]);
    }

    // Invalid Enum value

    /**
     * @see it('Int into Enum')
     */
    public function testIntIntoEnum()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          dog {
            doesKnowCommand(dogCommand: 2)
          }
        }
        ', [
            $this->badValue('DogCommand', '2', 4, 41)
        ]);
    }

    /**
     * @see it('Float into Enum')
     */
    public function testFloatIntoEnum()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          dog {
            doesKnowCommand(dogCommand: 1.0)
          }
        }
        ', [
            $this->badValue('DogCommand', '1.0', 4, 41)
        ]);
    }

    /**
     * @see it('String into Enum')
     */
    public function testStringIntoEnum()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          dog {
            doesKnowCommand(dogCommand: "SIT")
          }
        }
        ', [
            $this->badValue(
                'DogCommand',
                '"SIT"',
                4,
                41,
                'Did you mean the enum value SIT?'
            )
        ]);
    }

    /**
     * @see it('Boolean into Enum')
     */
    public function testBooleanIntoEnum()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          dog {
            doesKnowCommand(dogCommand: true)
          }
        }
        ', [
            $this->badValue('DogCommand', 'true', 4, 41)
        ]);
    }

    /**
     * @see it('Unknown Enum Value into Enum')
     */
    public function testUnknownEnumValueIntoEnum()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          dog {
            doesKnowCommand(dogCommand: JUGGLE)
          }
        }
        ', [
            $this->badValue('DogCommand', 'JUGGLE', 4, 41)
        ]);
    }

    /**
     * @see it('Different case Enum Value into Enum')
     */
    public function testDifferentCaseEnumValueIntoEnum()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          dog {
            doesKnowCommand(dogCommand: sit)
          }
        }
        ', [
            $this->badValue(
                'DogCommand',
                'sit',
                4,
                41,
                'Did you mean the enum value SIT?'
            )
        ]);
    }

    // Valid List value

    /**
     * @see it('Good list value')
     */
    public function testGoodListValue()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            stringListArgField(stringListArg: ["one", null, "two"])
          }
        }
        ');
    }

    /**
     * @see it('Empty list value')
     */
    public function testEmptyListValue()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            stringListArgField(stringListArg: [])
          }
        }
        ');
    }

    /**
     * @see it('Null value')
     */
    public function testNullValue()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            stringListArgField(stringListArg: null)
          }
        }
        ');
    }

    /**
     * @see it('Single value into List')
     */
    public function testSingleValueIntoList()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            stringListArgField(stringListArg: "one")
          }
        }
        ');
    }

    // Invalid List value

    /**
     * @see it('Incorrect item type')
     */
    public function testIncorrectItemtype()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            stringListArgField(stringListArg: ["one", 2])
          }
        }
        ', [
            $this->badValue('String', '2', 4, 55),
        ]);
    }

    /**
     * @see it('Single value of incorrect type')
     */
    public function testSingleValueOfIncorrectType()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            stringListArgField(stringListArg: 1)
          }
        }
        ', [
            $this->badValue('[String]', '1', 4, 47),
        ]);
    }

    // Valid non-nullable value

    /**
     * @see it('Arg on optional arg')
     */
    public function testArgOnOptionalArg()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
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
        $this->expectPassesRule(new ValuesOfCorrectType, '
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
        $this->expectPassesRule(new ValuesOfCorrectType, '
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
        $this->expectPassesRule(new ValuesOfCorrectType, '
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
        $this->expectPassesRule(new ValuesOfCorrectType, '
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
        $this->expectPassesRule(new ValuesOfCorrectType, '
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
        $this->expectPassesRule(new ValuesOfCorrectType, '
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
        $this->expectPassesRule(new ValuesOfCorrectType, '
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
        $this->expectPassesRule(new ValuesOfCorrectType, '
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
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            multipleOptAndReq(req1: 3, req2: 4, opt1: 5, opt2: 6)
          }
        }
        ');
    }

    // Invalid non-nullable value

    /**
     * @see it('Incorrect value type')
     */
    public function testIncorrectValueType()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            multipleReqs(req2: "two", req1: "one")
          }
        }
        ', [
            $this->badValue('Int!', '"two"', 4, 32),
            $this->badValue('Int!', '"one"', 4, 45),
        ]);
    }

    /**
     * @see it('Incorrect value and missing argument (ProvidedNonNullArguments)')
     */
    public function testIncorrectValueAndMissingArgumentProvidedNonNullArguments()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            multipleReqs(req1: "one")
          }
        }
        ', [
            $this->badValue('Int!', '"one"', 4, 32),
        ]);
    }

    /**
     * @see it('Null value')
     */
    public function testNullValue2()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            multipleReqs(req1: null)
          }
        }
        ', [
            $this->badValue('Int!', 'null', 4, 32),
        ]);
    }


    // DESCRIBE: Valid input object value

    /**
     * @see it('Optional arg, despite required field in type')
     */
    public function testOptionalArgDespiteRequiredFieldInType()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            complexArgField
          }
        }
        ');
    }

    /**
     * @see it('Partial object, only required')
     */
    public function testPartialObjectOnlyRequired()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            complexArgField(complexArg: { requiredField: true })
          }
        }
        ');
    }

    /**
     * @see it('Partial object, required field can be falsey')
     */
    public function testPartialObjectRequiredFieldCanBeFalsey()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            complexArgField(complexArg: { requiredField: false })
          }
        }
        ');
    }

    /**
     * @see it('Partial object, including required')
     */
    public function testPartialObjectIncludingRequired()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            complexArgField(complexArg: { requiredField: true, intField: 4 })
          }
        }
        ');
    }

    /**
     * @see it('Full object')
     */
    public function testFullObject()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
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
     * @see it('Full object with fields in different order')
     */
    public function testFullObjectWithFieldsInDifferentOrder()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
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

    // DESCRIBE: Invalid input object value

    /**
     * @see it('Partial object, missing required')
     */
    public function testPartialObjectMissingRequired()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            complexArgField(complexArg: { intField: 4 })
          }
        }
        ', [
            $this->requiredField('ComplexInput', 'requiredField', 'Boolean!', 4, 41),
        ]);
    }

    /**
     * @see it('Partial object, invalid field type')
     */
    public function testPartialObjectInvalidFieldType()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            complexArgField(complexArg: {
              stringListField: ["one", 2],
              requiredField: true,
            })
          }
        }
        ', [
            $this->badValue('String', '2', 5, 40),
        ]);
    }

    /**
     * @see it('Partial object, unknown field arg')
     *
     * The sorting of equal elements has changed so that the test fails on php < 7
     * @requires PHP 7.0
     */
    public function testPartialObjectUnknownFieldArg()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          complicatedArgs {
            complexArgField(complexArg: {
              requiredField: true,
              unknownField: "value"
            })
          }
        }
        ', [
            $this->unknownField(
                'ComplexInput',
                'unknownField',
                6,
                15,
                'Did you mean intField or booleanField?'
            ),
        ]);
    }



    /**
     * @see it('reports original error for custom scalar which throws')
     */
    public function testReportsOriginalErrorForCustomScalarWhichThrows()
    {
        $errors = $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          invalidArg(arg: 123)
        }
        ', [
            $this->badValue(
                'Invalid',
                '123',
                3,
                27,
                'Invalid scalar is always invalid: 123'
            ),
        ]);

        $this->assertEquals(
            'Invalid scalar is always invalid: 123',
            $errors[0]->getPrevious()->getMessage()
        );
    }

    /**
     * @see it('allows custom scalar to accept complex literals')
     */
    public function testAllowsCustomScalarToAcceptComplexLiterals()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        {
          test1: anyArg(arg: 123)
          test2: anyArg(arg: "abc")
          test3: anyArg(arg: [123, "abc"])
          test4: anyArg(arg: {deep: [123, "abc"]})
        }
        ');
    }

    // DESCRIBE: Directive arguments

    /**
     * @see it('with directives of valid types')
     */
    public function testWithDirectivesOfValidTypes()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
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
     * @see it('with directive with incorrect types')
     */
    public function testWithDirectiveWithIncorrectTypes()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        {
          dog @include(if: "yes") {
            name @skip(if: ENUM)
          }
        }
        ', [
            $this->badValue('Boolean!', '"yes"', 3, 28),
            $this->badValue('Boolean!', 'ENUM', 4, 28),
        ]);
    }

    // DESCRIBE: Variable default values

    /**
     * @see it('variables with valid default values')
     */
    public function testVariablesWithValidDefaultValues()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        query WithDefaultValues(
          $a: Int = 1,
          $b: String = "ok",
          $c: ComplexInput = { requiredField: true, intField: 3 }
        ) {
          dog { name }
        }
        ');
    }

    /**
     * @see it('variables with valid default null values')
     */
    public function testVariablesWithValidDefaultNullValues()
    {
        $this->expectPassesRule(new ValuesOfCorrectType, '
        query WithDefaultValues(
          $a: Int = null,
          $b: String = null,
          $c: ComplexInput = { requiredField: true, intField: null }
        ) {
          dog { name }
        }
        ');
    }

    /**
     * @see it('variables with invalid default null values')
     */
    public function testVariablesWithInvalidDefaultNullValues()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        query WithDefaultValues(
          $a: Int! = null,
          $b: String! = null,
          $c: ComplexInput = { requiredField: null, intField: null }
        ) {
          dog { name }
        }
        ', [
            $this->badValue('Int!', 'null', 3, 22),
            $this->badValue('String!', 'null', 4, 25),
            $this->badValue('Boolean!', 'null', 5, 47),
        ]);
    }

    /**
     * @see it('variables with invalid default values')
     */
    public function testVariablesWithInvalidDefaultValues()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        query InvalidDefaultValues(
          $a: Int = "one",
          $b: String = 4,
          $c: ComplexInput = "notverycomplex"
        ) {
          dog { name }
        }
        ', [
            $this->badValue('Int', '"one"', 3, 21),
            $this->badValue('String', '4', 4, 24),
            $this->badValue('ComplexInput', '"notverycomplex"', 5, 30),
        ]);
    }

    /**
     * @see it('variables with complex invalid default values')
     */
    public function testVariablesWithComplexInvalidDefaultValues()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        query WithDefaultValues(
          $a: ComplexInput = { requiredField: 123, intField: "abc" }
        ) {
          dog { name }
        }
        ', [
            $this->badValue('Boolean!', '123', 3, 47),
            $this->badValue('Int', '"abc"', 3, 62),
        ]);
    }

    /**
     * @see it('complex variables missing required field')
     */
    public function testComplexVariablesMissingRequiredField()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        query MissingRequiredField($a: ComplexInput = {intField: 3}) {
          dog { name }
        }
        ', [
            $this->requiredField('ComplexInput', 'requiredField', 'Boolean!', 2, 55),
        ]);
    }

    /**
     * @see it('list variables with invalid item')
     */
    public function testListVariablesWithInvalidItem()
    {
        $this->expectFailsRule(new ValuesOfCorrectType, '
        query InvalidItem($a: [String] = ["one", 2]) {
          dog { name }
        }
        ', [
            $this->badValue('String', '2', 2, 50),
        ]);
    }
}
