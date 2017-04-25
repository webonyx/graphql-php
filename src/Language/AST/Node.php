<?php
namespace GraphQL\Language\AST;

use GraphQL\Utils;

abstract class Node
{
    /**
      type Node = NameNode
    | DocumentNode
    | OperationDefinitionNode
    | VariableDefinitionNode
    | VariableNode
    | SelectionSetNode
    | FieldNode
    | ArgumentNode
    | FragmentSpreadNode
    | InlineFragmentNode
    | FragmentDefinitionNode
    | IntValueNode
    | FloatValueNode
    | StringValueNode
    | BooleanValueNode
    | EnumValueNode
    | ListValueNode
    | ObjectValueNode
    | ObjectFieldNode
    | DirectiveNode
    | ListTypeNode
    | NonNullTypeNode
     */

    public $kind;

    /**
     * @var Location
     */
    public $loc;

    /**
     * @param array $vars
     */
    public function __construct(array $vars)
    {
        Utils::assign($this, $vars);
    }

    /**
     * @return $this
     */
    public function cloneDeep()
    {
        return $this->cloneValue($this);
    }

    /**
     * @param $value
     * @return array|Node
     */
    private function cloneValue($value)
    {
        if (is_array($value)) {
            $cloned = [];
            foreach ($value as $key => $arrValue) {
                $cloned[$key] = $this->cloneValue($arrValue);
            }
        } else if ($value instanceof Node) {
            $cloned = clone $value;
            foreach (get_object_vars($cloned) as $prop => $propValue) {
                $cloned->{$prop} = $this->cloneValue($propValue);
            }
        } else {
            $cloned = $value;
        }

        return $cloned;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $tmp = $this->toArray(true);
        return json_encode($tmp);
    }

    /**
     * @param bool $recursive
     * @return array
     */
    public function toArray($recursive = false)
    {
        $tmp = (array) $this;

        $tmp['loc'] = [
            'start' => $this->loc->start,
            'end' => $this->loc->end
        ];

        if ($recursive) {
            $this->recursiveToArray($tmp);
        }

        return $tmp;
    }

    /**
     * @param $object
     */
    public function recursiveToArray(&$object)
    {
        if ($object instanceof Node) {
            /** @var Node $object */
            $object = $object->toArray(true);
        } elseif (is_object($object)) {
            $object = (array) $object;
        } elseif (is_array($object)) {
            foreach ($object as &$o) {
                $this->recursiveToArray($o);
            }
        }
    }
}
