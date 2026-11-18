<?php declare(strict_types=1);

namespace GraphQL\Benchmarks;

use GraphQL\GraphQL;
use GraphQL\Tests\StarWarsSchema;
use GraphQL\Type\Introspection;
use Tally\Attribute\Before;
use Tally\Attribute\Expected;
use Tally\Attribute\Warmup;

#[Warmup(1)]
class StarWarsBench
{
    private string $introQuery;

    #[Before]
    public function setIntroQuery(): void
    {
        $this->introQuery = Introspection::getIntrospectionQuery();
    }

    #[Expected(instructions: '40.26K', cycles: '56.46K', memory: '17.55KiB')]
    public function benchSchema(): void
    {
        StarWarsSchema::build();
    }

    #[Expected(instructions: '1.757M', cycles: '2.176M', memory: '81.93KiB')]
    public function benchHeroQuery(): void
    {
        $q = '
        query HeroNameQuery {
          hero {
            name
          }
        }
        ';

        GraphQL::executeQuery(
            StarWarsSchema::build(),
            $q
        );
    }

    #[Expected(instructions: '4.139M', cycles: '5.228M', memory: '86.55KiB')]
    public function benchNestedQuery(): void
    {
        $q = '
        query NestedQuery {
          hero {
            name
            friends {
              name
              appearsIn
              friends {
                name
              }
            }
          }
        }
        ';
        GraphQL::executeQuery(
            StarWarsSchema::build(),
            $q
        );
    }

    #[Expected(instructions: '4.430M', cycles: '5.257M', memory: '96.57KiB')]
    public function benchQueryWithFragment(): void
    {
        $q = '
        query UseFragment {
          luke: human(id: "1000") {
            ...HumanFragment
          }
          leia: human(id: "1003") {
            ...HumanFragment
          }
        }

        fragment HumanFragment on Human {
          name
          homePlanet
        }
        ';

        GraphQL::executeQuery(
            StarWarsSchema::build(),
            $q
        );
    }

    #[Expected(instructions: '4.136M', cycles: '4.909M', memory: '96.33KiB')]
    public function benchQueryWithInterfaceFragment(): void
    {
        $q = '
        query UseInterfaceFragment {
          luke: human(id: "1000") {
            ...CharacterFragment
          }
          leia: human(id: "1003") {
            ...CharacterFragment
          }
        }

        fragment CharacterFragment on Character {
          name
        }
        ';

        GraphQL::executeQuery(
            StarWarsSchema::build(),
            $q
        );
    }

    #[Expected(instructions: '47.33M', cycles: '61.28M', memory: '231.0KiB')]
    public function benchStarWarsIntrospectionQuery(): void
    {
        GraphQL::executeQuery(
            StarWarsSchema::build(),
            $this->introQuery
        );
    }
}
