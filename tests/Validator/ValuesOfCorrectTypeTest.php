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
     * @it Good int value
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
     * @it Good negative int value
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
     * @it Good boolean value
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
     * @it Good string value
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
     * @it Good float value
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
     * @it Int into Float
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
     * @it Int into ID
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
     * @it String into ID
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
     * @it Good enum value
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
     * @it Enum with null value
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
     * @it null into nullable type
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
     * @it Int into String
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
     * @it Float into String
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
     * @it Boolean into String
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
     * @it Unquoted String into String
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
     * @it String into Int
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
     * @it Big Int into Int
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
     * @it Unquoted String into Int
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
     * @it Simple Float into Int
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
     * @it Float into Int
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
     * @it String into Float
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
     * @it Boolean into Float
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
     * @it Unquoted into Float
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
     * @it Int into Boolean
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
     * @it Float into Boolean
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
     * @it String into Boolean
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
     * @it Unquoted into Boolean
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
     * @it Float into ID
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
     * @it Boolean into ID
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
     * @it Unquoted into ID
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
     * @it Int into Enum
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
     * @it Float into Enum
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
     * @it String into Enum
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
     * @it Boolean into Enum
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
     * @it Unknown Enum Value into Enum
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
     * @it Different case Enum Value into Enum
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
     * @it Good list value
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
     * @it Empty list value
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
     * @it Null value
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
     * @it Single value into List
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
     * @it Incorrect item type
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
     * @it Single value of incorrect type
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
     * @it Arg on optional arg
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
     * @it No Arg on optional arg
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
     * @it Multiple args
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
     * @it Multiple args reverse order
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
     * @it No args on multiple optional
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
     * @it One arg on multiple optional
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
     * @it Second arg on multiple optional
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
     * @it Multiple reqs on mixedList
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
     * @it Multiple reqs and one opt on mixedList
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
     * @it All reqs and opts on mixedList
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
     * @it Incorrect value type
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
     * @it Incorrect value and missing argument (ProvidedNonNullArguments)
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
     * @it Null value
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
     * @it Optional arg, despite required field in type
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
     * @it Partial object, only required
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
     * @it Partial object, required field can be falsey
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
     * @it Partial object, including required
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
     * @it Full object
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
     * @it Full object with fields in different order
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
     * @it Partial object, missing required
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
     * @it Partial object, invalid field type
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
     * @it Partial object, unknown field arg
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
     * @it reports original error for custom scalar which throws
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
     * @it allows custom scalar to accept complex literals
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
     * @it with directives of valid types
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
     * @it with directive with incorrect types
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
     * @it variables with valid default values
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
     * @it variables with valid default null values
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
     * @it variables with invalid default null values
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
     * @it variables with invalid default values
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
     * @it variables with complex invalid default values
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
     * @it complex variables missing required field
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
     * @it list variables with invalid item
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
