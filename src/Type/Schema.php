<?php

declare(strict_types=1);

namespace GraphQL\Type;

use Generator;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\GraphQL;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\SchemaTypeExtensionNode;
use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ImplementingType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Utils\InterfaceImplementations;
use GraphQL\Utils\TypeInfo;
use GraphQL\Utils\Utils;
use function implode;
use function is_array;
use function is_callable;
use function is_iterable;
use function sprintf;

/**
 * Schema Definition (see [schema definition docs](schema-definition.md)).
 *
 * A Schema is created by supplying the root types of each type of operation:
 * query, mutation (optional) and subscription (optional). A schema definition is
 * then supplied to the validator and executor. Usage Example:
 *
 *     $schema = new GraphQL\Type\Schema([
 *       'query' => $MyAppQueryRootType,
 *       'mutation' => $MyAppMutationRootType,
 *     ]);
 *
 * Or using Schema Config instance:
 *
 *     $config = GraphQL\Type\SchemaConfig::create()
 *         ->setQuery($MyAppQueryRootType)
 *         ->setMutation($MyAppMutationRootType);
 *
 *     $schema = new GraphQL\Type\Schema($config);
 */
class Schema
{
    private SchemaConfig $config;

    /**
     * Contains currently resolved schema types.
     *
     * @var array<string, Type&NamedType>
     */
    private array $resolvedTypes = [];

    /**
     * Lazily initialised.
     *
     * @var array<string, InterfaceImplementations>
     */
    private array $implementationsMap;

    /**
     * True when $resolvedTypes contains all possible schema types.
     */
    private bool $fullyLoaded = false;

    /** @var array<int, Error> */
    private array $validationErrors;

    /** @var array<int, SchemaTypeExtensionNode> */
    public array $extensionASTNodes = [];

    /**
     * @param SchemaConfig|array<string, mixed> $config
     *
     * @api
     */
    public function __construct($config)
    {
        if (is_array($config)) {
            $config = SchemaConfig::create($config);
        }

        // If this schema was built from a source known to be valid, then it may be
        // marked with assumeValid to avoid an additional type system validation.
        if ($config->getAssumeValid()) {
            $this->validationErrors = [];
        }

        $this->config = $config;
        $this->extensionASTNodes = $config->extensionASTNodes;

        // TODO can we make the following assumption hold true?
        // No need to check for the existence of the root query type
        // since we already validated the schema thus it must exist.
        $query = $config->query;
        if (null !== $query) {
            $this->resolvedTypes[$query->name] = $query;
        }

        $mutation = $config->mutation;
        if (null !== $mutation) {
            $this->resolvedTypes[$mutation->name] = $mutation;
        }

        $subscription = $config->subscription;
        if (null !== $subscription) {
            $this->resolvedTypes[$subscription->name] = $subscription;
        }

        $types = $this->config->types;
        if (is_array($types)) {
            foreach ($this->resolveAdditionalTypes() as $type) {
                $typeName = $type->name;
                if (isset($this->resolvedTypes[$typeName])) {
                    Utils::invariant(
                        $type === $this->resolvedTypes[$typeName],
                        'Schema must contain unique named types but contains multiple types named "' . $type . '" (see https://webonyx.github.io/graphql-php/type-definitions/#type-registry).'
                    );
                }

                $this->resolvedTypes[$typeName] = $type;
            }
            // @phpstan-ignore-next-line not strictly enforced until we can use actual union types
        } elseif (! is_callable($types)) {
            $invalidTypes = Utils::printSafe($types);

            throw new InvariantViolation("\"types\" must be array or callable if provided but got: {$invalidTypes}");
        }

        $this->resolvedTypes += Introspection::getTypes();

        if (isset($this->config->typeLoader)) {
            return;
        }

        // Perform full scan of the schema
        $this->getTypeMap();
    }

    /**
     * @return Generator<Type&NamedType>
     */
    private function resolveAdditionalTypes(): Generator
    {
        $types = $this->config->types;

        if (is_callable($types)) {
            $types = $types();
        }

        // @phpstan-ignore-next-line not strictly enforced unless PHP supports function types
        if (! is_iterable($types)) {
            $notIterable = Utils::printSafe($types);

            throw new InvariantViolation("Schema types callable must return iterable but got: {$notIterable}");
        }

        foreach ($types as $type) {
            yield self::resolveType($type);
        }
    }

    /**
     * Returns all types in this schema.
     *
     * This operation requires a full schema scan. Do not use in production environment.
     *
     * @return array<string, Type&NamedType> Keys represent type names, values are instances of corresponding type definitions
     *
     * @api
     */
    public function getTypeMap(): array
    {
        if (! $this->fullyLoaded) {
            $this->resolvedTypes = $this->collectAllTypes();
            $this->fullyLoaded = true;
        }

        return $this->resolvedTypes;
    }

    /**
     * @return array<Type&NamedType>
     */
    private function collectAllTypes(): array
    {
        /** @var array<Type&NamedType> $typeMap */
        $typeMap = [];
        foreach ($this->resolvedTypes as $type) {
            TypeInfo::extractTypes($type, $typeMap);
        }

        foreach ($this->getDirectives() as $directive) {
            // @phpstan-ignore-next-line generics are not strictly enforceable, error will be caught during schema validation
            if (! $directive instanceof Directive) {
                continue;
            }

            TypeInfo::extractTypesFromDirectives($directive, $typeMap);
        }

        // When types are set as array they are resolved in constructor
        if (is_callable($this->config->types)) {
            foreach ($this->resolveAdditionalTypes() as $type) {
                TypeInfo::extractTypes($type, $typeMap);
            }
        }

        return $typeMap;
    }

    /**
     * Returns a list of directives supported by this schema.
     *
     * @return array<Directive>
     *
     * @api
     */
    public function getDirectives(): array
    {
        return $this->config->directives ?? GraphQL::getStandardDirectives();
    }

    /**
     * @param mixed $typeLoaderReturn could be anything
     */
    public static function typeLoaderNotType($typeLoaderReturn): string
    {
        $typeClass = Type::class;
        $notType = Utils::printSafe($typeLoaderReturn);

        return "Type loader is expected to return an instanceof {$typeClass}, but it returned {$notType}";
    }

    public static function typeLoaderWrongTypeName(string $expectedTypeName, string $actualTypeName): string
    {
        return "Type loader is expected to return type {$expectedTypeName}, but it returned type {$actualTypeName}.";
    }

    public function getOperationType(string $operation): ?ObjectType
    {
        switch ($operation) {
            case 'query':
                return $this->getQueryType();

            case 'mutation':
                return $this->getMutationType();

            case 'subscription':
                return $this->getSubscriptionType();

            default:
                return null;
        }
    }

    /**
     * Returns root query type.
     *
     * @api
     */
    public function getQueryType(): ?ObjectType
    {
        return $this->config->query;
    }

    /**
     * Returns root mutation type.
     *
     * @api
     */
    public function getMutationType(): ?ObjectType
    {
        return $this->config->mutation;
    }

    /**
     * Returns schema subscription.
     *
     * @api
     */
    public function getSubscriptionType(): ?ObjectType
    {
        return $this->config->subscription;
    }

    /**
     * @api
     */
    public function getConfig(): SchemaConfig
    {
        return $this->config;
    }

    /**
     * Returns a type by name.
     *
     * @return (Type&NamedType)|null
     *
     * @api
     */
    public function getType(string $name): ?Type
    {
        if (! isset($this->resolvedTypes[$name])) {
            $type = Type::getStandardTypes()[$name]
                ?? $this->loadType($name);

            if (null === $type) {
                return null;
            }

            return $this->resolvedTypes[$name] = self::resolveType($type);
        }

        return $this->resolvedTypes[$name];
    }

    public function hasType(string $name): bool
    {
        return null !== $this->getType($name);
    }

    /**
     * @return (Type&NamedType)|null
     */
    private function loadType(string $typeName): ?Type
    {
        $typeLoader = $this->config->typeLoader;

        if (null === $typeLoader) {
            return $this->defaultTypeLoader($typeName);
        }

        $type = $typeLoader($typeName);

        if (null === $type) {
            return null;
        }

        // @phpstan-ignore-next-line not strictly enforceable unless PHP gets function types
        if (! $type instanceof Type) {
            throw new InvariantViolation(self::typeLoaderNotType($type));
        }

        if ($typeName !== $type->name) {
            throw new InvariantViolation(self::typeLoaderWrongTypeName($typeName, $type->name));
        }

        return $type;
    }

    /**
     * @return (Type&NamedType)|null
     */
    private function defaultTypeLoader(string $typeName): ?Type
    {
        // Default type loader simply falls back to collecting all types
        return $this->getTypeMap()[$typeName] ?? null;
    }

    /**
     * @template T of Type
     *
     * @param Type|callable $type
     * @phpstan-param T|callable():T $type
     *
     * @phpstan-return T
     */
    public static function resolveType($type): Type
    {
        if ($type instanceof Type) {
            return $type;
        }

        return $type();
    }

    /**
     * Returns all possible concrete types for given abstract type
     * (implementations for interfaces and members of union type for unions).
     *
     * This operation requires full schema scan. Do not use in production environment.
     *
     * @param AbstractType&Type $abstractType
     *
     * @return array<ObjectType>
     *
     * @api
     */
    public function getPossibleTypes(AbstractType $abstractType): array
    {
        if ($abstractType instanceof UnionType) {
            return $abstractType->getTypes();
        }
        /** @var InterfaceType $abstractType only other option */
        return $this->getImplementations($abstractType)->objects();
    }

    /**
     * Returns all types that implement a given interface type.
     *
     * This operation requires full schema scan. Do not use in production environment.
     *
     * @api
     */
    public function getImplementations(InterfaceType $abstractType): InterfaceImplementations
    {
        return $this->collectImplementations()[$abstractType->name];
    }

    /**
     * @return array<string, InterfaceImplementations>
     */
    private function collectImplementations(): array
    {
        if (! isset($this->implementationsMap)) {
            $this->implementationsMap = [];

            /** @var array<
             *     string,
             *     array{
             *         objects: array<int, ObjectType>,
             *         interfaces: array<int, InterfaceType>,
             *     }
             * > $foundImplementations
             */
            $foundImplementations = [];
            foreach ($this->getTypeMap() as $type) {
                if ($type instanceof InterfaceType) {
                    if (! isset($foundImplementations[$type->name])) {
                        $foundImplementations[$type->name] = ['objects' => [], 'interfaces' => []];
                    }

                    foreach ($type->getInterfaces() as $iface) {
                        if (! isset($foundImplementations[$iface->name])) {
                            $foundImplementations[$iface->name] = ['objects' => [], 'interfaces' => []];
                        }

                        $foundImplementations[$iface->name]['interfaces'][] = $type;
                    }
                } elseif ($type instanceof ObjectType) {
                    foreach ($type->getInterfaces() as $iface) {
                        if (! isset($foundImplementations[$iface->name])) {
                            $foundImplementations[$iface->name] = ['objects' => [], 'interfaces' => []];
                        }

                        $foundImplementations[$iface->name]['objects'][] = $type;
                    }
                }
            }

            foreach ($foundImplementations as $name => $implementations) {
                $this->implementationsMap[$name] = new InterfaceImplementations($implementations['objects'], $implementations['interfaces']);
            }
        }

        return $this->implementationsMap;
    }

    /**
     * Returns true if the given type is a sub type of the given abstract type.
     *
     * @param AbstractType&Type $abstractType
     * @param ImplementingType&Type $maybeSubType
     *
     * @api
     */
    public function isSubType(AbstractType $abstractType, ImplementingType $maybeSubType): bool
    {
        if ($abstractType instanceof InterfaceType) {
            return $maybeSubType->implementsInterface($abstractType);
        }

        /** @var UnionType $abstractType only other option */
        return $abstractType->isPossibleType($maybeSubType);
    }

    /**
     * Returns instance of directive by name.
     *
     * @api
     */
    public function getDirective(string $name): ?Directive
    {
        foreach ($this->getDirectives() as $directive) {
            if ($directive->name === $name) {
                return $directive;
            }
        }

        return null;
    }

    public function getAstNode(): ?SchemaDefinitionNode
    {
        return $this->config->getAstNode();
    }

    /**
     * Throws if the schema is not valid.
     *
     * This operation requires a full schema scan. Do not use in production environment.
     *
     * @throws InvariantViolation
     *
     * @api
     */
    public function assertValid(): void
    {
        $errors = $this->validate();

        if ([] !== $errors) {
            throw new InvariantViolation(implode("\n\n", $this->validationErrors));
        }

        $internalTypes = Type::getStandardTypes() + Introspection::getTypes();
        foreach ($this->getTypeMap() as $name => $type) {
            if (isset($internalTypes[$name])) {
                continue;
            }

            $type->assertValid();

            // Make sure type loader returns the same instance as registered in other places of schema
            if (null === $this->config->typeLoader) {
                continue;
            }

            Utils::invariant(
                $this->loadType($name) === $type,
                sprintf(
                    'Type loader returns different instance for %s than field/argument definitions. Make sure you always return the same instance for the same type name.',
                    $name
                )
            );
        }
    }

    /**
     * Validate the schema and return any errors.
     *
     * This operation requires a full schema scan. Do not use in production environment.
     *
     * @return array<int, Error>
     *
     * @api
     */
    public function validate(): array
    {
        // If this Schema has already been validated, return the previous results.
        if (isset($this->validationErrors)) {
            return $this->validationErrors;
        }

        // Validate the schema, producing a list of errors.
        $context = new SchemaValidationContext($this);
        $context->validateRootTypes();
        $context->validateDirectives();
        $context->validateTypes();

        // Persist the results of validation before returning to ensure validation
        // does not run multiple times for this schema.
        $this->validationErrors = $context->getErrors();

        return $this->validationErrors;
    }
}
