<?php
namespace GraphQL\Language\AST;

use GraphQL\Error\InvariantViolation;
use GraphQL\Utils\Utils;

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
        if (!empty($vars)) {
            Utils::assign($this, $vars);
        }
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
        if ($recursive) {
            return $this->recursiveToArray($this);
        } else {
            $tmp = (array) $this;

            $tmp['loc'] = [
                'start' => $this->loc->start,
                'end' => $this->loc->end
            ];

            return $tmp;
        }
    }

    /**
     * @param Node $node
     * @return array
     */
    private function recursiveToArray(Node $node)
    {
        $result = [
            'kind' => $node->kind,
            'loc' => [
                'start' => $node->loc->start,
                'end' => $node->loc->end
            ]
        ];

        foreach (get_object_vars($node) as $prop => $propValue) {
            if (isset($result[$prop]))
                continue;

            if (is_array($propValue) || $propValue instanceof NodeList) {
                $tmp = [];
                foreach ($propValue as $tmp1) {
                    $tmp[] = $tmp1 instanceof Node ? $this->recursiveToArray($tmp1) : (array) $tmp1;
                }
            } else if ($propValue instanceof Node) {
                $tmp = $this->recursiveToArray($propValue);
            } else if (is_scalar($propValue) || null === $propValue) {
                $tmp = $propValue;
            } else {
                $tmp = null;
            }

            $result[$prop] = $tmp;
        }
        return $result;
    }
}
