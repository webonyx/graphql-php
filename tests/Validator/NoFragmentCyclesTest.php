<?php
namespace GraphQL\Validator;

use GraphQL\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\NoFragmentCycles;

class NoFragmentCyclesTest extends TestCase
{
    // Validate: No circular fragment spreads

    public function testSingleReferenceIsValid()
    {
        $this->expectPassesRule(new NoFragmentCycles(), '
      fragment fragA on Dog { ...fragB }
      fragment fragB on Dog { name }
        ');
    }

    public function testSpreadingTwiceIsNotCircular()
    {
        $this->expectPassesRule(new NoFragmentCycles, '
      fragment fragA on Dog { ...fragB, ...fragB }
      fragment fragB on Dog { name }
        ');
    }

    public function testSpreadingTwiceIndirectlyIsNotCircular()
    {
        $this->expectPassesRule(new NoFragmentCycles, '
      fragment fragA on Dog { ...fragB, ...fragC }
      fragment fragB on Dog { ...fragC }
      fragment fragC on Dog { name }
        ');
    }

    public function testDoubleSpreadWithinAbstractTypes()
    {
        $this->expectPassesRule(new NoFragmentCycles, '
      fragment nameFragment on Pet {
        ... on Dog { name }
        ... on Cat { name }
      }

      fragment spreadsInAnon on Pet {
        ... on Dog { ...nameFragment }
        ... on Cat { ...nameFragment }
      }
        ');
    }

    public function testSpreadingRecursivelyWithinFieldFails()
    {
        $this->expectFailsRule(new NoFragmentCycles, '
      fragment fragA on Human { relatives { ...fragA } },
        ', [
            $this->cycleError('fragA', [], 2, 45)
        ]);
    }

    public function testNoSpreadingItselfDirectly()
    {
        $this->expectFailsRule(new NoFragmentCycles, '
      fragment fragA on Dog { ...fragA }
        ', [
            $this->cycleError('fragA', [], 2, 31)
        ]);
    }

    public function testNoSpreadingItselfDirectlyWithinInlineFragment()
    {
        $this->expectFailsRule(new NoFragmentCycles, '
      fragment fragA on Pet {
        ... on Dog {
          ...fragA
        }
      }
        ', [
            $this->cycleError('fragA', [], 4, 11)
        ]);
    }

    public function testNoSpreadingItselfIndirectly()
    {
        $this->expectFailsRule(new NoFragmentCycles, '
      fragment fragA on Dog { ...fragB }
      fragment fragB on Dog { ...fragA }
        ', [
            FormattedError::create(
                NoFragmentCycles::cycleErrorMessage('fragA', ['fragB']),
                [ new SourceLocation(2, 31), new SourceLocation(3, 31) ]
            )
        ]);
    }

    public function testNoSpreadingItselfIndirectlyReportsOppositeOrder()
    {
        $this->expectFailsRule(new NoFragmentCycles, '
      fragment fragB on Dog { ...fragA }
      fragment fragA on Dog { ...fragB }
        ', [
            FormattedError::create(
                NoFragmentCycles::cycleErrorMessage('fragB', ['fragA']),
                [new SourceLocation(2, 31), new SourceLocation(3, 31)]
            )
        ]);
    }

    public function testNoSpreadingItselfIndirectlyWithinInlineFragment()
    {
        $this->expectFailsRule(new NoFragmentCycles, '
      fragment fragA on Pet {
        ... on Dog {
          ...fragB
        }
      }
      fragment fragB on Pet {
        ... on Dog {
          ...fragA
        }
      }
        ', [
            FormattedError::create(
                NoFragmentCycles::cycleErrorMessage('fragA', ['fragB']),
                [new SourceLocation(4, 11), new SourceLocation(9, 11)]
            )
        ]);
    }

    public function testNoSpreadingItselfDeeply()
    {
        $this->expectFailsRule(new NoFragmentCycles, '
      fragment fragA on Dog { ...fragB }
      fragment fragB on Dog { ...fragC }
      fragment fragC on Dog { ...fragO }
      fragment fragX on Dog { ...fragY }
      fragment fragY on Dog { ...fragZ }
      fragment fragZ on Dog { ...fragO }
      fragment fragO on Dog { ...fragA, ...fragX }
    ', [
            FormattedError::create(
                NoFragmentCycles::cycleErrorMessage('fragA', ['fragB', 'fragC', 'fragO']),
                [
                    new SourceLocation(2, 31),
                    new SourceLocation(3, 31),
                    new SourceLocation(4, 31),
                    new SourceLocation(8, 31),
                ]
            ),
            FormattedError::create(
                NoFragmentCycles::cycleErrorMessage('fragX', ['fragY', 'fragZ', 'fragO']),
                [
                    new SourceLocation(5, 31),
                    new SourceLocation(6, 31),
                    new SourceLocation(7, 31),
                    new SourceLocation(8, 41),
                ]
            )
        ]);
    }

    public function testNoSpreadingItselfDeeplyTwoPathsNewRule()
    {
        $this->expectFailsRule(new NoFragmentCycles, '
      fragment fragA on Dog { ...fragB, ...fragC }
      fragment fragB on Dog { ...fragA }
      fragment fragC on Dog { ...fragA }
        ', [
            FormattedError::create(
                NoFragmentCycles::cycleErrorMessage('fragA', ['fragB']),
                [new SourceLocation(2, 31), new SourceLocation(3, 31)]
            ),
            FormattedError::create(
                NoFragmentCycles::cycleErrorMessage('fragA', ['fragC']),
                [new SourceLocation(2, 41), new SourceLocation(4, 31)]
            )
        ]);
    }

    private function cycleError($fargment, $spreadNames, $line, $column)
    {
        return FormattedError::create(
            NoFragmentCycles::cycleErrorMessage($fargment, $spreadNames),
            [new SourceLocation($line, $column)]
        );
    }
}
