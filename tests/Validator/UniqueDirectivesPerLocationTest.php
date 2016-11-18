<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Validator\Rules\UniqueDirectivesPerLocation;

class UniqueDirectivesPerLocationTest extends TestCase
{
    /**
     * @it no directives
     */
    public function testNoDirectives()
    {
        $this->expectPassesRule(new UniqueDirectivesPerLocation(), '
      fragment Test on Type {
        field
      }
        ');
    }

    /**
     * @it unique directives in different locations
     */
    public function testUniqueDirectivesInDifferentLocations()
    {
        $this->expectPassesRule(new UniqueDirectivesPerLocation(), '
      fragment Test on Type @directiveA {
        field @directiveB
      }
        ');
    }

    /**
     * @it unique directives in same locations
     */
    public function testUniqueDirectivesInSameLocations()
    {
        $this->expectPassesRule(new UniqueDirectivesPerLocation(), '
      fragment Test on Type @directiveA @directiveB {
        field @directiveA @directiveB
      }
        ');
    }

    /**
     * @it same directives in different locations
     */
    public function testSameDirectivesInDifferentLocations()
    {
        $this->expectPassesRule(new UniqueDirectivesPerLocation(), '
      fragment Test on Type @directiveA {
        field @directiveA
      }
        ');
    }

    /**
     * @it same directives in similar locations
     */
    public function testSameDirectivesInSimilarLocations()
    {
        $this->expectPassesRule(new UniqueDirectivesPerLocation(), '
      fragment Test on Type {
        field @directive
        field @directive
      }
        ');
    }

    /**
     * @it duplicate directives in one location
     */
    public function testDuplicateDirectivesInOneLocation()
    {
        $this->expectFailsRule(new UniqueDirectivesPerLocation(), '
      fragment Test on Type {
        field @directive @directive
      }
        ', [
            $this->duplicateDirective('directive', 3, 15, 3, 26)
        ]);
    }

    /**
     * @it many duplicate directives in one location
     */
    public function testManyDuplicateDirectivesInOneLocation()
    {
        $this->expectFailsRule(new UniqueDirectivesPerLocation(), '
      fragment Test on Type {
        field @directive @directive @directive
      }
        ', [
            $this->duplicateDirective('directive', 3, 15, 3, 26),
            $this->duplicateDirective('directive', 3, 15, 3, 37)
        ]);
    }

    /**
     * @it different duplicate directives in one location
     */
    public function testDifferentDuplicateDirectivesInOneLocation()
    {
        $this->expectFailsRule(new UniqueDirectivesPerLocation, '
      fragment Test on Type {
        field @directiveA @directiveB @directiveA @directiveB
      }
        ', [
            $this->duplicateDirective('directiveA', 3, 15, 3, 39),
            $this->duplicateDirective('directiveB', 3, 27, 3, 51)
        ]);
    }

    /**
     * @it duplicate directives in many locations
     */
    public function testDuplicateDirectivesInManyLocations()
    {
        $this->expectFailsRule(new UniqueDirectivesPerLocation(), '
      fragment Test on Type @directive @directive {
        field @directive @directive
      }
        ', [
            $this->duplicateDirective('directive', 2, 29, 2, 40),
            $this->duplicateDirective('directive', 3, 15, 3, 26)
        ]);
    }

    private function duplicateDirective($directiveName, $l1, $c1, $l2, $c2)
    {
        return [
            'message' =>UniqueDirectivesPerLocation::duplicateDirectiveMessage($directiveName),
            'locations' => [
                ['line' => $l1, 'column' => $c1],
                ['line' => $l2, 'column' => $c2]
            ]
        ];
    }
}
