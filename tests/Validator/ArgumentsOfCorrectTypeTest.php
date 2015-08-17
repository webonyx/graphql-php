<?php
namespace GraphQL\Validator;

use GraphQL\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\ArgumentsOfCorrectType;

class ArgumentsOfCorrectTypeTest extends TestCase
{
    function missingArg($fieldName, $argName, $typeName, $line, $column)
    {
        return FormattedError::create(
            Messages::missingArgMessage($fieldName, $argName, $typeName),
            [new SourceLocation($line, $column)]
        );
    }

    function badValue($argName, $typeName, $value, $line, $column)
    {
        return FormattedError::create(
            ArgumentsOfCorrectType::badValueMessage($argName, $typeName, $value),
            [new SourceLocation($line, $column)]
        );
    }

    // Validate: Argument values of correct type
    // Valid values:

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

    // Invalid String values
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
    public function testIncorrectItemtype()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            stringListArgField(stringListArg: ["one", 2])
          }
        }
        ', [
            $this->badValue('stringListArg', '[String]', '["one", 2]', 4, 47),
        ]);
    }

    public function testSingleValueOfIncorrectType()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType(), '
        {
          complicatedArgs {
            stringListArgField(stringListArg: 1)
          }
        }
        ', [
            $this->badValue('stringListArg', '[String]', '1', 4, 47),
        ]);
    }

    // Valid non-nullable value
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
    public function testIncorrectValueType()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType, '
        {
          complicatedArgs {
            multipleReqs(req2: "two", req1: "one")
          }
        }
        ', [
            $this->badValue('req2', 'Int!', '"two"', 4, 32),
            $this->badValue('req1', 'Int!', '"one"', 4, 45),
        ]);
    }

    public function testIncorrectValueAndMissingArgument()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType, '
        {
          complicatedArgs {
            multipleReqs(req1: "one")
          }
        }
        ', [
            $this->badValue('req1', 'Int!', '"one"', 4, 32),
        ]);
    }


    // Valid input object value
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
    public function testPartialObjectMissingRequired()
    {
        $this->expectFailsRule(new ArgumentsOfCorrectType, '
        {
          complicatedArgs {
            complexArgField(complexArg: { intField: 4 })
          }
        }
        ', [
            $this->badValue('complexArg', 'ComplexInput', '{intField: 4}', 4, 41),
        ]);
    }

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
                41
            ),
        ]);
    }

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
                41
            ),
        ]);
    }
}
