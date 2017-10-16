<?php
namespace GraphQL\Tests\Validator;

use GraphQL\Error\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\PossibleFragmentSpreads;

class PossibleFragmentSpreadsTest extends TestCase
{
    // Validate: Possible fragment spreads

    /**
     * @it of the same object
     */
    public function testOfTheSameObject()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads(), '
      fragment objectWithinObject on Dog { ...dogFragment }
      fragment dogFragment on Dog { barkVolume }
        ');
    }

    /**
     * @it of the same object with inline fragment
     */
    public function testOfTheSameObjectWithInlineFragment()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment objectWithinObjectAnon on Dog { ... on Dog { barkVolume } }
        ');
    }

    /**
     * @it object into an implemented interface
     */
    public function testObjectIntoAnImplementedInterface()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment objectWithinInterface on Pet { ...dogFragment }
      fragment dogFragment on Dog { barkVolume }
        ');
    }

    /**
     * @it object into containing union
     */
    public function testObjectIntoContainingUnion()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment objectWithinUnion on CatOrDog { ...dogFragment }
      fragment dogFragment on Dog { barkVolume }
        ');
    }

    /**
     * @it union into contained object
     */
    public function testUnionIntoContainedObject()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment unionWithinObject on Dog { ...catOrDogFragment }
      fragment catOrDogFragment on CatOrDog { __typename }
        ');
    }

    /**
     * @it union into overlapping interface
     */
    public function testUnionIntoOverlappingInterface()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment unionWithinInterface on Pet { ...catOrDogFragment }
      fragment catOrDogFragment on CatOrDog { __typename }
        ');
    }

    /**
     * @it union into overlapping union
     */
    public function testUnionIntoOverlappingUnion()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment unionWithinUnion on DogOrHuman { ...catOrDogFragment }
      fragment catOrDogFragment on CatOrDog { __typename }
        ');
    }

    /**
     * @it interface into implemented object
     */
    public function testInterfaceIntoImplementedObject()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment interfaceWithinObject on Dog { ...petFragment }
      fragment petFragment on Pet { name }
        ');
    }

    /**
     * @it interface into overlapping interface
     */
    public function testInterfaceIntoOverlappingInterface()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment interfaceWithinInterface on Pet { ...beingFragment }
      fragment beingFragment on Being { name }
        ');
    }

    /**
     * @it interface into overlapping interface in inline fragment
     */
    public function testInterfaceIntoOverlappingInterfaceInInlineFragment()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment interfaceWithinInterface on Pet { ... on Being { name } }
        ');
    }

    /**
     * @it interface into overlapping union
     */
    public function testInterfaceIntoOverlappingUnion()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment interfaceWithinUnion on CatOrDog { ...petFragment }
      fragment petFragment on Pet { name }
        ');
    }

    /**
     * @it different object into object
     */
    public function testDifferentObjectIntoObject()
    {
        $this->expectFailsRule(new PossibleFragmentSpreads, '
      fragment invalidObjectWithinObject on Cat { ...dogFragment }
      fragment dogFragment on Dog { barkVolume }
        ',
            [$this->error('dogFragment', 'Cat', 'Dog', 2, 51)]
        );
    }

    /**
     * @it different object into object in inline fragment
     */
    public function testDifferentObjectIntoObjectInInlineFragment()
    {
        $this->expectFailsRule(new PossibleFragmentSpreads, '
      fragment invalidObjectWithinObjectAnon on Cat {
        ... on Dog { barkVolume }
      }
        ',
            [$this->errorAnon('Cat', 'Dog', 3, 9)]
        );
    }

    /**
     * @it object into not implementing interface
     */
    public function testObjectIntoNotImplementingInterface()
    {
        $this->expectFailsRule(new PossibleFragmentSpreads, '
      fragment invalidObjectWithinInterface on Pet { ...humanFragment }
      fragment humanFragment on Human { pets { name } }
        ',
            [$this->error('humanFragment', 'Pet', 'Human', 2, 54)]
        );
    }

    /**
     * @it object into not containing union
     */
    public function testObjectIntoNotContainingUnion()
    {
        $this->expectFailsRule(new PossibleFragmentSpreads, '
      fragment invalidObjectWithinUnion on CatOrDog { ...humanFragment }
      fragment humanFragment on Human { pets { name } }
        ',
            [$this->error('humanFragment', 'CatOrDog', 'Human', 2, 55)]
        );
    }

    /**
     * @it union into not contained object
     */
    public function testUnionIntoNotContainedObject()
    {
        $this->expectFailsRule(new PossibleFragmentSpreads, '
      fragment invalidUnionWithinObject on Human { ...catOrDogFragment }
      fragment catOrDogFragment on CatOrDog { __typename }
        ',
            [$this->error('catOrDogFragment', 'Human', 'CatOrDog', 2, 52)]
        );
    }

    /**
     * @it union into non overlapping interface
     */
    public function testUnionIntoNonOverlappingInterface()
    {
        $this->expectFailsRule(new PossibleFragmentSpreads, '
      fragment invalidUnionWithinInterface on Pet { ...humanOrAlienFragment }
      fragment humanOrAlienFragment on HumanOrAlien { __typename }
        ',
            [$this->error('humanOrAlienFragment', 'Pet', 'HumanOrAlien', 2, 53)]
        );
    }

    /**
     * @it union into non overlapping union
     */
    public function testUnionIntoNonOverlappingUnion()
    {
        $this->expectFailsRule(new PossibleFragmentSpreads, '
      fragment invalidUnionWithinUnion on CatOrDog { ...humanOrAlienFragment }
      fragment humanOrAlienFragment on HumanOrAlien { __typename }
        ',
            [$this->error('humanOrAlienFragment', 'CatOrDog', 'HumanOrAlien', 2, 54)]
        );
    }

    /**
     * @it interface into non implementing object
     */
    public function testInterfaceIntoNonImplementingObject()
    {
        $this->expectFailsRule(new PossibleFragmentSpreads, '
      fragment invalidInterfaceWithinObject on Cat { ...intelligentFragment }
      fragment intelligentFragment on Intelligent { iq }
        ',
            [$this->error('intelligentFragment', 'Cat', 'Intelligent', 2, 54)]
        );
    }

    /**
     * @it interface into non overlapping interface
     */
    public function testInterfaceIntoNonOverlappingInterface()
    {
        // Ideally this should fail, but our new lazy schema doesn't scan through all types and fields
        // So we don't have enough knowledge to check interface intersection and always allow this to pass:

        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment invalidInterfaceWithinInterface on Pet {
        ...intelligentFragment
      }
      fragment intelligentFragment on Intelligent { iq }
        ');
    }

    /**
     * @it interface into non overlapping interface in inline fragment
     */
    public function testInterfaceIntoNonOverlappingInterfaceInInlineFragment()
    {
        // Ideally this should fail, but our new lazy schema doesn't scan through all types and fields
        // So we don't have enough knowledge to check interface intersection and always allow this to pass:

        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment invalidInterfaceWithinInterfaceAnon on Pet {
        ...on Intelligent { iq }
      }
        ');
    }

    /**
     * @it interface into non overlapping union
     */
    public function testInterfaceIntoNonOverlappingUnion()
    {
        $this->expectFailsRule(new PossibleFragmentSpreads, '
      fragment invalidInterfaceWithinUnion on HumanOrAlien { ...petFragment }
      fragment petFragment on Pet { name }
        ',
            [$this->error('petFragment', 'HumanOrAlien', 'Pet', 2, 62)]
        );
    }

    private function error($fragName, $parentType, $fragType, $line, $column)
    {
        return FormattedError::create(
            PossibleFragmentSpreads::typeIncompatibleSpreadMessage($fragName, $parentType, $fragType),
            [new SourceLocation($line, $column)]
        );
    }

    private function errorAnon($parentType, $fragType, $line, $column)
    {
        return FormattedError::create(
            PossibleFragmentSpreads::typeIncompatibleAnonSpreadMessage($parentType, $fragType),
            [new SourceLocation($line, $column)]
        );
    }
}
