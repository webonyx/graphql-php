<?php
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
    private $introQuery;

    public function setIntroQuery()
    {
        $this->introQuery = Introspection::getIntrospectionQuery();
    }

    public function benchSchema()
    {
        StarWarsSchema::build();
    }

    public function benchHeroQuery()
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

    public function benchNestedQuery()
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

    public function benchQueryWithFragment()
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

    public function benchStarWarsIntrospectionQuery()
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
