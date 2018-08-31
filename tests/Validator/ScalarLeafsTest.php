<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\ScalarLeafs;

class ScalarLeafsTest extends ValidatorTestCase
{
    // Validate: Scalar leafs

    /**
     * @see it('valid scalar selection')
     */
    public function testValidScalarSelection()
    {
        $this->expectPassesRule(new ScalarLeafs, '
      fragment scalarSelection on Dog {
        barks
      }
        ');
    }

    /**
     * @see it('object type missing selection')
     */
    public function testObjectTypeMissingSelection()
    {
        $this->expectFailsRule(new ScalarLeafs, '
      query directQueryOnObjectWithoutSubFields {
        human
      }
        ', [$this->missingObjSubselection('human', 'Human', 3, 9)]);
    }

    /**
     * @see it('interface type missing selection')
     */
    public function testInterfaceTypeMissingSelection()
    {
        $this->expectFailsRule(new ScalarLeafs, '
      {
        human { pets }
      }
        ', [$this->missingObjSubselection('pets', '[Pet]', 3, 17)]);
    }

    /**
     * @see it('valid scalar selection with args')
     */
    public function testValidScalarSelectionWithArgs()
    {
        $this->expectPassesRule(new ScalarLeafs, '
      fragment scalarSelectionWithArgs on Dog {
        doesKnowCommand(dogCommand: SIT)
      }
        ');
    }

    /**
     * @see it('scalar selection not allowed on Boolean')
     */
    public function testScalarSelectionNotAllowedOnBoolean()
    {
        $this->expectFailsRule(new ScalarLeafs, '
      fragment scalarSelectionsNotAllowedOnBoolean on Dog {
        barks { sinceWhen }
      }
        ',
            [$this->noScalarSubselection('barks', 'Boolean', 3, 15)]);
    }

    /**
     * @see it('scalar selection not allowed on Enum')
     */
    public function testScalarSelectionNotAllowedOnEnum()
    {
        $this->expectFailsRule(new ScalarLeafs, '
      fragment scalarSelectionsNotAllowedOnEnum on Cat {
        furColor { inHexdec }
      }
        ',
        [$this->noScalarSubselection('furColor', 'FurColor', 3, 18)]
        );
    }

    /**
     * @see it('scalar selection not allowed with args')
     */
    public function testScalarSelectionNotAllowedWithArgs()
    {
        $this->expectFailsRule(new ScalarLeafs, '
      fragment scalarSelectionsNotAllowedWithArgs on Dog {
        doesKnowCommand(dogCommand: SIT) { sinceWhen }
      }
        ',
        [$this->noScalarSubselection('doesKnowCommand', 'Boolean', 3, 42)]
        );
    }

    /**
     * @see it('Scalar selection not allowed with directives')
     */
    public function testScalarSelectionNotAllowedWithDirectives()
    {
        $this->expectFailsRule(new ScalarLeafs, '
      fragment scalarSelectionsNotAllowedWithDirectives on Dog {
        name @include(if: true) { isAlsoHumanName }
      }
        ',
        [$this->noScalarSubselection('name', 'String', 3, 33)]
        );
    }

    /**
     * @see it('Scalar selection not allowed with directives and args')
     */
    public function testScalarSelectionNotAllowedWithDirectivesAndArgs()
    {
        $this->expectFailsRule(new ScalarLeafs, '
      fragment scalarSelectionsNotAllowedWithDirectivesAndArgs on Dog {
        doesKnowCommand(dogCommand: SIT) @include(if: true) { sinceWhen }
      }
        ',
            [$this->noScalarSubselection('doesKnowCommand', 'Boolean', 3, 61)]
        );
    }

    private function noScalarSubselection($field, $type, $line, $column)
    {
        return FormattedError::create(
            ScalarLeafs::noSubselectionAllowedMessage($field, $type),
            [new SourceLocation($line, $column)]
        );
    }

    private function missingObjSubselection($field, $type, $line, $column)
    {
        return FormattedError::create(
            ScalarLeafs::requiredSubselectionMessage($field, $type),
            [new SourceLocation($line, $column)]
        );
    }
}
