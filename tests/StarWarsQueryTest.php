<?php

declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\GraphQL;
use PHPUnit\Framework\TestCase;

class StarWarsQueryTest extends TestCase
{
    /**
     * @see it('Correctly identifies R2-D2 as the hero of the Star Wars Saga')
     */
    public function testCorrectlyIdentifiesR2D2AsTheHeroOfTheStarWarsSaga() : void
    {
        $query    = '
        query HeroNameQuery {
          hero {
            name
          }
        }
        ';
        $expected = [
            'hero' => ['name' => 'R2-D2'],
        ];
        $this->assertValidQuery($query, $expected);
    }

    /**
     * Helper function to test a query and the expected response.
     */
    private function assertValidQuery($query, $expected) : void
    {
        $this->assertEquals(
            ['data' => $expected],
            GraphQL::executeQuery(StarWarsSchema::build(), $query)->toArray()
        );
    }

    // Describe: Nested Queries

    /**
     * @see it('Allows us to query for the ID and friends of R2-D2')
     */
    public function testAllowsUsToQueryForTheIDAndFriendsOfR2D2() : void
    {
        $query    = '
        query HeroNameAndFriendsQuery {
          hero {
            id
            name
            friends {
              name
            }
          }
        }
        ';
        $expected = [
            'hero' => [
                'id'      => '2001',
                'name'    => 'R2-D2',
                'friends' => [
                    ['name' => 'Luke Skywalker'],
                    ['name' => 'Han Solo'],
                    ['name' => 'Leia Organa'],
                ],
            ],
        ];
        $this->assertValidQuery($query, $expected);
    }

    // Describe: Using IDs and query parameters to refetch objects

    /**
     * @see it('Allows us to query for the friends of friends of R2-D2')
     */
    public function testAllowsUsToQueryForTheFriendsOfFriendsOfR2D2() : void
    {
        $query    = '
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
        $expected = [
            'hero' => [
                'name'    => 'R2-D2',
                'friends' => [
                    [
                        'name'      => 'Luke Skywalker',
                        'appearsIn' => ['NEWHOPE', 'EMPIRE', 'JEDI'],
                        'friends'   => [
                            ['name' => 'Han Solo'],
                            ['name' => 'Leia Organa'],
                            ['name' => 'C-3PO'],
                            ['name' => 'R2-D2'],
                        ],
                    ],
                    [
                        'name'      => 'Han Solo',
                        'appearsIn' => ['NEWHOPE', 'EMPIRE', 'JEDI'],
                        'friends'   => [
                            ['name' => 'Luke Skywalker'],
                            ['name' => 'Leia Organa'],
                            ['name' => 'R2-D2'],
                        ],
                    ],
                    [
                        'name'      => 'Leia Organa',
                        'appearsIn' => ['NEWHOPE', 'EMPIRE', 'JEDI'],
                        'friends'   =>
                            [
                                ['name' => 'Luke Skywalker'],
                                ['name' => 'Han Solo'],
                                ['name' => 'C-3PO'],
                                ['name' => 'R2-D2'],
                            ],
                    ],
                ],
            ],
        ];
        $this->assertValidQuery($query, $expected);
    }

    /**
     * @see it('Using IDs and query parameters to refetch objects')
     */
    public function testAllowsUsToQueryForLukeSkywalkerDirectlyUsingHisID() : void
    {
        $query    = '
        query FetchLukeQuery {
          human(id: "1000") {
            name
          }
        }
        ';
        $expected = [
            'human' => ['name' => 'Luke Skywalker'],
        ];

        $this->assertValidQuery($query, $expected);
    }

    /**
     * @see it('Allows us to create a generic query, then use it to fetch Luke Skywalker using his ID')
     */
    public function testGenericQueryToGetLukeSkywalkerById() : void
    {
        $query    = '
        query FetchSomeIDQuery($someId: String!) {
          human(id: $someId) {
            name
          }
        }
        ';
        $params   = ['someId' => '1000'];
        $expected = [
            'human' => ['name' => 'Luke Skywalker'],
        ];

        $this->assertValidQueryWithParams($query, $params, $expected);
    }

    /**
     * Helper function to test a query with params and the expected response.
     */
    private function assertValidQueryWithParams($query, $params, $expected)
    {
        $this->assertEquals(
            ['data' => $expected],
            GraphQL::executeQuery(StarWarsSchema::build(), $query, null, null, $params)->toArray()
        );
    }

    // Using aliases to change the key in the response

    /**
     * @see it('Allows us to create a generic query, then use it to fetch Han Solo using his ID')
     */
    public function testGenericQueryToGetHanSoloById() : void
    {
        $query    = '
        query FetchSomeIDQuery($someId: String!) {
          human(id: $someId) {
            name
          }
        }
        ';
        $params   = ['someId' => '1002'];
        $expected = [
            'human' => ['name' => 'Han Solo'],
        ];
        $this->assertValidQueryWithParams($query, $params, $expected);
    }

    /**
     * @see it('Allows us to create a generic query, then pass an invalid ID to get null back')
     */
    public function testGenericQueryWithInvalidId() : void
    {
        $query    = '
        query humanQuery($id: String!) {
          human(id: $id) {
            name
          }
        }
        ';
        $params   = ['id' => 'not a valid id'];
        $expected = ['human' => null];
        $this->assertValidQueryWithParams($query, $params, $expected);
    }

    // Uses fragments to express more complex queries

    /**
     * @see it('Allows us to query for Luke, changing his key with an alias')
     */
    public function testLukeKeyAlias() : void
    {
        $query    = '
        query FetchLukeAliased {
          luke: human(id: "1000") {
            name
          }
        }
        ';
        $expected = [
            'luke' => ['name' => 'Luke Skywalker'],
        ];
        $this->assertValidQuery($query, $expected);
    }

    /**
     * @see it('Allows us to query for both Luke and Leia, using two root fields and an alias')
     */
    public function testTwoRootKeysAsAnAlias() : void
    {
        $query    = '
        query FetchLukeAndLeiaAliased {
          luke: human(id: "1000") {
            name
          }
          leia: human(id: "1003") {
            name
          }
        }
        ';
        $expected = [
            'luke' => ['name' => 'Luke Skywalker'],
            'leia' => ['name' => 'Leia Organa'],
        ];
        $this->assertValidQuery($query, $expected);
    }

    /**
     * @see it('Allows us to query using duplicated content')
     */
    public function testQueryUsingDuplicatedContent() : void
    {
        $query    = '
        query DuplicateFields {
          luke: human(id: "1000") {
            name
            homePlanet
          }
          leia: human(id: "1003") {
            name
            homePlanet
          }
        }
        ';
        $expected = [
            'luke' => [
                'name'       => 'Luke Skywalker',
                'homePlanet' => 'Tatooine',
            ],
            'leia' => [
                'name'       => 'Leia Organa',
                'homePlanet' => 'Alderaan',
            ],
        ];
        $this->assertValidQuery($query, $expected);
    }

    /**
     * @see it('Allows us to use a fragment to avoid duplicating content')
     */
    public function testUsingFragment() : void
    {
        $query = '
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

        $expected = [
            'luke' => [
                'name'       => 'Luke Skywalker',
                'homePlanet' => 'Tatooine',
            ],
            'leia' => [
                'name'       => 'Leia Organa',
                'homePlanet' => 'Alderaan',
            ],
        ];
        $this->assertValidQuery($query, $expected);
    }

    /**
     * @see it('Using __typename to find the type of an object')
     */
    public function testVerifyThatR2D2IsADroid() : void
    {
        $query    = '
        query CheckTypeOfR2 {
          hero {
            __typename
            name
          }
        }
        ';
        $expected = [
            'hero' => [
                '__typename' => 'Droid',
                'name'       => 'R2-D2',
            ],
        ];
        $this->assertValidQuery($query, $expected);
    }

    /**
     * @see it('Allows us to verify that Luke is a human')
     */
    public function testVerifyThatLukeIsHuman() : void
    {
        $query = '
        query CheckTypeOfLuke {
          hero(episode: EMPIRE) {
            __typename
            name
          }
        }
        ';

        $expected = [
            'hero' => [
                '__typename' => 'Human',
                'name'       => 'Luke Skywalker',
            ],
        ];

        $this->assertValidQuery($query, $expected);
    }
}
