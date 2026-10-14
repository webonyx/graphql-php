<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Validator\Rules\ScalarLeafs;

/**
 * @phpstan-import-type ErrorArray from ErrorHelper
 */
final class ScalarLeafsTest extends ValidatorTestCase
{
    // Validate: Scalar leafs

    /** @see it('valid scalar selection') */
    public function testValidScalarSelection(): void
    {
        $this->expectPassesRule(
            new ScalarLeafs(),
            '
      fragment scalarSelection on Dog {
        barks
      }
        '
        );
    }

    /** @see it('object type missing selection') */
    public function testObjectTypeMissingSelection(): void
    {
        $this->expectFailsRule(
            new ScalarLeafs(),
            '
      query directQueryOnObjectWithoutSubFields {
        human
      }
        ',
            [$this->missingObjSubselection('human', 'Human', 3, 9)]
        );
    }

    /** @phpstan-return ErrorArray */
    private function missingObjSubselection(string $field, string $type, int $line, int $column): array
    {
        return ErrorHelper::create(
            ScalarLeafs::requiredSubselectionMessage($field, $type),
            [new SourceLocation($line, $column)]
        );
    }

    /** @see it('interface type missing selection') */
    public function testInterfaceTypeMissingSelection(): void
    {
        $this->expectFailsRule(
            new ScalarLeafs(),
            '
      {
        human { pets }
      }
        ',
            [$this->missingObjSubselection('pets', '[Pet]', 3, 17)]
        );
    }

    /** @see it('valid scalar selection with args') */
    public function testValidScalarSelectionWithArgs(): void
    {
        $this->expectPassesRule(
            new ScalarLeafs(),
            '
      fragment scalarSelectionWithArgs on Dog {
        doesKnowCommand(dogCommand: SIT)
      }
        '
        );
    }

    /** @see it('scalar selection not allowed on Boolean') */
    public function testScalarSelectionNotAllowedOnBoolean(): void
    {
        $this->expectFailsRule(
            new ScalarLeafs(),
            '
      fragment scalarSelectionsNotAllowedOnBoolean on Dog {
        barks { sinceWhen }
      }
        ',
            [$this->noScalarSubselection('barks', 'Boolean', 3, 15)]
        );
    }

    /** @phpstan-return ErrorArray */
    private function noScalarSubselection(string $field, string $type, int $line, int $column): array
    {
        return ErrorHelper::create(
            ScalarLeafs::noSubselectionAllowedMessage($field, $type),
            [new SourceLocation($line, $column)]
        );
    }

    /** @see it('scalar selection not allowed on Enum') */
    public function testScalarSelectionNotAllowedOnEnum(): void
    {
        $this->expectFailsRule(
            new ScalarLeafs(),
            '
      fragment scalarSelectionsNotAllowedOnEnum on Cat {
        furColor { inHexdec }
      }
        ',
            [$this->noScalarSubselection('furColor', 'FurColor', 3, 18)]
        );
    }

    /** @see it('scalar selection not allowed with args') */
    public function testScalarSelectionNotAllowedWithArgs(): void
    {
        $this->expectFailsRule(
            new ScalarLeafs(),
            '
      fragment scalarSelectionsNotAllowedWithArgs on Dog {
        doesKnowCommand(dogCommand: SIT) { sinceWhen }
      }
        ',
            [$this->noScalarSubselection('doesKnowCommand', 'Boolean', 3, 42)]
        );
    }

    /** @see it('Scalar selection not allowed with directives') */
    public function testScalarSelectionNotAllowedWithDirectives(): void
    {
        $this->expectFailsRule(
            new ScalarLeafs(),
            '
      fragment scalarSelectionsNotAllowedWithDirectives on Dog {
        name @include(if: true) { isAlsoHumanName }
      }
        ',
            [$this->noScalarSubselection('name', 'String', 3, 33)]
        );
    }

    /** @see it('Scalar selection not allowed with directives and args') */
    public function testScalarSelectionNotAllowedWithDirectivesAndArgs(): void
    {
        $this->expectFailsRule(
            new ScalarLeafs(),
            '
      fragment scalarSelectionsNotAllowedWithDirectivesAndArgs on Dog {
        doesKnowCommand(dogCommand: SIT) @include(if: true) { sinceWhen }
      }
        ',
            [$this->noScalarSubselection('doesKnowCommand', 'Boolean', 3, 61)]
        );
    }
}
