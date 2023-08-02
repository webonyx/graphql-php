<?php declare(strict_types=1);

namespace GraphQL\Type;

use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\SchemaExtensionNode;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

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
 * @phpstan-type TypeLoader callable(string $typeName): ((Type&NamedType)|null)
 * @phpstan-type Types iterable<Type&NamedType>|(callable(): iterable<Type&NamedType>)
 * @phpstan-type SchemaConfigOptions array{
 *   query?: ObjectType|(callable(): ObjectType)|null,
 *   mutation?: ObjectType|(callable(): ObjectType)|null,
 *   subscription?: ObjectType|(callable(): ObjectType)|null,
 *   types?: Types|null,
 *   directives?: array<Directive>|null,
 *   typeLoader?: TypeLoader|null,
 *   assumeValid?: bool|null,
 *   astNode?: SchemaDefinitionNode|null,
 *   extensionASTNodes?: array<SchemaExtensionNode>|null,
 * }
 */
class SchemaConfig
{
    /** @var ObjectType|(callable(): ObjectType)|null */
    public $query;

    /** @var ObjectType|(callable(): ObjectType)|null */
    public $mutation;

    /** @var ObjectType|(callable(): ObjectType)|null */
    public $subscription;

    /**
     * @var iterable|callable
     *
     * @phpstan-var Types
     */
    public $types = [];

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
     * @api
     */
    public static function create(array $options = []): self
    {
        $config = new static();

        if ($options !== []) {
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

    /**
     * @return ObjectType|(callable(): ObjectType)|null
     *
     * @api
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param ObjectType|(callable(): ObjectType)|null $query
     *
     * @api
     */
    public function setQuery($query): self
    {
        assert(is_callable($query) || $query instanceof ObjectType || is_null($query), 'Invalid Query');

        $this->query = $query;

        return $this;
    }

    /**
     * @return ObjectType|(callable(): ObjectType)|null
     *
     * @api
     */
    public function getMutation()
    {
        return $this->mutation;
    }

    /**
     * @param ObjectType|(callable(): ObjectType)|null $mutation
     *
     * @api
     */
    public function setMutation($mutation): self
    {
        assert(is_callable($mutation) || $mutation instanceof ObjectType || is_null($mutation), 'Invalid Mutation');

        $this->mutation = $mutation;

        return $this;
    }

    /**
     * @return ObjectType|(callable(): ObjectType)|null
     *
     * @api
     */
    public function getSubscription()
    {
        return $this->subscription;
    }

    /**
     * @param ObjectType|(callable(): ObjectType)|null $subscription
     *
     * @api
     */
    public function setSubscription($subscription): self
    {
        assert(is_callable($subscription) || $subscription instanceof ObjectType || is_null($subscription), 'Invalid Subscription');

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
}
