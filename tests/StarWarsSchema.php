<?php
namespace GraphQL\Tests;

/**
 * This is designed to be an end-to-end test, demonstrating
 * the full GraphQL stack.
 *
 * We will create a GraphQL schema that describes the major
 * characters in the original Star Wars trilogy.
 *
 * NOTE: This may contain spoilers for the original Star
 * Wars trilogy.
 */
use GraphQL\Schema;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * Using our shorthand to describe type systems, the type system for our
 * Star Wars example is:
 *
 * enum Episode { NEWHOPE, EMPIRE, JEDI }
 *
 * interface Character {
 *   id: String!
 *   name: String
 *   friends: [Character]
 *   appearsIn: [Episode]
 * }
 *
 * type Human implements Character {
 *   id: String!
 *   name: String
 *   friends: [Character]
 *   appearsIn: [Episode]
 *   homePlanet: String
 * }
 *
 * type Droid implements Character {
 *   id: String!
 *   name: String
 *   friends: [Character]
 *   appearsIn: [Episode]
 *   primaryFunction: String
 * }
 *
 * type Query {
 *   hero(episode: Episode): Character
 *   human(id: String!): Human
 *   droid(id: String!): Droid
 * }
 *
 * We begin by setting up our schema.
 */

class StarWarsSchema
{
    public static function build()
    {
        /**
         * The original trilogy consists of three movies.
         *
         * This implements the following type system shorthand:
         *   enum Episode { NEWHOPE, EMPIRE, JEDI }
         */
        $episodeEnum = new EnumType([
            'name' => 'Episode',
            'description' => 'One of the films in the Star Wars Trilogy',
            'values' => [
                'NEWHOPE' => [
                    'value' => 4,
                    'description' => 'Released in 1977.'
                ],
                'EMPIRE' => [
                    'value' => 5,
                    'description' => 'Released in 1980.'
                ],
                'JEDI' => [
                    'value' => 6,
                    'description' => 'Released in 1983.'
                ],
            ]
        ]);

        $humanType = null;
        $droidType = null;

        /**
         * Characters in the Star Wars trilogy are either humans or droids.
         *
         * This implements the following type system shorthand:
         *   interface Character {
         *     id: String!
         *     name: String
         *     friends: [Character]
         *     appearsIn: [Episode]
         *   }
         */
        $characterInterface = new InterfaceType([
            'name' => 'Character',
            'description' => 'A character in the Star Wars Trilogy',
            'fields' => function() use (&$characterInterface, $episodeEnum) {
                return [
                    'id' => [
                        'type' => Type::nonNull(Type::string()),
                        'description' => 'The id of the character.',
                    ],
                    'name' => [
                        'type' => Type::string(),
                        'description' => 'The name of the character.'
                    ],
                    'friends' => [
                        'type' => Type::listOf($characterInterface),
                        'description' => 'The friends of the character, or an empty list if they have none.',
                    ],
                    'appearsIn' => [
                        'type' => Type::listOf($episodeEnum),
                        'description' => 'Which movies they appear in.'
                    ],
                    'secretBackstory' => [
                        'type' => Type::string(),
                        'description' => 'All secrets about their past.',
                    ],
                ];
            },
            'resolveType' => function ($obj) use (&$humanType, &$droidType) {
                return StarWarsData::getHuman($obj['id']) ? $humanType : $droidType;
            },
        ]);

        /**
         * We define our human type, which implements the character interface.
         *
         * This implements the following type system shorthand:
         *   type Human implements Character {
         *     id: String!
         *     name: String
         *     friends: [Character]
         *     appearsIn: [Episode]
         *     secretBackstory: String
         *   }
         */
        $humanType = new ObjectType([
            'name' => 'Human',
            'description' => 'A humanoid creature in the Star Wars universe.',
            'fields' => [
                'id' => [
                    'type' => new NonNull(Type::string()),
                    'description' => 'The id of the human.',
                ],
                'name' => [
                    'type' => Type::string(),
                    'description' => 'The name of the human.',
                ],
                'friends' => [
                    'type' => Type::listOf($characterInterface),
                    'description' => 'The friends of the human, or an empty list if they have none.',
                    'resolve' => function ($human, $args, $context, ResolveInfo $info) {
                        $fieldSelection = $info->getFieldSelection();
                        $fieldSelection['id'] = true;

                        $friends = array_map(
                            function($friend) use ($fieldSelection) {
                                return array_intersect_key($friend, $fieldSelection);
                            },
                            StarWarsData::getFriends($human)
                        );

                        return $friends;
                    },
                ],
                'appearsIn' => [
                    'type' => Type::listOf($episodeEnum),
                    'description' => 'Which movies they appear in.'
                ],
                'homePlanet' => [
                    'type' => Type::string(),
                    'description' => 'The home planet of the human, or null if unknown.'
                ],
                'secretBackstory' => [
                    'type' => Type::string(),
                    'description' => 'Where are they from and how they came to be who they are.',
                    'resolve' => function() {
                        // This is to demonstrate error reporting
                        throw new \Exception('secretBackstory is secret.');
                    },
                ],
            ],
            'interfaces' => [$characterInterface]
        ]);

        /**
         * The other type of character in Star Wars is a droid.
         *
         * This implements the following type system shorthand:
         *   type Droid implements Character {
         *     id: String!
         *     name: String
         *     friends: [Character]
         *     appearsIn: [Episode]
         *     secretBackstory: String
         *     primaryFunction: String
         *   }
         */
        $droidType = new ObjectType([
            'name' => 'Droid',
            'description' => 'A mechanical creature in the Star Wars universe.',
            'fields' => [
                'id' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => 'The id of the droid.',
                ],
                'name' => [
                    'type' => Type::string(),
                    'description' => 'The name of the droid.'
                ],
                'friends' => [
                    'type' => Type::listOf($characterInterface),
                    'description' => 'The friends of the droid, or an empty list if they have none.',
                    'resolve' => function ($droid) {
                        return StarWarsData::getFriends($droid);
                    },
                ],
                'appearsIn' => [
                    'type' => Type::listOf($episodeEnum),
                    'description' => 'Which movies they appear in.'
                ],
                'secretBackstory' => [
                    'type' => Type::string(),
                    'description' => 'Construction date and the name of the designer.',
                    'resolve' => function() {
                        // This is to demonstrate error reporting
                        throw new \Exception('secretBackstory is secret.');
                    },
                ],
                'primaryFunction' => [
                    'type' => Type::string(),
                    'description' => 'The primary function of the droid.'
                ]
            ],
            'interfaces' => [$characterInterface]
        ]);

        /**
         * This is the type that will be the root of our query, and the
         * entry point into our schema. It gives us the ability to fetch
         * objects by their IDs, as well as to fetch the undisputed hero
         * of the Star Wars trilogy, R2-D2, directly.
         *
         * This implements the following type system shorthand:
         *   type Query {
         *     hero(episode: Episode): Character
         *     human(id: String!): Human
         *     droid(id: String!): Droid
         *   }
         *
         */
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'hero' => [
                    'type' => $characterInterface,
                    'args' => [
                        'episode' => [
                            'description' => 'If omitted, returns the hero of the whole saga. If provided, returns the hero of that particular episode.',
                            'type' => $episodeEnum
                        ]
                    ],
                    'resolve' => function ($root, $args) {
                        return StarWarsData::getHero(isset($args['episode']) ? $args['episode'] : null);
                    },
                ],
                'human' => [
                    'type' => $humanType,
                    'args' => [
                        'id' => [
                            'name' => 'id',
                            'description' => 'id of the human',
                            'type' => Type::nonNull(Type::string())
                        ]
                    ],
                    'resolve' => function ($root, $args) {
                        $humans = StarWarsData::humans();
                        return isset($humans[$args['id']]) ? $humans[$args['id']] : null;
                    }
                ],
                'droid' => [
                    'type' => $droidType,
                    'args' => [
                        'id' => [
                            'name' => 'id',
                            'description' => 'id of the droid',
                            'type' => Type::nonNull(Type::string())
                        ]
                    ],
                    'resolve' => function ($root, $args) {
                        $droids = StarWarsData::droids();
                        return isset($droids[$args['id']]) ? $droids[$args['id']] : null;
                    }
                ]
            ]
        ]);

        return new Schema(['query' => $queryType]);
    }
}
