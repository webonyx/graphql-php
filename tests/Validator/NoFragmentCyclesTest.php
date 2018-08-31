<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\NoFragmentCycles;

class NoFragmentCyclesTest extends ValidatorTestCase
{
    // Validate: No circular fragment spreads

    /**
     * @see it('single reference is valid')
     */
    public function testSingleReferenceIsValid() : void
    {
        $this->expectPassesRule(new NoFragmentCycles(), '
      fragment fragA on Dog { ...fragB }
      fragment fragB on Dog { name }
        ');
    }

    /**
     * @see it('spreading twice is not circular')
     */
    public function testSpreadingTwiceIsNotCircular() : void
    {
        $this->expectPassesRule(new NoFragmentCycles, '
      fragment fragA on Dog { ...fragB, ...fragB }
      fragment fragB on Dog { name }
        ');
    }

    /**
     * @see it('spreading twice indirectly is not circular')
     */
    public function testSpreadingTwiceIndirectlyIsNotCircular() : void
    {
        $this->expectPassesRule(new NoFragmentCycles, '
      fragment fragA on Dog { ...fragB, ...fragC }
      fragment fragB on Dog { ...fragC }
      fragment fragC on Dog { name }
        ');
    }

    /**
     * @see it('double spread within abstract types')
     */
    public function testDoubleSpreadWithinAbstractTypes() : void
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

    /**
     * @see it('does not false positive on unknown fragment')
     */
    public function testDoesNotFalsePositiveOnUnknownFragment() : void
    {
        $this->expectPassesRule(new NoFragmentCycles, '
      fragment nameFragment on Pet {
        ...UnknownFragment
      }
        ');
    }

    /**
     * @see it('spreading recursively within field fails')
     */
    public function testSpreadingRecursivelyWithinFieldFails() : void
    {
        $this->expectFailsRule(new NoFragmentCycles, '
      fragment fragA on Human { relatives { ...fragA } },
        ', [
            $this->cycleError('fragA', [], 2, 45)
        ]);
    }

    /**
     * @see it('no spreading itself directly')
     */
    public function testNoSpreadingItselfDirectly() : void
    {
        $this->expectFailsRule(new NoFragmentCycles, '
      fragment fragA on Dog { ...fragA }
        ', [
            $this->cycleError('fragA', [], 2, 31)
        ]);
    }

    /**
     * @see it('no spreading itself directly within inline fragment')
     */
    public function testNoSpreadingItselfDirectlyWithinInlineFragment() : void
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

    /**
     * @see it('no spreading itself indirectly')
     */
    public function testNoSpreadingItselfIndirectly() : void
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

    /**
     * @see it('no spreading itself indirectly reports opposite order')
     */
    public function testNoSpreadingItselfIndirectlyReportsOppositeOrder() : void
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

    /**
     * @see it('no spreading itself indirectly within inline fragment')
     */
    public function testNoSpreadingItselfIndirectlyWithinInlineFragment() : void
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

    /**
     * @see it('no spreading itself deeply')
     */
    public function testNoSpreadingItselfDeeply() : void
    {
        $this->expectFailsRule(new NoFragmentCycles, '
      fragment fragA on Dog { ...fragB }
      fragment fragB on Dog { ...fragC }
      fragment fragC on Dog { ...fragO }
      fragment fragX on Dog { ...fragY }
      fragment fragY on Dog { ...fragZ }
      fragment fragZ on Dog { ...fragO }
      fragment fragO on Dog { ...fragP }
      fragment fragP on Dog { ...fragA, ...fragX }
    ', [
            FormattedError::create(
                NoFragmentCycles::cycleErrorMessage('fragA', [ 'fragB', 'fragC', 'fragO', 'fragP' ]),
                [
                    new SourceLocation(2, 31),
                    new SourceLocation(3, 31),
                    new SourceLocation(4, 31),
                    new SourceLocation(8, 31),
                    new SourceLocation(9, 31),
                ]
            ),
            FormattedError::create(
                NoFragmentCycles::cycleErrorMessage('fragO', [ 'fragP', 'fragX', 'fragY', 'fragZ' ]),
                [
                    new SourceLocation(8, 31),
                    new SourceLocation(9, 41),
                    new SourceLocation(5, 31),
                    new SourceLocation(6, 31),
                    new SourceLocation(7, 31),
                ]
            )
        ]);
    }

    /**
     * @see it('no spreading itself deeply two paths')
     */
    public function testNoSpreadingItselfDeeplyTwoPaths() : void
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

    /**
     * @see it('no spreading itself deeply two paths -- alt traverse order')
     */
    public function testNoSpreadingItselfDeeplyTwoPathsTraverseOrder() : void
    {
        $this->expectFailsRule(new NoFragmentCycles, '
      fragment fragA on Dog { ...fragC }
      fragment fragB on Dog { ...fragC }
      fragment fragC on Dog { ...fragA, ...fragB }
    ', [
            FormattedError::create(
                NoFragmentCycles::cycleErrorMessage('fragA', [ 'fragC' ]),
                [new SourceLocation(2,31), new SourceLocation(4,31)]
            ),
            FormattedError::create(
                NoFragmentCycles::cycleErrorMessage('fragC', [ 'fragB' ]),
                [new SourceLocation(4, 41), new SourceLocation(3, 31)]
            )
        ]);
    }

    /**
     * @see it('no spreading itself deeply and immediately')
     */
    public function testNoSpreadingItselfDeeplyAndImmediately() : void
    {
        $this->expectFailsRule(new NoFragmentCycles, '
      fragment fragA on Dog { ...fragB }
      fragment fragB on Dog { ...fragB, ...fragC }
      fragment fragC on Dog { ...fragA, ...fragB }
    ', [
            FormattedError::create(
                NoFragmentCycles::cycleErrorMessage('fragB', []),
                [new SourceLocation(3, 31)]
            ),
            FormattedError::create(
                NoFragmentCycles::cycleErrorMessage('fragA', [ 'fragB', 'fragC' ]),
                [
                    new SourceLocation(2, 31),
                    new SourceLocation(3, 41),
                    new SourceLocation(4, 31)
                ]
            ),
            FormattedError::create(
                NoFragmentCycles::cycleErrorMessage('fragB', [ 'fragC' ]),
                [new SourceLocation(3, 41), new SourceLocation(4, 41)]
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
