<?php
namespace GraphQL\Type;

use GraphQL\Utils\Utils;

class Descriptor implements \JsonSerializable
{
    public $version = '1.0';

    public $typeMap = [];

    public $possibleTypeMap = [];

    public $created;

    public static function fromArray(array $array)
    {
        Utils::invariant(
            isset($array['version'], $array['typeMap'], $array['possibleTypeMap'], $array['created']),
            __METHOD__ . ' expects array with keys "version", "typeMap", "possibleTypeMap", "created" but got keys: %s',
            implode(', ', array_keys($array))
        );
        Utils::invariant(
            is_string($array['version']) && '1.0' === $array['version'],
            __METHOD__ . ' expects array where "version" key equals to "1.0"'
        );
        Utils::invariant(
            is_array($array['typeMap']) && is_array($array['possibleTypeMap']),
            __METHOD__ . ' expects array where "typeMap" and "possibleTypeMap" keys are arrays'
        );
        Utils::invariant(
            is_int($array['created']),
            __METHOD__ . ' expects array where "created" key is integer timestamp'
        );

        $descriptor = new self();
        Utils::assign($descriptor, $array);
    }

    public function toArray()
    {
        return [
            'typeMap' => $this->typeMap,
            'version' => $this->version,
            'possibleTypeMap' => $this->possibleTypeMap,
            'created' => $this->created
        ];
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    function jsonSerialize()
    {
        return $this->toArray();
    }
}
