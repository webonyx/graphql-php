<?php
namespace GraphQL\Tests;


use GraphQL\GraphQL;

class StarWarsQueryTest extends \PHPUnit_Framework_TestCase
{
    // Star Wars Query Tests
    // Basic Queries

    /**
     * @it Correctly identifies R2-D2 as the hero of the Star Wars Saga
     */
    public function testCorrectlyIdentifiesR2D2AsTheHeroOfTheStarWarsSaga()
    {
        $query = '
        query HeroNameQuery {
          hero {
            name
          }
        }
        ';
        $expected = [
            'hero' => [
                'name' => 'R2-D2'
            ]
        ];
        $this->assertValidQuery($query, $expected);
    }

    /**
     * @it Allows us to query for the ID and friends of R2-D2
     */
    public function testAllowsUsToQueryForTheIDAndFriendsOfR2D2()
    {
        $query = '
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
                'id' => '2001',
                'name' => 'R2-D2',
                'friends' => [
                    [
                        'name' => 'Luke Skywalker',
                    ],
                    [
                        'name' => 'Han Solo',
                    ],
                    [
                        'name' => 'Leia Organa',
                    ],
                ]
            ]
        ];
        $this->assertValidQuery($query, $expected);
    }

    // Describe: Nested Queries

    /**
     * @it Allows us to query for the friends of friends of R2-D2
     */
    public function testAllowsUsToQueryForTheFriendsOfFriendsOfR2D2()
    {
        $query = '
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
                'name' => 'R2-D2',
                'friends' => [
                    [
                        'name' => 'Luke Skywalker',
                        'appearsIn' => ['NEWHOPE', 'EMPIRE', 'JEDI',],
                        'friends' => [
                            ['name' => 'Han Solo',],
                            ['name' => 'Leia Organa',],
                            ['name' => 'C-3PO',],
                            ['name' => 'R2-D2',],
                        ],
                    ],
                    [
                        'name' => 'Han Solo',
                        'appearsIn' => ['NEWHOPE', 'EMPIRE', 'JEDI'],
                        'friends' => [
                            ['name' => 'Luke Skywalker',],
                            ['name' => 'Leia Organa'],
                            ['name' => 'R2-D2',],
                        ]
                    ],
                    [
                        'name' => 'Leia Organa',
                        'appearsIn' => ['NEWHOPE', 'EMPIRE', 'JEDI'],
                        'friends' =>
                            [
                                ['name' => 'Luke Skywalker',],
                                ['name' => 'Han Solo',],
                                ['name' => 'C-3PO',],
                                ['name' => 'R2-D2',],
                            ],
                    ],
                ],
            ]
        ];
        $this->assertValidQuery($query, $expected);
    }

    // Describe: Using IDs and query parameters to refetch objects

    /**
     * @it Using IDs and query parameters to refetch objects
     */
    public function testAllowsUsToQueryForLukeSkywalkerDirectlyUsingHisID()
    {
        $query = '
        query FetchLukeQuery {
          human(id: "1000") {
            name
          }
        }
        ';
        $expected = [
            'human' => [
                'name' => 'Luke Skywalker'
            ]
        ];

        $this->assertValidQuery($query, $expected);
    }

    /**
     * @it Allows us to create a generic query, then use it to fetch Luke Skywalker using his ID
     */
    public function testGenericQueryToGetLukeSkywalkerById()
    {
        $query = '
        query FetchSomeIDQuery($someId: String!) {
          human(id: $someId) {
            name
          }
        }
        ';
        $params = [
            'someId' => '1000'
        ];
        $expected = [
            'human' => [
                'name' => 'Luke Skywalker'
            ]
        ];

        $this->assertValidQueryWithParams($query, $params, $expected);
    }

    /**
     * @it Allows us to create a generic query, then use it to fetch Han Solo using his ID
     */
    public function testGenericQueryToGetHanSoloById()
    {
        $query = '
        query FetchSomeIDQuery($someId: String!) {
          human(id: $someId) {
            name
          }
        }
        ';
        $params = [
            'someId' => '1002'
        ];
        $expected = [
            'human' => [
                'name' => 'Han Solo'
            ]
        ];
        $this->assertValidQueryWithParams($query, $params, $expected);
    }

    /**
     * @it Allows us to create a generic query, then pass an invalid ID to get null back
     */
    public function testGenericQueryWithInvalidId()
    {
        $query = '
        query humanQuery($id: String!) {
          human(id: $id) {
            name
          }
        }
        ';
        $params = [
            'id' => 'not a valid id'
        ];
        $expected = [
            'human' => null
        ];
        $this->assertValidQueryWithParams($query, $params, $expected);
    }

    // Using aliases to change the key in the response

    /**
     * @it Allows us to query for Luke, changing his key with an alias
     */
    function testLukeKeyAlias()
    {
        $query = '
        query FetchLukeAliased {
          luke: human(id: "1000") {
            name
          }
        }
        ';
        $expected = [
            'luke' => [
                'name' => 'Luke Skywalker'
            ],
        ];
        $this->assertValidQuery($query, $expected);
    }

    /**
     * @it Allows us to query for both Luke and Leia, using two root fields and an alias
     */
    function testTwoRootKeysAsAnAlias()
    {
        $query = '
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
            'luke' => [
                'name' => 'Luke Skywalker'
            ],
            'leia' => [
                'name' => 'Leia Organa'
            ]
        ];
        $this->assertValidQuery($query, $expected);
    }

    // Uses fragments to express more complex queries

    /**
     * @it Allows us to query using duplicated content
     */
    function testQueryUsingDuplicatedContent()
    {
        $query = '
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
                'name' => 'Luke Skywalker',
                'homePlanet' => 'Tatooine'
            ],
            'leia' => [
                'name' => 'Leia Organa',
                'homePlanet' => 'Alderaan'
            ]
        ];
        $this->assertValidQuery($query, $expected);
    }

    /**
     * @it Allows us to use a fragment to avoid duplicating content
     */
    function testUsingFragment()
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
                'name' => 'Luke Skywalker',
                'homePlanet' => 'Tatooine'
            ],
            'leia' => [
                'name' => 'Leia Organa',
                'homePlanet' => 'Alderaan'
            ]
        ];
        $this->assertValidQuery($query, $expected);
    }

    /**
     * @it Using __typename to find the type of an object
     */
    public function testVerifyThatR2D2IsADroid()
    {
        $query = '
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
                'name' => 'R2-D2'
            ],
        ];
        $this->assertValidQuery($query, $expected);
    }

    /**
     * @it Allows us to verify that Luke is a human
     */
    public function testVerifyThatLukeIsHuman()
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
                'name' => 'Luke Skywalker'
            ],
        ];

        $this->assertValidQuery($query, $expected);
    }

    /**
     * Helper function to test a query and the expected response.
     */
    private function assertValidQuery($query, $expected)
    {
        $this->assertEquals(['data' => $expected], GraphQL::execute(StarWarsSchema::build(), $query));
    }

    /**
     * Helper function to test a query with params and the expected response.
     */
    private function assertValidQueryWithParams($query, $params, $expected)
    {
        $this->assertEquals(['data' => $expected], GraphQL::execute(StarWarsSchema::build(), $query, null, null, $params));
    }
}
