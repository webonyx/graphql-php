<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\UniqueArgumentNames;

class UniqueArgumentNamesTest extends ValidatorTestCase
{
    // Validate: Unique argument names

    /**
     * @see it('no arguments on field')
     */
    public function testNoArgumentsOnField()
    {
        $this->expectPassesRule(new UniqueArgumentNames(), '
      {
        field
      }
        ');
    }

    /**
     * @see it('no arguments on directive')
     */
    public function testNoArgumentsOnDirective()
    {
        $this->expectPassesRule(new UniqueArgumentNames, '
      {
        field @directive
      }
        ');
    }

    /**
     * @see it('argument on field')
     */
    public function testArgumentOnField()
    {
        $this->expectPassesRule(new UniqueArgumentNames, '
      {
        field(arg: "value")
      }
        ');
    }

    /**
     * @see it('argument on directive')
     */
    public function testArgumentOnDirective()
    {
        $this->expectPassesRule(new UniqueArgumentNames, '
      {
        field @directive(arg: "value")
      }
        ');
    }

    /**
     * @see it('same argument on two fields')
     */
    public function testSameArgumentOnTwoFields()
    {
        $this->expectPassesRule(new UniqueArgumentNames, '
      {
        one: field(arg: "value")
        two: field(arg: "value")
      }
        ');
    }

    /**
     * @see it('same argument on field and directive')
     */
    public function testSameArgumentOnFieldAndDirective()
    {
        $this->expectPassesRule(new UniqueArgumentNames, '
      {
        field(arg: "value") @directive(arg: "value")
      }
        ');
    }

    /**
     * @see it('same argument on two directives')
     */
    public function testSameArgumentOnTwoDirectives()
    {
        $this->expectPassesRule(new UniqueArgumentNames, '
      {
        field @directive1(arg: "value") @directive2(arg: "value")
      }
        ');
    }

    /**
     * @see it('multiple field arguments')
     */
    public function testMultipleFieldArguments()
    {
        $this->expectPassesRule(new UniqueArgumentNames, '
      {
        field(arg1: "value", arg2: "value", arg3: "value")
      }
        ');
    }

    /**
     * @see it('multiple directive arguments')
     */
    public function testMultipleDirectiveArguments()
    {
        $this->expectPassesRule(new UniqueArgumentNames, '
      {
        field @directive(arg1: "value", arg2: "value", arg3: "value")
      }
        ');
    }

    /**
     * @see it('duplicate field arguments')
     */
    public function testDuplicateFieldArguments()
    {
        $this->expectFailsRule(new UniqueArgumentNames, '
      {
        field(arg1: "value", arg1: "value")
      }
        ', [
            $this->duplicateArg('arg1', 3, 15, 3, 30)
        ]);
    }

    /**
     * @see it('many duplicate field arguments')
     */
    public function testManyDuplicateFieldArguments()
    {
        $this->expectFailsRule(new UniqueArgumentNames, '
      {
        field(arg1: "value", arg1: "value", arg1: "value")
      }
        ', [
            $this->duplicateArg('arg1', 3, 15, 3, 30),
            $this->duplicateArg('arg1', 3, 15, 3, 45)
        ]);
    }

    /**
     * @see it('duplicate directive arguments')
     */
    public function testDuplicateDirectiveArguments()
    {
        $this->expectFailsRule(new UniqueArgumentNames, '
      {
        field @directive(arg1: "value", arg1: "value")
      }
        ', [
            $this->duplicateArg('arg1', 3, 26, 3, 41)
        ]);
    }

    /**
     * @see it('many duplicate directive arguments')
     */
    public function testManyDuplicateDirectiveArguments()
    {
        $this->expectFailsRule(new UniqueArgumentNames, '
      {
        field @directive(arg1: "value", arg1: "value", arg1: "value")
      }
        ', [
            $this->duplicateArg('arg1', 3, 26, 3, 41),
            $this->duplicateArg('arg1', 3, 26, 3, 56)
        ]);
    }

    private function duplicateArg($argName, $l1, $c1, $l2, $c2)
    {
        return FormattedError::create(
            UniqueArgumentNames::duplicateArgMessage($argName),
            [new SourceLocation($l1, $c1), new SourceLocation($l2, $c2)]
        );
    }
}
