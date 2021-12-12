<?php

declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Language\SourceLocation;
use GraphQL\Tests\ErrorHelper;
use GraphQL\Validator\Rules\PossibleFragmentSpreads;

/**
 * @phpstan-import-type ErrorArray from ErrorHelper
 */
class PossibleFragmentSpreadsTest extends ValidatorTestCase
{
    // Validate: Possible fragment spreads

    /**
     * @see it('of the same object')
     */
    public function testOfTheSameObject(): void
    {
        $this->expectPassesRule(
            new PossibleFragmentSpreads(),
            '
      fragment objectWithinObject on Dog { ...dogFragment }
      fragment dogFragment on Dog { barkVolume }
        '
        );
    }

    /**
     * @see it('of the same object with inline fragment')
     */
    public function testOfTheSameObjectWithInlineFragment(): void
    {
        $this->expectPassesRule(
            new PossibleFragmentSpreads(),
            '
      fragment objectWithinObjectAnon on Dog { ... on Dog { barkVolume } }
        '
        );
    }

    /**
     * @see it('object into an implemented interface')
     */
    public function testObjectIntoAnImplementedInterface(): void
    {
        $this->expectPassesRule(
            new PossibleFragmentSpreads(),
            '
      fragment objectWithinInterface on Pet { ...dogFragment }
      fragment dogFragment on Dog { barkVolume }
        '
        );
    }

    /**
     * @see it('object into containing union')
     */
    public function testObjectIntoContainingUnion(): void
    {
        $this->expectPassesRule(
            new PossibleFragmentSpreads(),
            '
      fragment objectWithinUnion on CatOrDog { ...dogFragment }
      fragment dogFragment on Dog { barkVolume }
        '
        );
    }

    /**
     * @see it('union into contained object')
     */
    public function testUnionIntoContainedObject(): void
    {
        $this->expectPassesRule(
            new PossibleFragmentSpreads(),
            '
      fragment unionWithinObject on Dog { ...catOrDogFragment }
      fragment catOrDogFragment on CatOrDog { __typename }
        '
        );
    }

    /**
     * @see it('union into overlapping interface')
     */
    public function testUnionIntoOverlappingInterface(): void
    {
        $this->expectPassesRule(
            new PossibleFragmentSpreads(),
            '
      fragment unionWithinInterface on Pet { ...catOrDogFragment }
      fragment catOrDogFragment on CatOrDog { __typename }
        '
        );
    }

    /**
     * @see it('union into overlapping union')
     */
    public function testUnionIntoOverlappingUnion(): void
    {
        $this->expectPassesRule(
            new PossibleFragmentSpreads(),
            '
      fragment unionWithinUnion on DogOrHuman { ...catOrDogFragment }
      fragment catOrDogFragment on CatOrDog { __typename }
        '
        );
    }

    /**
     * @see it('interface into implemented object')
     */
    public function testInterfaceIntoImplementedObject(): void
    {
        $this->expectPassesRule(
            new PossibleFragmentSpreads(),
            '
      fragment interfaceWithinObject on Dog { ...petFragment }
      fragment petFragment on Pet { name }
        '
        );
    }

    /**
     * @see it('interface into overlapping interface')
     */
    public function testInterfaceIntoOverlappingInterface(): void
    {
        $this->expectPassesRule(
            new PossibleFragmentSpreads(),
            '
      fragment interfaceWithinInterface on Pet { ...beingFragment }
      fragment beingFragment on Being { name }
        '
        );
    }

    /**
     * @see it('interface into overlapping interface in inline fragment')
     */
    public function testInterfaceIntoOverlappingInterfaceInInlineFragment(): void
    {
        $this->expectPassesRule(
            new PossibleFragmentSpreads(),
            '
      fragment interfaceWithinInterface on Pet { ... on Being { name } }
        '
        );
    }

    /**
     * @see it('interface into overlapping union')
     */
    public function testInterfaceIntoOverlappingUnion(): void
    {
        $this->expectPassesRule(
            new PossibleFragmentSpreads(),
            '
      fragment interfaceWithinUnion on CatOrDog { ...petFragment }
      fragment petFragment on Pet { name }
        '
        );
    }

    /**
     * @see it('ignores incorrect type (caught by FragmentsOnCompositeTypes)')
     */
    public function testIgnoresIncorrectTypeCaughtByFragmentsOnCompositeTypes(): void
    {
        $this->expectPassesRule(
            new PossibleFragmentSpreads(),
            '
      fragment petFragment on Pet { ...badInADifferentWay }
      fragment badInADifferentWay on String { name }
        '
        );
    }

    /**
     * @see it('different object into object')
     */
    public function testDifferentObjectIntoObject(): void
    {
        $this->expectFailsRule(
            new PossibleFragmentSpreads(),
            '
      fragment invalidObjectWithinObject on Cat { ...dogFragment }
      fragment dogFragment on Dog { barkVolume }
        ',
            [$this->error('dogFragment', 'Cat', 'Dog', 2, 51)]
        );
    }

    /**
     * @phpstan-return ErrorArray
     */
    private function error(string $fragName, string $parentType, string $fragType, int $line, int $column): array
    {
        return ErrorHelper::create(
            PossibleFragmentSpreads::typeIncompatibleSpreadMessage($fragName, $parentType, $fragType),
            [new SourceLocation($line, $column)]
        );
    }

    /**
     * @see it('different object into object in inline fragment')
     */
    public function testDifferentObjectIntoObjectInInlineFragment(): void
    {
        $this->expectFailsRule(
            new PossibleFragmentSpreads(),
            '
      fragment invalidObjectWithinObjectAnon on Cat {
        ... on Dog { barkVolume }
      }
        ',
            [$this->errorAnon('Cat', 'Dog', 3, 9)]
        );
    }

    /**
     * @phpstan-return ErrorArray
     */
    private function errorAnon(string $parentType, string $fragType, int $line, int $column): array
    {
        return ErrorHelper::create(
            PossibleFragmentSpreads::typeIncompatibleAnonSpreadMessage($parentType, $fragType),
            [new SourceLocation($line, $column)]
        );
    }

    /**
     * @see it('object into not implementing interface')
     */
    public function testObjectIntoNotImplementingInterface(): void
    {
        $this->expectFailsRule(
            new PossibleFragmentSpreads(),
            '
      fragment invalidObjectWithinInterface on Pet { ...humanFragment }
      fragment humanFragment on Human { pets { name } }
        ',
            [$this->error('humanFragment', 'Pet', 'Human', 2, 54)]
        );
    }

    /**
     * @see it('object into not containing union')
     */
    public function testObjectIntoNotContainingUnion(): void
    {
        $this->expectFailsRule(
            new PossibleFragmentSpreads(),
            '
      fragment invalidObjectWithinUnion on CatOrDog { ...humanFragment }
      fragment humanFragment on Human { pets { name } }
        ',
            [$this->error('humanFragment', 'CatOrDog', 'Human', 2, 55)]
        );
    }

    /**
     * @see it('union into not contained object')
     */
    public function testUnionIntoNotContainedObject(): void
    {
        $this->expectFailsRule(
            new PossibleFragmentSpreads(),
            '
      fragment invalidUnionWithinObject on Human { ...catOrDogFragment }
      fragment catOrDogFragment on CatOrDog { __typename }
        ',
            [$this->error('catOrDogFragment', 'Human', 'CatOrDog', 2, 52)]
        );
    }

    /**
     * @see it('union into non overlapping interface')
     */
    public function testUnionIntoNonOverlappingInterface(): void
    {
        $this->expectFailsRule(
            new PossibleFragmentSpreads(),
            '
      fragment invalidUnionWithinInterface on Pet { ...humanOrAlienFragment }
      fragment humanOrAlienFragment on HumanOrAlien { __typename }
        ',
            [$this->error('humanOrAlienFragment', 'Pet', 'HumanOrAlien', 2, 53)]
        );
    }

    /**
     * @see it('union into non overlapping union')
     */
    public function testUnionIntoNonOverlappingUnion(): void
    {
        $this->expectFailsRule(
            new PossibleFragmentSpreads(),
            '
      fragment invalidUnionWithinUnion on CatOrDog { ...humanOrAlienFragment }
      fragment humanOrAlienFragment on HumanOrAlien { __typename }
        ',
            [$this->error('humanOrAlienFragment', 'CatOrDog', 'HumanOrAlien', 2, 54)]
        );
    }

    /**
     * @see it('interface into non implementing object')
     */
    public function testInterfaceIntoNonImplementingObject(): void
    {
        $this->expectFailsRule(
            new PossibleFragmentSpreads(),
            '
      fragment invalidInterfaceWithinObject on Cat { ...intelligentFragment }
      fragment intelligentFragment on Intelligent { iq }
        ',
            [$this->error('intelligentFragment', 'Cat', 'Intelligent', 2, 54)]
        );
    }

    /**
     * @see it('interface into non overlapping interface')
     */
    public function testInterfaceIntoNonOverlappingInterface(): void
    {
        // Ideally this should fail, but our new lazy schema doesn't scan through all types and fields
        // So we don't have enough knowledge to check interface intersection and always allow this to pass:

        $this->expectPassesRule(
            new PossibleFragmentSpreads(),
            '
      fragment invalidInterfaceWithinInterface on Pet {
        ...intelligentFragment
      }
      fragment intelligentFragment on Intelligent { iq }
        '
        );
    }

    /**
     * @see it('interface into non overlapping interface in inline fragment')
     */
    public function testInterfaceIntoNonOverlappingInterfaceInInlineFragment(): void
    {
        // Ideally this should fail, but our new lazy schema doesn't scan through all types and fields
        // So we don't have enough knowledge to check interface intersection and always allow this to pass:

        $this->expectPassesRule(
            new PossibleFragmentSpreads(),
            '
      fragment invalidInterfaceWithinInterfaceAnon on Pet {
        ...on Intelligent { iq }
      }
        '
        );
    }

    /**
     * @see it('interface into non overlapping union')
     */
    public function testInterfaceIntoNonOverlappingUnion(): void
    {
        $this->expectFailsRule(
            new PossibleFragmentSpreads(),
            '
      fragment invalidInterfaceWithinUnion on HumanOrAlien { ...petFragment }
      fragment petFragment on Pet { name }
        ',
            [$this->error('petFragment', 'HumanOrAlien', 'Pet', 2, 62)]
        );
    }
}
