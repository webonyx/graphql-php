<?php declare(strict_types=1);

namespace GraphQL\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\SchemaExtensionNode;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Utils;

/**
 * Configuration options for schema construction.
 *
 * The options accepted by the **create** method are described
 * in the [schema definition docs](schema-definition.md#configuration-options).
 *
 * Usage example:
 *
 *     $config = SchemaConfig::create()
 *         ->setQuery($myQueryType)
 *         ->setTypeLoader($myTypeLoader);
 *
 *     $schema = new Schema($config);
 *
 * @see Type, NamedType
 *
 * @phpstan-type MaybeLazyObjectType ObjectType|(callable(): (ObjectType|null))|null
 * @phpstan-type TypeLoader callable(string $typeName): ((Type&NamedType)|null)
 * @phpstan-type Types iterable<Type&NamedType>|(callable(): iterable<Type&NamedType>)|iterable<(callable(): Type&NamedType)>|(callable(): iterable<(callable(): Type&NamedType)>)
 * @phpstan-type SchemaConfigOptions array{
 *   description?: string|null,
 *   query?: MaybeLazyObjectType,
 *   mutation?: MaybeLazyObjectType,
 *   subscription?: MaybeLazyObjectType,
 *   types?: Types|null,
 *   scalarOverrides?: array<ScalarType>|null,
 *   directives?: array<Directive>|null,
 *   typeLoader?: TypeLoader|null,
 *   assumeValid?: bool|null,
 *   astNode?: SchemaDefinitionNode|null,
 *   extensionASTNodes?: array<SchemaExtensionNode>|null,
 * }
 */
class SchemaConfig
{
    public ?string $description = null;

    /** @var MaybeLazyObjectType */
    public $query;

    /** @var MaybeLazyObjectType */
    public $mutation;

    /** @var MaybeLazyObjectType */
    public $subscription;

    /**
     * @var iterable|callable
     *
     * @phpstan-var Types
     */
    public $types = [];

    /**
     * Replacements for built-in scalar types, keyed by their name.
     *
     * When null, they are discovered by scanning **types**, which requires
     * resolving it fully even when it is given as a lazy callable.
     * Pass replacement scalars explicitly (or an empty array for none)
     * to skip the scan and keep lazy type loading lazy.
     *
     * @var array<string, ScalarType>|null
     */
    public ?array $scalarOverrides = null;

    /** @var array<Directive>|null */
    public ?array $directives = null;

    /**
     * @var callable|null
     *
     * @phpstan-var TypeLoader|null
     */
    public $typeLoader;

    public bool $assumeValid = false;

    public ?SchemaDefinitionNode $astNode = null;

    /** @var array<SchemaExtensionNode> */
    public array $extensionASTNodes = [];

    /**
     * Converts an array of options to instance of SchemaConfig
     * (or just returns empty config when array is not passed).
     *
     * @phpstan-param SchemaConfigOptions $options
     *
     * @throws InvariantViolation
     *
     * @api
     */
    public static function create(array $options = []): self
    {
        $config = new static();

        if ($options !== []) {
            if (isset($options['description'])) {
                $config->setDescription($options['description']);
            }
            if (isset($options['query'])) {
                $config->setQuery($options['query']);
            }

            if (isset($options['mutation'])) {
                $config->setMutation($options['mutation']);
            }

            if (isset($options['subscription'])) {
                $config->setSubscription($options['subscription']);
            }

            if (isset($options['types'])) {
                $config->setTypes($options['types']);
            }

            if (isset($options['scalarOverrides'])) {
                $config->setScalarOverrides($options['scalarOverrides']);
            }

            if (isset($options['directives'])) {
                $config->setDirectives($options['directives']);
            }

            if (isset($options['typeLoader'])) {
                $config->setTypeLoader($options['typeLoader']);
            }

            if (isset($options['assumeValid'])) {
                $config->setAssumeValid($options['assumeValid']);
            }

            if (isset($options['astNode'])) {
                $config->setAstNode($options['astNode']);
            }

            if (isset($options['extensionASTNodes'])) {
                $config->setExtensionASTNodes($options['extensionASTNodes']);
            }
        }

        return $config;
    }

    /** @api */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /** @api */
    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return MaybeLazyObjectType
     *
     * @api
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param MaybeLazyObjectType $query
     *
     * @throws InvariantViolation
     *
     * @api
     */
    public function setQuery($query): self
    {
        $this->assertMaybeLazyObjectType($query);
        $this->query = $query;

        return $this;
    }

    /**
     * @return MaybeLazyObjectType
     *
     * @api
     */
    public function getMutation()
    {
        return $this->mutation;
    }

    /**
     * @param MaybeLazyObjectType $mutation
     *
     * @throws InvariantViolation
     *
     * @api
     */
    public function setMutation($mutation): self
    {
        $this->assertMaybeLazyObjectType($mutation);
        $this->mutation = $mutation;

        return $this;
    }

    /**
     * @return MaybeLazyObjectType
     *
     * @api
     */
    public function getSubscription()
    {
        return $this->subscription;
    }

    /**
     * @param MaybeLazyObjectType $subscription
     *
     * @throws InvariantViolation
     *
     * @api
     */
    public function setSubscription($subscription): self
    {
        $this->assertMaybeLazyObjectType($subscription);
        $this->subscription = $subscription;

        return $this;
    }

    /**
     * @return array|callable
     *
     * @phpstan-return Types
     *
     * @api
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * @param array|callable $types
     *
     * @phpstan-param Types $types
     *
     * @api
     */
    public function setTypes($types): self
    {
        $this->types = $types;

        return $this;
    }

    /**
     * @return array<string, ScalarType>|null
     *
     * @api
     */
    public function getScalarOverrides(): ?array
    {
        return $this->scalarOverrides;
    }

    /**
     * Deeper validation (that each override is a ScalarType named after a built-in scalar)
     * runs during schema validation, see SchemaValidationContext::validateScalarOverrides().
     *
     * @param array<ScalarType>|null $scalarOverrides
     *
     * @api
     */
    public function setScalarOverrides(?array $scalarOverrides): self
    {
        if ($scalarOverrides === null) {
            $this->scalarOverrides = null;

            return $this;
        }

        $this->scalarOverrides = [];
        foreach ($scalarOverrides as $scalarOverride) {
            // Deeper validation (correct scalar names, actually being a ScalarType) happens
            // during schema validation, see SchemaValidationContext::validateScalarOverrides().
            // This assertion just catches basic misuse at development time and narrows the type
            // for the name-keyed map below.
            // @phpstan-ignore-next-line not strictly enforceable unless PHP gets generics
            assert($scalarOverride instanceof ScalarType, 'Expected instanceof ' . ScalarType::class . ', got: ' . Utils::printSafe($scalarOverride));

            $this->scalarOverrides[$scalarOverride->name] = $scalarOverride;
        }

        return $this;
    }

    /**
     * @return array<Directive>|null
     *
     * @api
     */
    public function getDirectives(): ?array
    {
        return $this->directives;
    }

    /**
     * @param array<Directive>|null $directives
     *
     * @api
     */
    public function setDirectives(?array $directives): self
    {
        $this->directives = $directives;

        return $this;
    }

    /**
     * @return callable|null $typeLoader
     *
     * @phpstan-return TypeLoader|null $typeLoader
     *
     * @api
     */
    public function getTypeLoader(): ?callable
    {
        return $this->typeLoader;
    }

    /**
     * @phpstan-param TypeLoader|null $typeLoader
     *
     * @api
     */
    public function setTypeLoader(?callable $typeLoader): self
    {
        $this->typeLoader = $typeLoader;

        return $this;
    }

    public function getAssumeValid(): bool
    {
        return $this->assumeValid;
    }

    public function setAssumeValid(bool $assumeValid): self
    {
        $this->assumeValid = $assumeValid;

        return $this;
    }

    public function getAstNode(): ?SchemaDefinitionNode
    {
        return $this->astNode;
    }

    public function setAstNode(?SchemaDefinitionNode $astNode): self
    {
        $this->astNode = $astNode;

        return $this;
    }

    /** @return array<SchemaExtensionNode> */
    public function getExtensionASTNodes(): array
    {
        return $this->extensionASTNodes;
    }

    /** @param array<SchemaExtensionNode> $extensionASTNodes */
    public function setExtensionASTNodes(array $extensionASTNodes): self
    {
        $this->extensionASTNodes = $extensionASTNodes;

        return $this;
    }

    /**
     * @param mixed $maybeLazyObjectType Should be MaybeLazyObjectType
     *
     * @throws InvariantViolation
     */
    protected function assertMaybeLazyObjectType($maybeLazyObjectType): void
    {
        if ($maybeLazyObjectType instanceof ObjectType || is_callable($maybeLazyObjectType) || is_null($maybeLazyObjectType)) {
            return;
        }

        $notMaybeLazyObjectType = is_object($maybeLazyObjectType)
            ? get_class($maybeLazyObjectType)
            : gettype($maybeLazyObjectType);
        $objectTypeClass = ObjectType::class;
        throw new InvariantViolation("Expected instanceof {$objectTypeClass}, a callable that returns such an instance, or null, got: {$notMaybeLazyObjectType}.");
    }
}
