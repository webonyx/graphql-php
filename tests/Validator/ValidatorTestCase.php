<?php declare(strict_types=1);

namespace GraphQL\Tests\Validator;

use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use GraphQL\Error\InvariantViolation;
use GraphQL\Error\SyntaxError;
use GraphQL\Error\UserError;
use GraphQL\Language\AST\Node;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\ValidationRule;
use PHPUnit\Framework\TestCase;

abstract class ValidatorTestCase extends TestCase
{
    /**
     * @param array<string, mixed> $options
     *
     * @throws \Exception
     * @throws \ReflectionException
     * @throws InvariantViolation
     */
    protected function expectPassesRule(ValidationRule $rule, string $queryString, array $options = []): void
    {
        $this->expectValid(self::getTestSchema(), [$rule], $queryString, $options);
    }

    /**
     * @param array<ValidationRule> $rules
     * @param array<string, mixed> $options
     *
     * @throws \Exception
     * @throws \JsonException
     * @throws SyntaxError
     */
    protected function expectValid(Schema $schema, array $rules, string $queryString, array $options = []): void
    {
        self::assertSame(
            [],
            DocumentValidator::validate($schema, Parser::parse($queryString, $options), $rules),
            'Should validate'
        );
    }

    /** @throws InvariantViolation */
    public static function getTestSchema(): Schema
    {
        $Being = new InterfaceType([
            'name' => 'Being',
            'fields' => [
                'name' => [
                    'type' => Type::string(),
                    'args' => ['surname' => ['type' => Type::boolean()]],
                ],
            ],
        ]);

        $Pet = new InterfaceType([
            'name' => 'Pet',
            'fields' => [
                'name' => [
                    'type' => Type::string(),
                    'args' => ['surname' => ['type' => Type::boolean()]],
                ],
            ],
        ]);

        $Canine = new InterfaceType([
            'name' => 'Canine',
            'fields' => static fn (): array => [
                'name' => [
                    'type' => Type::string(),
                    'args' => ['surname' => ['type' => Type::boolean()]],
                ],
            ],
        ]);

        $DogCommand = new EnumType([
            'name' => 'DogCommand',
            'values' => [
                'SIT' => ['value' => 0],
                'HEEL' => ['value' => 1],
                'DOWN' => ['value' => 2],
            ],
        ]);

        $Dog = new ObjectType([
            'name' => 'Dog',
            'fields' => [
                'name' => [
                    'type' => Type::string(),
                    'args' => ['surname' => ['type' => Type::boolean()]],
                ],
                'nickname' => ['type' => Type::string()],
                'barkVolume' => ['type' => Type::int()],
                'barks' => ['type' => Type::boolean()],
                'doesKnowCommand' => [
                    'type' => Type::boolean(),
                    'args' => ['dogCommand' => ['type' => $DogCommand]],
                ],
                'isHousetrained' => [
                    'type' => Type::boolean(),
                    'args' => ['atOtherHomes' => ['type' => Type::boolean(), 'defaultValue' => true]],
                ],
                'isAtLocation' => [
                    'type' => Type::boolean(),
                    'args' => ['x' => ['type' => Type::int()], 'y' => ['type' => Type::int()]],
                ],
                'secretName' => [
                    'type' => Type::string(),
                    'visible' => false,
                ],
            ],
            'interfaces' => [$Being, $Pet, $Canine],
        ]);

        $Cat = new ObjectType([
            'name' => 'Cat',
            'fields' => static function () use (&$FurColor): array {
                return [
                    'name' => [
                        'type' => Type::string(),
                        'args' => ['surname' => ['type' => Type::boolean()]],
                    ],
                    'nickname' => ['type' => Type::string()],
                    'meows' => ['type' => Type::boolean()],
                    'meowVolume' => ['type' => Type::int()],
                    'furColor' => $FurColor,
                ];
            },
            'interfaces' => [$Being, $Pet],
        ]);

        $CatOrDog = new UnionType([
            'name' => 'CatOrDog',
            'types' => [$Dog, $Cat],
        ]);

        $Intelligent = new InterfaceType([
            'name' => 'Intelligent',
            'fields' => [
                'iq' => ['type' => Type::int()],
            ],
        ]);

        $Human = new ObjectType([
            'name' => 'Human',
            'interfaces' => [$Being, $Intelligent],
            'fields' => static function () use (&$Human, $Pet): array {
                assert($Human instanceof ObjectType);

                return [
                    'name' => [
                        'type' => Type::string(),
                        'args' => ['surname' => ['type' => Type::boolean()]],
                    ],
                    'pets' => ['type' => Type::listOf($Pet)],
                    'relatives' => ['type' => Type::listOf($Human)],
                    'iq' => ['type' => Type::int()],
                ];
            },
        ]);

        $Alien = new ObjectType([
            'name' => 'Alien',
            'interfaces' => [$Being, $Intelligent],
            'fields' => [
                'iq' => ['type' => Type::int()],
                'name' => [
                    'type' => Type::string(),
                    'args' => ['surname' => ['type' => Type::boolean()]],
                ],
                'numEyes' => ['type' => Type::int()],
            ],
        ]);

        $DogOrHuman = new UnionType([
            'name' => 'DogOrHuman',
            'types' => [$Dog, $Human],
        ]);

        $HumanOrAlien = new UnionType([
            'name' => 'HumanOrAlien',
            'types' => [$Human, $Alien],
        ]);

        $FurColor = new EnumType([
            'name' => 'FurColor',
            'values' => [
                'BROWN' => ['value' => 0],
                'BLACK' => ['value' => 1],
                'TAN' => ['value' => 2],
                'SPOTTED' => ['value' => 3],
                'NO_FUR' => ['value' => null],
            ],
        ]);

        $ComplexInput = new InputObjectType([
            'name' => 'ComplexInput',
            'fields' => [
                'requiredField' => ['type' => Type::nonNull(Type::boolean())],
                'nonNullField' => ['type' => Type::nonNull(Type::boolean()), 'defaultValue' => false],
                'intField' => ['type' => Type::int()],
                'stringField' => ['type' => Type::string()],
                'booleanField' => ['type' => Type::boolean()],
                'stringListField' => ['type' => Type::listOf(Type::string())],
            ],
        ]);

        $ComplicatedArgs = new ObjectType([
            'name' => 'ComplicatedArgs',
            'fields' => [
                'intArgField' => [
                    'type' => Type::string(),
                    'args' => ['intArg' => ['type' => Type::int()]],
                ],
                'nonNullIntArgField' => [
                    'type' => Type::string(),
                    'args' => ['nonNullIntArg' => ['type' => Type::nonNull(Type::int())]],
                ],
                'stringArgField' => [
                    'type' => Type::string(),
                    'args' => ['stringArg' => ['type' => Type::string()]],
                ],
                'booleanArgField' => [
                    'type' => Type::string(),
                    'args' => ['booleanArg' => ['type' => Type::boolean()]],
                ],
                'enumArgField' => [
                    'type' => Type::string(),
                    'args' => ['enumArg' => ['type' => $FurColor]],
                ],
                'floatArgField' => [
                    'type' => Type::string(),
                    'args' => ['floatArg' => ['type' => Type::float()]],
                ],
                'idArgField' => [
                    'type' => Type::string(),
                    'args' => ['idArg' => ['type' => Type::id()]],
                ],
                'stringListArgField' => [
                    'type' => Type::string(),
                    'args' => ['stringListArg' => ['type' => Type::listOf(Type::string())]],
                ],
                'stringListNonNullArgField' => [
                    'type' => Type::string(),
                    'args' => [
                        'stringListNonNullArg' => [
                            'type' => Type::listOf(Type::nonNull(Type::string())),
                        ],
                    ],
                ],
                'complexArgField' => [
                    'type' => Type::string(),
                    'args' => ['complexArg' => ['type' => $ComplexInput]],
                ],
                'multipleReqs' => [
                    'type' => Type::string(),
                    'args' => [
                        'req1' => ['type' => Type::nonNull(Type::int())],
                        'req2' => ['type' => Type::nonNull(Type::int())],
                    ],
                ],
                'nonNullFieldWithDefault' => [
                    'type' => Type::string(),
                    'args' => [
                        'arg' => ['type' => Type::nonNull(Type::int()), 'defaultValue' => 0],
                    ],
                ],
                'multipleOpts' => [
                    'type' => Type::string(),
                    'args' => [
                        'opt1' => [
                            'type' => Type::int(),
                            'defaultValue' => 0,
                        ],
                        'opt2' => [
                            'type' => Type::int(),
                            'defaultValue' => 0,
                        ],
                    ],
                ],
                'multipleOptAndReq' => [
                    'type' => Type::string(),
                    'args' => [
                        'req1' => ['type' => Type::nonNull(Type::int())],
                        'req2' => ['type' => Type::nonNull(Type::int())],
                        'opt1' => [
                            'type' => Type::int(),
                            'defaultValue' => 0,
                        ],
                        'opt2' => [
                            'type' => Type::int(),
                            'defaultValue' => 0,
                        ],
                    ],
                ],
            ],
        ]);

        $invalidScalar = new CustomScalarType([
            'name' => 'Invalid',
            'serialize' => static fn ($value) => $value,
            'parseLiteral' => static function (Node $node): void {
                throw new UserError("Invalid scalar is always invalid: {$node->kind}");
            },
            'parseValue' => static function ($value): void {
                throw new UserError("Invalid scalar is always invalid: {$value}");
            },
        ]);

        $anyScalar = new CustomScalarType([
            'name' => 'Any',
            'serialize' => static fn ($value) => $value,
            'parseValue' => static fn ($value) => $value,
            'parseLiteral' => static fn ($node) => $node,
        ]);

        $queryRoot = new ObjectType([
            'name' => 'QueryRoot',
            'fields' => [
                'human' => [
                    'args' => ['id' => ['type' => Type::id()]],
                    'type' => $Human,
                ],
                'alien' => ['type' => $Alien],
                'dog' => ['type' => $Dog],
                'cat' => ['type' => $Cat],
                'pet' => ['type' => $Pet],
                'catOrDog' => ['type' => $CatOrDog],
                'dogOrHuman' => ['type' => $DogOrHuman],
                'humanOrAlien' => ['type' => $HumanOrAlien],
                'complicatedArgs' => ['type' => $ComplicatedArgs],
                'invalidArg' => [
                    'args' => [
                        'arg' => ['type' => $invalidScalar],
                    ],
                    'type' => Type::string(),
                ],
                'anyArg' => [
                    'args' => ['arg' => ['type' => $anyScalar]],
                    'type' => Type::string(),
                ],
            ],
        ]);

        $subscriptionRoot = new ObjectType([
            'name' => 'SubscriptionRoot',
            'fields' => [
                'catSubscribe' => ['type' => $Cat],
                'barkSubscribe' => ['type' => $Dog],
            ],
        ]);

        return new Schema([
            'query' => $queryRoot,
            'subscription' => $subscriptionRoot,
            'directives' => [
                Directive::includeDirective(),
                Directive::skipDirective(),
                Directive::deprecatedDirective(),
                new Directive([
                    'name' => 'directive',
                    'locations' => [DirectiveLocation::FIELD, DirectiveLocation::FRAGMENT_DEFINITION],
                ]),
                new Directive([
                    'name' => 'directiveA',
                    'locations' => [DirectiveLocation::FIELD, DirectiveLocation::FRAGMENT_DEFINITION],
                ]),
                new Directive([
                    'name' => 'directiveB',
                    'locations' => [DirectiveLocation::FIELD, DirectiveLocation::FRAGMENT_DEFINITION],
                ]),
                new Directive([
                    'name' => 'repeatable',
                    'locations' => [DirectiveLocation::FIELD, DirectiveLocation::FRAGMENT_DEFINITION],
                    'isRepeatable' => true,
                ]),
                new Directive([
                    'name' => 'onQuery',
                    'locations' => [DirectiveLocation::QUERY],
                ]),
                new Directive([
                    'name' => 'onMutation',
                    'locations' => [DirectiveLocation::MUTATION],
                ]),
                new Directive([
                    'name' => 'onSubscription',
                    'locations' => [DirectiveLocation::SUBSCRIPTION],
                ]),
                new Directive([
                    'name' => 'onField',
                    'locations' => [DirectiveLocation::FIELD],
                ]),
                new Directive([
                    'name' => 'onFragmentDefinition',
                    'locations' => [DirectiveLocation::FRAGMENT_DEFINITION],
                ]),
                new Directive([
                    'name' => 'onFragmentSpread',
                    'locations' => [DirectiveLocation::FRAGMENT_SPREAD],
                ]),
                new Directive([
                    'name' => 'onInlineFragment',
                    'locations' => [DirectiveLocation::INLINE_FRAGMENT],
                ]),
                new Directive([
                    'name' => 'onVariableDefinition',
                    'locations' => [DirectiveLocation::VARIABLE_DEFINITION],
                ]),
            ],
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     * @param array<string, mixed> $options
     *
     * @throws \Exception
     * @throws \ReflectionException
     * @throws InvariantViolation
     *
     * @return array<int, Error>
     */
    protected function expectFailsRule(
        ValidationRule $rule,
        string $queryString,
        array $errors,
        array $options = []
    ): array {
        return $this->expectInvalid(self::getTestSchema(), [$rule], $queryString, $errors, $options);
    }

    /**
     * @param array<ValidationRule>|null $rules
     * @param array<int, array<string, mixed>> $expectedErrors
     * @param array<string, mixed> $options
     *
     * @throws \Exception
     * @throws \JsonException
     * @throws SyntaxError
     *
     * @return array<int, Error>
     */
    protected function expectInvalid(Schema $schema, ?array $rules, string $queryString, array $expectedErrors, array $options = []): array
    {
        $errors = DocumentValidator::validate($schema, Parser::parse($queryString, $options), $rules);

        self::assertNotEmpty($errors, 'GraphQL should not validate');
        self::assertEquals($expectedErrors, array_map([FormattedError::class, 'createFromException'], $errors));

        return $errors;
    }

    /**
     * @throws \Exception
     * @throws \JsonException
     * @throws SyntaxError
     */
    protected function expectPassesRuleWithSchema(Schema $schema, ValidationRule $rule, string $queryString): void
    {
        $this->expectValid($schema, [$rule], $queryString);
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     *
     * @throws \Exception
     * @throws \InvalidArgumentException
     * @throws \ReflectionException
     * @throws InvariantViolation
     * @throws SyntaxError
     */
    protected function expectFailsRuleWithSchema(
        Schema $schema,
        ValidationRule $rule,
        string $queryString,
        array $errors
    ): void {
        $this->expectInvalid($schema, [$rule], $queryString, $errors);
    }

    /**
     * @throws \Exception
     * @throws \JsonException
     * @throws \ReflectionException
     * @throws SyntaxError
     */
    protected function expectPassesCompleteValidation(string $queryString): void
    {
        $this->expectValid(self::getTestSchema(), DocumentValidator::allRules(), $queryString);
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     *
     * @throws \Exception
     * @throws \InvalidArgumentException
     * @throws \ReflectionException
     * @throws InvariantViolation
     */
    protected function expectFailsCompleteValidation(string $queryString, array $errors): void
    {
        $this->expectInvalid(self::getTestSchema(), DocumentValidator::allRules(), $queryString, $errors);
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     *
     * @throws \Exception
     * @throws \JsonException
     * @throws SyntaxError
     */
    protected function expectSDLErrorsFromRule(
        ValidationRule $rule,
        string $sdlString,
        ?Schema $schema = null,
        array $errors = []
    ): void {
        $actualErrors = DocumentValidator::validateSDL(Parser::parse($sdlString), $schema, [$rule]);
        self::assertEquals(
            $errors,
            array_map([FormattedError::class, 'createFromException'], $actualErrors)
        );
    }

    /** @throws \Exception */
    protected function expectValidSDL(ValidationRule $rule, string $sdlString, ?Schema $schema = null): void
    {
        $this->expectSDLErrorsFromRule($rule, $sdlString, $schema, []);
    }
}
