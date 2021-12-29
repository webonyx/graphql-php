<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Validator\Rules\ValuesOfCorrectType;

/**
 * @phpstan-import-type ErrorArray from ErrorHelper
 */
class ValuesOfCorrectTypeTest extends ValidatorTestCase
{
    /**
     * @see it('Good int value')
     */
    public function testGoodIntValue(): void
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
    public function testGoodNegativeIntValue(): void
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
    public function testGoodBooleanValue(): void
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
    public function testGoodStringValue(): void
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
    public function testGoodFloatValue(): void
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

    public function testGoodNegativeFloatValue(): void
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
    public function testIntIntoFloat(): void
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
    public function testIntIntoID(): void
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
    public function testStringIntoID(): void
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
    public function testGoodEnumValue(): void
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
    public function testEnumWithNullValue(): void
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
    public function testNullIntoNullableType(): void
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
    public function testIntIntoString(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            stringArgField(stringArg: 1)
          }
        }
        ',
            [
                $this->badValueWithMessage('String cannot represent a non string value: 1', 4, 39),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @phpstan-return ErrorArray
     */
    private function badValueWithMessage(string $message, int $line, int $column): array
    {
        return ErrorHelper::create($message, [new SourceLocation($line, $column)]);
    }

    /**
     * @see it('Float into String')
     */
    public function testFloatIntoString(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            stringArgField(stringArg: 1.0)
          }
        }
        ',
            [
                $this->badValueWithMessage('String cannot represent a non string value: 1.0', 4, 39),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    // Invalid String values

    /**
     * @see it('Boolean into String')
     */
    public function testBooleanIntoString(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            stringArgField(stringArg: true)
          }
        }
        ',
            [
                $this->badValueWithMessage('String cannot represent a non string value: true', 4, 39),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('Unquoted String into String')
     */
    public function testUnquotedStringIntoString(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            stringArgField(stringArg: BAR)
          }
        }
        ',
            [
                $this->badValueWithMessage('String cannot represent a non string value: BAR', 4, 39),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('String into Int')
     */
    public function testStringIntoInt(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            intArgField(intArg: "3")
          }
        }
        ',
            [
                $this->badValueWithMessage('Int cannot represent non-integer value: "3"', 4, 33),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('Big Int into Int')
     */
    public function testBigIntIntoInt(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            intArgField(intArg: 829384293849283498239482938)
          }
        }
        ',
            [
                $this->badValueWithMessage('Int cannot represent non-integer value: 829384293849283498239482938', 4, 33),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    // Invalid Int values

    /**
     * @see it('Unquoted String into Int')
     */
    public function testUnquotedStringIntoInt(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            intArgField(intArg: FOO)
          }
        }
        ',
            [
                $this->badValueWithMessage('Int cannot represent non-integer value: FOO', 4, 33),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('Simple Float into Int')
     */
    public function testSimpleFloatIntoInt(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            intArgField(intArg: 3.0)
          }
        }
        ',
            [
                $this->badValueWithMessage('Int cannot represent non-integer value: 3.0', 4, 33),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('Float into Int')
     */
    public function testFloatIntoInt(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            intArgField(intArg: 3.333)
          }
        }
        ',
            [
                $this->badValueWithMessage('Int cannot represent non-integer value: 3.333', 4, 33),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('String into Float')
     */
    public function testStringIntoFloat(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            floatArgField(floatArg: "3.333")
          }
        }
        ',
            [
                $this->badValueWithMessage('Float cannot represent non numeric value: "3.333"', 4, 37),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('Boolean into Float')
     */
    public function testBooleanIntoFloat(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            floatArgField(floatArg: true)
          }
        }
        ',
            [
                $this->badValueWithMessage('Float cannot represent non numeric value: true', 4, 37),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    // Invalid Float values

    /**
     * @see it('Unquoted into Float')
     */
    public function testUnquotedIntoFloat(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            floatArgField(floatArg: FOO)
          }
        }
        ',
            [
                $this->badValueWithMessage('Float cannot represent non numeric value: FOO', 4, 37),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('Int into Boolean')
     */
    public function testIntIntoBoolean(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            booleanArgField(booleanArg: 2)
          }
        }
        ',
            [
                $this->badValueWithMessage('Boolean cannot represent a non boolean value: 2', 4, 41),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('Float into Boolean')
     */
    public function testFloatIntoBoolean(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            booleanArgField(booleanArg: 1.0)
          }
        }
        ',
            [
                $this->badValueWithMessage('Boolean cannot represent a non boolean value: 1.0', 4, 41),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    // Invalid Boolean value

    /**
     * @see it('String into Boolean')
     */
    public function testStringIntoBoolean(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            booleanArgField(booleanArg: "true")
          }
        }
        ',
            [
                $this->badValueWithMessage('Boolean cannot represent a non boolean value: "true"', 4, 41),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('Unquoted into Boolean')
     */
    public function testUnquotedIntoBoolean(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            booleanArgField(booleanArg: TRUE)
          }
        }
        ',
            [
                $this->badValueWithMessage('Boolean cannot represent a non boolean value: TRUE', 4, 41),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('Float into ID')
     */
    public function testFloatIntoID(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            idArgField(idArg: 1.0)
          }
        }
        ',
            [
                $this->badValueWithMessage('ID cannot represent a non-string and non-integer value: 1.0', 4, 31),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('Boolean into ID')
     */
    public function testBooleanIntoID(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            idArgField(idArg: true)
          }
        }
        ',
            [
                $this->badValueWithMessage('ID cannot represent a non-string and non-integer value: true', 4, 31),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    // Invalid ID value

    /**
     * @see it('Unquoted into ID')
     */
    public function testUnquotedIntoID(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            idArgField(idArg: SOMETHING)
          }
        }
        ',
            [
                $this->badValueWithMessage('ID cannot represent a non-string and non-integer value: SOMETHING', 4, 31),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('Int into Enum')
     */
    public function testIntIntoEnum(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          dog {
            doesKnowCommand(dogCommand: 2)
          }
        }
        ',
            [
                $this->badValueWithMessage('Enum "DogCommand" cannot represent non-enum value: 2.', 4, 41),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('Float into Enum')
     */
    public function testFloatIntoEnum(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          dog {
            doesKnowCommand(dogCommand: 1.0)
          }
        }
        ',
            [
                $this->badValueWithMessage('Enum "DogCommand" cannot represent non-enum value: 1.0.', 4, 41),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    // Invalid Enum value

    /**
     * @see it('String into Enum')
     */
    public function testStringIntoEnum(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          dog {
            doesKnowCommand(dogCommand: "SIT")
          }
        }
        ',
            [
                $this->badValueWithMessage('Enum "DogCommand" cannot represent non-enum value: "SIT". Did you mean the enum value "SIT"?', 4, 41),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('Boolean into Enum')
     */
    public function testBooleanIntoEnum(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          dog {
            doesKnowCommand(dogCommand: true)
          }
        }
        ',
            [
                $this->badValueWithMessage('Enum "DogCommand" cannot represent non-enum value: true.', 4, 41),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('Unknown Enum Value into Enum')
     */
    public function testUnknownEnumValueIntoEnum(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          dog {
            doesKnowCommand(dogCommand: JUGGLE)
          }
        }
        ',
            [
                $this->badValueWithMessage('Value "JUGGLE" does not exist in "DogCommand" enum.', 4, 41),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('Different case Enum Value into Enum')
     */
    public function testDifferentCaseEnumValueIntoEnum(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          dog {
            doesKnowCommand(dogCommand: sit)
          }
        }
        ',
            [
                $this->badValueWithMessage('Value "sit" does not exist in "DogCommand" enum. Did you mean the enum value "SIT"?', 4, 41),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('Good list value')
     */
    public function testGoodListValue(): void
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
    public function testEmptyListValue(): void
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
    public function testNullValue(): void
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
    public function testSingleValueIntoList(): void
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
    public function testIncorrectItemtype(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            stringListArgField(stringListArg: ["one", 2])
          }
        }
        ',
            [
                $this->badValueWithMessage('String cannot represent a non string value: 2', 4, 55),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('Single value of incorrect type')
     */
    public function testSingleValueOfIncorrectType(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            stringListArgField(stringListArg: 1)
          }
        }
        ',
            [
                $this->badValueWithMessage('String cannot represent a non string value: 1', 4, 47),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    // Invalid List value

    /**
     * @see it('Arg on optional arg')
     */
    public function testArgOnOptionalArg(): void
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
    public function testNoArgOnOptionalArg(): void
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
    public function testMultipleArgs(): void
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
    public function testMultipleArgsReverseOrder(): void
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
    public function testNoArgsOnMultipleOptional(): void
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
    public function testOneArgOnMultipleOptional(): void
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
    public function testSecondArgOnMultipleOptional(): void
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
    public function testMultipleReqsOnMixedList(): void
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
    public function testMultipleReqsAndOneOptOnMixedList(): void
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
    public function testAllReqsAndOptsOnMixedList(): void
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
    public function testIncorrectValueType(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            multipleReqs(req2: "two", req1: "one")
          }
        }
        ',
            [
                $this->badValueWithMessage('Int cannot represent non-integer value: "two"', 4, 32),
                $this->badValueWithMessage('Int cannot represent non-integer value: "one"', 4, 45),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
        self::assertTrue($errors[1]->isClientSafe());
    }

    /**
     * @see it('Incorrect value and missing argument (ProvidedRequiredArgumentsRule)')
     */
    public function testIncorrectValueAndMissingArgumentProvidedRequiredArgumentsRule(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            multipleReqs(req1: "one")
          }
        }
        ',
            [
                $this->badValueWithMessage('Int cannot represent non-integer value: "one"', 4, 32),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    // Invalid non-nullable value

    /**
     * @see it('Null value')
     */
    public function testNullValue2(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            multipleReqs(req1: null)
          }
        }
        ',
            [
                $this->badValueWithMessage('Expected value of type "Int!", found null.', 4, 32),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('Optional arg, despite required field in type')
     */
    public function testOptionalArgDespiteRequiredFieldInType(): void
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
    public function testPartialObjectOnlyRequired(): void
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
    public function testPartialObjectRequiredFieldCanBeFalsey(): void
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
    public function testPartialObjectIncludingRequired(): void
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
    public function testFullObject(): void
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
    public function testFullObjectWithFieldsInDifferentOrder(): void
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
    public function testPartialObjectMissingRequired(): void
    {
        $errors = $this->expectFailsRule(
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

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @phpstan-return ErrorArray
     */
    private function requiredField(string $typeName, string $fieldName, string $fieldTypeName, int $line, int $column): array
    {
        return ErrorHelper::create(
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
    public function testPartialObjectInvalidFieldType(): void
    {
        $errors = $this->expectFailsRule(
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
                $this->badValueWithMessage('String cannot represent a non string value: 2', 5, 40),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('Partial object, null to non-null field')
     */
    public function testPartialObjectNullToNonNullField(): void
    {
        $errors = $this->expectFailsRule(
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
            [$this->badValueWithMessage('Expected value of type "Boolean!", found null.', 6, 29)]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('Partial object, unknown field arg')
     */
    public function testPartialObjectUnknownFieldArg(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          complicatedArgs {
            complexArgField(complexArg: {
              requiredField: true,
              invalidField: "value"
            })
          }
        }
        ',
            [
                [
                    'message' => 'Field "invalidField" is not defined by type "ComplexInput". Did you mean "intField"?',
                    'locations' => [['line' => 6, 'column' => 15]],
                ],
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('reports original error for custom scalar which throws')
     */
    public function testReportsOriginalErrorForCustomScalarWhichThrows(): void
    {
        $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          invalidArg(arg: 123)
        }
        ',
            [
                $this->badValueWithMessage('Expected value of type "Invalid", found 123; Invalid scalar is always invalid: 123', 3, 27),
            ]
        );
    }

    /**
     * @see it('allows custom scalar to accept complex literals')
     */
    public function testAllowsCustomScalarToAcceptComplexLiterals(): void
    {
        $customScalar = new CustomScalarType(['name' => 'Any']);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'anyArg' => [
                        'type' => Type::string(),
                        'args' => [
                            'arg' => $customScalar,
                        ],
                    ],
                ],
            ]),
        ]);

        $this->expectPassesRuleWithSchema(
            $schema,
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
    public function testWithDirectivesOfValidTypes(): void
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
    public function testWithDirectiveWithIncorrectTypes(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        {
          dog @include(if: "yes") {
            name @skip(if: ENUM)
          }
        }
        ',
            [
                $this->badValueWithMessage('Boolean cannot represent a non boolean value: "yes"', 3, 28),
                $this->badValueWithMessage('Boolean cannot represent a non boolean value: ENUM', 4, 28),
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
        self::assertTrue($errors[1]->isClientSafe());
    }

    // DESCRIBE: Variable default values

    /**
     * @see it('variables with valid default values')
     */
    public function testVariablesWithValidDefaultValues(): void
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
    public function testVariablesWithValidDefaultNullValues(): void
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
    public function testVariablesWithInvalidDefaultNullValues(): void
    {
        $errors = $this->expectFailsRule(
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
                [
                    'message' => 'Expected value of type "Int!", found null.',
                    'locations' => [['line' => 3, 'column' => 22]],
                ],
                [
                    'message' => 'Expected value of type "String!", found null.',
                    'locations' => [['line' => 4, 'column' => 25]],
                ],
                [
                    'message' => 'Expected value of type "Boolean!", found null.',
                    'locations' => [['line' => 5, 'column' => 47]],
                ],
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
        self::assertTrue($errors[1]->isClientSafe());
        self::assertTrue($errors[2]->isClientSafe());
    }

    /**
     * @see it('variables with invalid default values')
     */
    public function testVariablesWithInvalidDefaultValues(): void
    {
        $errors = $this->expectFailsRule(
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
                [
                    'message' => 'Int cannot represent non-integer value: "one"',
                    'locations' => [['line' => 3, 'column' => 21]],
                ],
                [
                    'message' => 'String cannot represent a non string value: 4',
                    'locations' => [['line' => 4, 'column' => 24]],
                ],
                [
                    'message' => 'Expected value of type "ComplexInput", found "notverycomplex".',
                    'locations' => [['line' => 5, 'column' => 30]],
                ],
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('variables with complex invalid default values')
     */
    public function testVariablesWithComplexInvalidDefaultValues(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        query WithDefaultValues(
          $a: ComplexInput = { requiredField: 123, intField: "abc" }
        ) {
          dog { name }
        }
        ',
            [
                [
                    'message' => 'Boolean cannot represent a non boolean value: 123',
                    'locations' => [['line' => 3, 'column' => 47]],
                ],
                [
                    'message' => 'Int cannot represent non-integer value: "abc"',
                    'locations' => [['line' => 3, 'column' => 62]],
                ],
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('complex variables missing required field')
     */
    public function testComplexVariablesMissingRequiredField(): void
    {
        $errors = $this->expectFailsRule(
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

        self::assertTrue($errors[0]->isClientSafe());
    }

    /**
     * @see it('list variables with invalid item')
     */
    public function testListVariablesWithInvalidItem(): void
    {
        $errors = $this->expectFailsRule(
            new ValuesOfCorrectType(),
            '
        query InvalidItem($a: [String] = ["one", 2]) {
          dog { name }
        }
        ',
            [
                [
                    'message' => 'String cannot represent a non string value: 2',
                    'locations' => [['line' => 2, 'column' => 50]],
                ],
            ]
        );

        self::assertTrue($errors[0]->isClientSafe());
    }
}
