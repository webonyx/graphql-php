<?php
namespace GraphQL\Examples\Blog\Type;


use GraphQL\Examples\Blog\Type\Enum\ContentFormatEnum;
use GraphQL\Examples\Blog\TypeSystem;

/**
 * Class Field
 * @package GraphQL\Examples\Blog\Type
 */
class FieldDefinitions
{
    private $types;

    public function __construct(TypeSystem $types)
    {
        $this->types = $types;
    }

    private $htmlField;

    public function htmlField($name, $objectKey = null)
    {
        $objectKey = $objectKey ?: $name;

        return $this->htmlField ?: $this->htmlField = [
            'name' => $name,
        ];
    }
}
