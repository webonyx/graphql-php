<?php
namespace GraphQL\Validator;

use GraphQL\FormattedError;
use GraphQL\Language\SourceLocation;
use GraphQL\Validator\Rules\PossibleFragmentSpreads;

class PossibleFragmentSpreadsTest extends TestCase
{
    // Validate: Possible fragment spreads
    public function testOfTheSameObject()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads(), '
      fragment objectWithinObject on Dog { ...dogFragment }
      fragment dogFragment on Dog { barkVolume }
        ');
    }

    public function testOfTheSameObjectWithInlineFragment()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment objectWithinObjectAnon on Dog { ... on Dog { barkVolume } }
        ');
    }

    public function testObjectIntoAnImplementedInterface()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment objectWithinInterface on Pet { ...dogFragment }
      fragment dogFragment on Dog { barkVolume }
        ');
    }

    public function testObjectIntoContainingUnion()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment objectWithinUnion on CatOrDog { ...dogFragment }
      fragment dogFragment on Dog { barkVolume }
        ');
    }

    public function testUnionIntoContainedObject()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment unionWithinObject on Dog { ...catOrDogFragment }
      fragment catOrDogFragment on CatOrDog { __typename }
        ');
    }

    public function testUnionIntoOverlappingInterface()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment unionWithinInterface on Pet { ...catOrDogFragment }
      fragment catOrDogFragment on CatOrDog { __typename }
        ');
    }

    public function testUnionIntoOverlappingUnion()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment unionWithinUnion on DogOrHuman { ...catOrDogFragment }
      fragment catOrDogFragment on CatOrDog { __typename }
        ');
    }

    public function testInterfaceIntoImplementedObject()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment interfaceWithinObject on Dog { ...petFragment }
      fragment petFragment on Pet { name }
        ');
    }

    public function testInterfaceIntoOverlappingInterface()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment interfaceWithinInterface on Pet { ...beingFragment }
      fragment beingFragment on Being { name }
        ');
    }

    public function testInterfaceIntoOverlappingInterfaceInInlineFragment()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment interfaceWithinInterface on Pet { ... on Being { name } }
        ');
    }

    public function testInterfaceIntoOverlappingUnion()
    {
        $this->expectPassesRule(new PossibleFragmentSpreads, '
      fragment interfaceWithinUnion on CatOrDog { ...petFragment }
      fragment petFragment on Pet { name }
        ');
    }

    public function testDifferentObjectIntoObject()
    {
        $this->expectFailsRule(new PossibleFragmentSpreads, '
      fragment invalidObjectWithinObject on Cat { ...dogFragment }
      fragment dogFragment on Dog { barkVolume }
        ',
            [$this->error('dogFragment', 'Cat', 'Dog', 2, 51)]
        );
    }

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

    public function testObjectIntoNotImplementingInterface()
    {
        $this->expectFailsRule(new PossibleFragmentSpreads, '
      fragment invalidObjectWithinInterface on Pet { ...humanFragment }
      fragment humanFragment on Human { pets { name } }
        ',
            [$this->error('humanFragment', 'Pet', 'Human', 2, 54)]
        );
    }

    public function testObjectIntoNotContainingUnion()
    {
        $this->expectFailsRule(new PossibleFragmentSpreads, '
      fragment invalidObjectWithinUnion on CatOrDog { ...humanFragment }
      fragment humanFragment on Human { pets { name } }
        ',
            [$this->error('humanFragment', 'CatOrDog', 'Human', 2, 55)]
        );
    }

    public function testUnionIntoNotContainedObject()
    {
        $this->expectFailsRule(new PossibleFragmentSpreads, '
      fragment invalidUnionWithinObject on Human { ...catOrDogFragment }
      fragment catOrDogFragment on CatOrDog { __typename }
        ',
            [$this->error('catOrDogFragment', 'Human', 'CatOrDog', 2, 52)]
        );
    }

    public function testUnionIntoNonOverlappingInterface()
    {
        $this->expectFailsRule(new PossibleFragmentSpreads, '
      fragment invalidUnionWithinInterface on Pet { ...humanOrAlienFragment }
      fragment humanOrAlienFragment on HumanOrAlien { __typename }
        ',
            [$this->error('humanOrAlienFragment', 'Pet', 'HumanOrAlien', 2, 53)]
        );
    }

    public function testUnionIntoNonOverlappingUnion()
    {
        $this->expectFailsRule(new PossibleFragmentSpreads, '
      fragment invalidUnionWithinUnion on CatOrDog { ...humanOrAlienFragment }
      fragment humanOrAlienFragment on HumanOrAlien { __typename }
        ',
            [$this->error('humanOrAlienFragment', 'CatOrDog', 'HumanOrAlien', 2, 54)]
        );
    }

    public function testInterfaceIntoNonImplementingObject()
    {
        $this->expectFailsRule(new PossibleFragmentSpreads, '
      fragment invalidInterfaceWithinObject on Cat { ...intelligentFragment }
      fragment intelligentFragment on Intelligent { iq }
        ',
            [$this->error('intelligentFragment', 'Cat', 'Intelligent', 2, 54)]
        );
    }

    public function testInterfaceIntoNonOverlappingInterface()
    {
        $this->expectFailsRule(new PossibleFragmentSpreads, '
      fragment invalidInterfaceWithinInterface on Pet {
        ...intelligentFragment
      }
      fragment intelligentFragment on Intelligent { iq }
        ',
            [$this->error('intelligentFragment', 'Pet', 'Intelligent', 3, 9)]
        );
    }

    public function testInterfaceIntoNonOverlappingInterfaceInInlineFragment()
    {
        $this->expectFailsRule(new PossibleFragmentSpreads, '
      fragment invalidInterfaceWithinInterfaceAnon on Pet {
        ...on Intelligent { iq }
      }
        ',
            [$this->errorAnon('Pet', 'Intelligent', 3, 9)]
        );
    }

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
