<?php

declare(strict_types=1);

namespace GraphQL\Tests;

use GraphQL\GraphQL;
use PHPUnit\Framework\TestCase;

class StarWarsIntrospectionTest extends TestCase
{
    // Star Wars Introspection Tests
    // Basic Introspection

    /**
     * @see it('Allows querying the schema for types')
     */
    public function testAllowsQueryingTheSchemaForTypes(): void
    {
        $query    = '
        query IntrospectionTypeQuery {
          __schema {
            types {
              name
            }
          }
        }
        ';
        $expected = [
            '__schema' => [
                'types' => [
                    ['name' => 'Query'],
                    ['name' => 'Episode'],
                    ['name' => 'Character'],
                    ['name' => 'String'],
                    ['name' => 'Human'],
                    ['name' => 'Droid'],
                    ['name' => 'ID'],
                    ['name' => 'Float'],
                    ['name' => 'Int'],
                    ['name' => 'Boolean'],
                    ['name' => '__Schema'],
                    ['name' => '__Type'],
                    ['name' => '__TypeKind'],
                    ['name' => '__Field'],
                    ['name' => '__InputValue'],
                    ['name' => '__EnumValue'],
                    ['name' => '__Directive'],
                    ['name' => '__DirectiveLocation'],
                ],
            ],
        ];
        self::assertValidQuery($query, $expected);
    }

    /**
     * Helper function to test a query and the expected response.
     */
    private static function assertValidQuery($query, $expected): void
    {
        self::assertEquals(['data' => $expected], GraphQL::executeQuery(StarWarsSchema::build(), $query)->toArray());
    }

    /**
     * @see it('Allows querying the schema for query type')
     */
    public function testAllowsQueryingTheSchemaForQueryType(): void
    {
        $query    = '
        query IntrospectionQueryTypeQuery {
          __schema {
            queryType {
              name
            }
          }
        }
        ';
        $expected = [
            '__schema' => [
                'queryType' => ['name' => 'Query'],
            ],
        ];
        self::assertValidQuery($query, $expected);
    }

    /**
     * @see it('Allows querying the schema for a specific type')
     */
    public function testAllowsQueryingTheSchemaForASpecificType(): void
    {
        $query    = '
        query IntrospectionDroidTypeQuery {
          __type(name: "Droid") {
            name
          }
        }
        ';
        $expected = [
            '__type' => ['name' => 'Droid'],
        ];
        self::assertValidQuery($query, $expected);
    }

    /**
     * @see it('Allows querying the schema for an object kind')
     */
    public function testAllowsQueryingForAnObjectKind(): void
    {
        $query    = '
        query IntrospectionDroidKindQuery {
          __type(name: "Droid") {
            name
            kind
          }
        }
        ';
        $expected = [
            '__type' => [
                'name' => 'Droid',
                'kind' => 'OBJECT',
            ],
        ];
        self::assertValidQuery($query, $expected);
    }

    /**
     * @see it('Allows querying the schema for an interface kind')
     */
    public function testAllowsQueryingForInterfaceKind(): void
    {
        $query    = '
        query IntrospectionCharacterKindQuery {
          __type(name: "Character") {
            name
            kind
          }
        }
        ';
        $expected = [
            '__type' => [
                'name' => 'Character',
                'kind' => 'INTERFACE',
            ],
        ];
        self::assertValidQuery($query, $expected);
    }

    /**
     * @see it('Allows querying the schema for object fields')
     */
    public function testAllowsQueryingForObjectFields(): void
    {
        $query    = '
        query IntrospectionDroidFieldsQuery {
          __type(name: "Droid") {
            name
            fields {
              name
              type {
                name
                kind
              }
            }
          }
        }
        ';
        $expected = [
            '__type' => [
                'name'   => 'Droid',
                'fields' => [
                    [
                        'name' => 'id',
                        'type' => [
                            'name' => null,
                            'kind' => 'NON_NULL',
                        ],
                    ],
                    [
                        'name' => 'name',
                        'type' => [
                            'name' => 'String',
                            'kind' => 'SCALAR',
                        ],
                    ],
                    [
                        'name' => 'friends',
                        'type' => [
                            'name' => null,
                            'kind' => 'LIST',
                        ],
                    ],
                    [
                        'name' => 'appearsIn',
                        'type' => [
                            'name' => null,
                            'kind' => 'LIST',
                        ],
                    ],
                    [
                        'name' => 'secretBackstory',
                        'type' => [
                            'name' => 'String',
                            'kind' => 'SCALAR',
                        ],
                    ],
                    [
                        'name' => 'primaryFunction',
                        'type' => [
                            'name' => 'String',
                            'kind' => 'SCALAR',
                        ],
                    ],
                ],
            ],
        ];
        self::assertValidQuery($query, $expected);
    }

    /**
     * @see it('Allows querying the schema for nested object fields')
     */
    public function testAllowsQueryingTheSchemaForNestedObjectFields(): void
    {
        $query    = '
        query IntrospectionDroidNestedFieldsQuery {
          __type(name: "Droid") {
            name
            fields {
              name
              type {
                name
                kind
                ofType {
                  name
                  kind
                }
              }
            }
          }
        }
        ';
        $expected = [
            '__type' => [
                'name'   => 'Droid',
                'fields' => [
                    [
                        'name' => 'id',
                        'type' => [
                            'name'   => null,
                            'kind'   => 'NON_NULL',
                            'ofType' => [
                                'name' => 'String',
                                'kind' => 'SCALAR',
                            ],
                        ],
                    ],
                    [
                        'name' => 'name',
                        'type' => [
                            'name'   => 'String',
                            'kind'   => 'SCALAR',
                            'ofType' => null,
                        ],
                    ],
                    [
                        'name' => 'friends',
                        'type' => [
                            'name'   => null,
                            'kind'   => 'LIST',
                            'ofType' => [
                                'name' => 'Character',
                                'kind' => 'INTERFACE',
                            ],
                        ],
                    ],
                    [
                        'name' => 'appearsIn',
                        'type' => [
                            'name'   => null,
                            'kind'   => 'LIST',
                            'ofType' => [
                                'name' => 'Episode',
                                'kind' => 'ENUM',
                            ],
                        ],
                    ],
                    [
                        'name' => 'secretBackstory',
                        'type' => [
                            'name'   => 'String',
                            'kind'   => 'SCALAR',
                            'ofType' => null,
                        ],
                    ],
                    [
                        'name' => 'primaryFunction',
                        'type' => [
                            'name'   => 'String',
                            'kind'   => 'SCALAR',
                            'ofType' => null,
                        ],
                    ],
                ],
            ],
        ];
        self::assertValidQuery($query, $expected);
    }

    /**
     * @see it('Allows querying the schema for field args')
     */
    public function testAllowsQueryingTheSchemaForFieldArgs(): void
    {
        $query = '
        query IntrospectionQueryTypeQuery {
          __schema {
            queryType {
              fields {
                name
                args {
                  name
                  description
                  type {
                    name
                    kind
                    ofType {
                      name
                      kind
                    }
                  }
                  defaultValue
                }
              }
            }
          }
        }
        ';

        $expected = [
            '__schema' => [
                'queryType' => [
                    'fields' => [
                        [
                            'name' => 'hero',
                            'args' => [
                                [
                                    'defaultValue' => null,
                                    'description'  => 'If omitted, returns the hero of the whole saga. If provided, returns the hero of that particular episode.',
                                    'name'         => 'episode',
                                    'type'         => [
                                        'kind'   => 'ENUM',
                                        'name'   => 'Episode',
                                        'ofType' => null,
                                    ],
                                ],
                            ],
                        ],
                        [
                            'name' => 'human',
                            'args' => [
                                [
                                    'name'         => 'id',
                                    'description'  => 'id of the human',
                                    'type'         => [
                                        'kind'   => 'NON_NULL',
                                        'name'   => null,
                                        'ofType' => [
                                            'kind' => 'SCALAR',
                                            'name' => 'String',
                                        ],
                                    ],
                                    'defaultValue' => null,
                                ],
                            ],
                        ],
                        [
                            'name' => 'droid',
                            'args' => [
                                [
                                    'name'         => 'id',
                                    'description'  => 'id of the droid',
                                    'type'         => [
                                        'kind'   => 'NON_NULL',
                                        'name'   => null,
                                        'ofType' =>
                                            [
                                                'kind' => 'SCALAR',
                                                'name' => 'String',
                                            ],
                                    ],
                                    'defaultValue' => null,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        self::assertValidQuery($query, $expected);
    }

    /**
     * @see it('Allows querying the schema for documentation')
     */
    public function testAllowsQueryingTheSchemaForDocumentation(): void
    {
        $query    = '
        query IntrospectionDroidDescriptionQuery {
          __type(name: "Droid") {
            name
            description
          }
        }
        ';
        $expected = [
            '__type' => [
                'name'        => 'Droid',
                'description' => 'A mechanical creature in the Star Wars universe.',
            ],
        ];
        self::assertValidQuery($query, $expected);
    }
}
