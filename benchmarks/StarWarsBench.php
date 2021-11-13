<?php

declare(strict_types=1);

namespace GraphQL\Benchmarks;

use GraphQL\GraphQL;
use GraphQL\Tests\StarWarsSchema;
use GraphQL\Type\Introspection;

/**
 * @BeforeMethods({"setIntroQuery"})
 * @OutputTimeUnit("milliseconds", precision=3)
 * @Warmup(2)
 * @Revs(10)
 * @Iterations(2)
 */
class StarWarsBench
{
    private string $introQuery;

    public function setIntroQuery(): void
    {
        $this->introQuery = Introspection::getIntrospectionQuery();
    }

    public function benchSchema(): void
    {
        StarWarsSchema::build();
    }

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

    public function benchStarWarsIntrospectionQuery(): void
    {
        GraphQL::executeQuery(
            StarWarsSchema::build(),
            $this->introQuery
        );
    }

    public function benchQueryWithInterfaceFragment()
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
}
