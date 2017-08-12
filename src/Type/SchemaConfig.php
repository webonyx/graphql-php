<?php
namespace GraphQL\Type;

use GraphQL\Type\Descriptor;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Utils;

/**
 * Class Config
 * Note: properties are marked as public for performance reasons. They should be considered read-only.
 *
 * @package GraphQL
 */
class SchemaConfig
{
    /**
     * @var ObjectType
     */
    public $query;

    /**
     * @var ObjectType
     */
    public $mutation;

    /**
     * @var ObjectType
     */
    public $subscription;

    /**
     * @var Type[]|callable
     */
    public $types;

    /**
     * @var Directive[]
     */
    public $directives;

    /**
     * @var Descriptor
     */
    public $descriptor;

    /**
     * @var callable
     */
    public $typeLoader;

    /**
     * @param array $options
     * @return SchemaConfig
     */
    public static function create(array $options = [])
    {
        $config = new static();

        if (!empty($options)) {
            if (isset($options['query'])) {
                Utils::invariant(
                    $options['query'] instanceof ObjectType,
                    'Schema query must be Object Type if provided but got: %s',
                    Utils::getVariableType($options['query'])
                );
                $config->setQuery($options['query']);
            }

            if (isset($options['mutation'])) {
                Utils::invariant(
                    $options['mutation'] instanceof ObjectType,
                    'Schema mutation must be Object Type if provided but got: %s',
                    Utils::getVariableType($options['mutation'])
                );
                $config->setMutation($options['mutation']);
            }

            if (isset($options['subscription'])) {
                Utils::invariant(
                    $options['subscription'] instanceof ObjectType,
                    'Schema subscription must be Object Type if provided but got: %s',
                    Utils::getVariableType($options['subscription'])
                );
                $config->setSubscription($options['subscription']);
            }

            if (isset($options['types'])) {
                Utils::invariant(
                    is_array($options['types']) || is_callable($options['types']),
                    'Schema types must be array or callable if provided but got: %s',
                    Utils::getVariableType($options['types'])
                );
                $config->setTypes($options['types']);
            }

            if (isset($options['directives'])) {
                Utils::invariant(
                    is_array($options['directives']),
                    'Schema directives must be array if provided but got: %s',
                    Utils::getVariableType($options['directives'])
                );
                $config->setDirectives($options['directives']);
            }

            if (isset($options['typeLoader'])) {
                Utils::invariant(
                    is_callable($options['typeLoader']),
                    'Schema type loader must be callable if provided but got: %s',
                    Utils::getVariableType($options['typeLoader'])
                );
                $config->setTypeLoader($options['typeLoader']);
            }

            if (isset($options['descriptor'])) {
                Utils::invariant(
                    $options['descriptor'] instanceof Descriptor,
                    'Schema descriptor must be instance of GraphQL\Type\Descriptor but got: %s',
                    Utils::getVariableType($options['descriptor'])
                );
                $config->setDescriptor($options['descriptor']);
            }
        }

        return $config;
    }

    /**
     * @return ObjectType
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param ObjectType $query
     * @return SchemaConfig
     */
    public function setQuery(ObjectType $query)
    {
        $this->query = $query;
        return $this;
    }

    /**
     * @return ObjectType
     */
    public function getMutation()
    {
        return $this->mutation;
    }

    /**
     * @param ObjectType $mutation
     * @return SchemaConfig
     */
    public function setMutation(ObjectType $mutation)
    {
        $this->mutation = $mutation;
        return $this;
    }

    /**
     * @return ObjectType
     */
    public function getSubscription()
    {
        return $this->subscription;
    }

    /**
     * @param ObjectType $subscription
     * @return SchemaConfig
     */
    public function setSubscription(ObjectType $subscription)
    {
        $this->subscription = $subscription;
        return $this;
    }

    /**
     * @return Type[]
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * @param Type[]|callable $types
     * @return SchemaConfig
     */
    public function setTypes($types)
    {
        $this->types = $types;
        return $this;
    }

    /**
     * @return Directive[]
     */
    public function getDirectives()
    {
        return $this->directives;
    }

    /**
     * @param Directive[] $directives
     * @return SchemaConfig
     */
    public function setDirectives(array $directives)
    {
        $this->directives = $directives;
        return $this;
    }

    /**
     * @return Descriptor
     */
    public function getDescriptor()
    {
        return $this->descriptor;
    }

    /**
     * @param Descriptor $descriptor
     * @return SchemaConfig
     */
    public function setDescriptor(Descriptor $descriptor)
    {
        $this->descriptor = $descriptor;
        return $this;
    }

    /**
     * @return callable
     */
    public function getTypeLoader()
    {
        return $this->typeLoader;
    }

    /**
     * @param callable $typeLoader
     * @return SchemaConfig
     */
    public function setTypeLoader(callable $typeLoader)
    {
        $this->typeLoader = $typeLoader;
        return $this;
    }
}
