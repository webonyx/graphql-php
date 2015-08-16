<?php
namespace GraphQL\Language;

use GraphQL\Language\AST\Node;

class Visitor
{
    const BREAK_VISIT = '@@BREAK@@';
    const CONTINUE_VISIT = '@@CONTINUE@@';

    /**
     * Break visitor
     *
     * @return VisitorOperation
     */
    public static function stop()
    {
        $r = new VisitorOperation();
        $r->doBreak = true;
        return $r;
    }

    /**
     * Skip current node
     */
    public static function skipNode()
    {
        $r = new VisitorOperation();
        $r->doContinue = true;
        return $r;
    }

    /**
     * Remove current node
     */
    public static function removeNode()
    {
        $r = new VisitorOperation();
        $r->removeNode = true;
        return $r;
    }

    public static $visitorKeys = array(
        Node::NAME => [],
        Node::DOCUMENT => ['definitions'],
        Node::OPERATION_DEFINITION => ['name', 'variableDefinitions', 'directives', 'selectionSet'],
        Node::VARIABLE_DEFINITION => ['variable', 'type', 'defaultValue'],
        Node::VARIABLE => ['name'],
        Node::SELECTION_SET => ['selections'],
        Node::FIELD => ['alias', 'name', 'arguments', 'directives', 'selectionSet'],
        Node::ARGUMENT => ['name', 'value'],
        Node::FRAGMENT_SPREAD => ['name', 'directives'],
        Node::INLINE_FRAGMENT => ['typeCondition', 'directives', 'selectionSet'],
        Node::FRAGMENT_DEFINITION => ['name', 'typeCondition', 'directives', 'selectionSet'],

        Node::INT => [],
        Node::FLOAT => [],
        Node::STRING => [],
        Node::BOOLEAN => [],
        Node::ENUM => [],
        Node::LST => ['values'],
        Node::OBJECT => ['fields'],
        Node::OBJECT_FIELD => ['name', 'value'],
        Node::DIRECTIVE => ['name', 'arguments'],
        Node::NAMED_TYPE => ['name'],
        Node::LIST_TYPE => ['type'],
        Node::NON_NULL_TYPE => ['type'],
    );

    /**
     * visit() will walk through an AST using a depth first traversal, calling
     * the visitor's enter function at each node in the traversal, and calling the
     * leave function after visiting that node and all of it's child nodes.
     *
     * By returning different values from the enter and leave functions, the
     * behavior of the visitor can be altered, including skipping over a sub-tree of
     * the AST (by returning false), editing the AST by returning a value or null
     * to remove the value, or to stop the whole traversal by returning BREAK.
     *
     * When using visit() to edit an AST, the original AST will not be modified, and
     * a new version of the AST with the changes applied will be returned from the
     * visit function.
     *
     *     var editedAST = visit(ast, {
     *       enter(node, key, parent, path, ancestors) {
     *         // @return
     *         //   undefined: no action
     *         //   false: skip visiting this node
     *         //   visitor.BREAK: stop visiting altogether
     *         //   null: delete this node
     *         //   any value: replace this node with the returned value
     *       },
     *       leave(node, key, parent, path, ancestors) {
     *         // @return
     *         //   undefined: no action
     *         //   visitor.BREAK: stop visiting altogether
     *         //   null: delete this node
     *         //   any value: replace this node with the returned value
     *       }
     *     });
     *
     * Alternatively to providing enter() and leave() functions, a visitor can
     * instead provide functions named the same as the kinds of AST nodes, or
     * enter/leave visitors at a named key, leading to four permutations of
     * visitor API:
     *
     * 1) Named visitors triggered when entering a node a specific kind.
     *
     *     visit(ast, {
     *       Kind(node) {
     *         // enter the "Kind" node
     *       }
     *     })
     *
     * 2) Named visitors that trigger upon entering and leaving a node of
     *    a specific kind.
     *
     *     visit(ast, {
     *       Kind: {
     *         enter(node) {
     *           // enter the "Kind" node
     *         }
     *         leave(node) {
     *           // leave the "Kind" node
     *         }
     *       }
     *     })
     *
     * 3) Generic visitors that trigger upon entering and leaving any node.
     *
     *     visit(ast, {
     *       enter(node) {
     *         // enter any node
     *       },
     *       leave(node) {
     *         // leave any node
     *       }
     *     })
     *
     * 4) Parallel visitors for entering and leaving nodes of a specific kind.
     *
     *     visit(ast, {
     *       enter: {
     *         Kind(node) {
     *           // enter the "Kind" node
     *         }
     *       },
     *       leave: {
     *         Kind(node) {
     *           // leave the "Kind" node
     *         }
     *       }
     *     })
     */
    public static function visit($root, $visitor, $keyMap = null)
    {
        $visitorKeys = $keyMap ?: self::$visitorKeys;

        $stack = null;
        $inArray = is_array($root);
        $keys = [$root];
        $index = -1;
        $edits = [];
        $parent = null;
        $path = [];
        $ancestors = [];
        $newRoot = $root;

        $UNDEFINED = null;

        do {
            $index++;
            $isLeaving = $index === count($keys);
            $key = null;
            $node = null;
            $isEdited = $isLeaving && count($edits) !== 0;

            if ($isLeaving) {
                $key = count($ancestors) === 0 ? $UNDEFINED : array_pop($path);
                $node = $parent;
                $parent = array_pop($ancestors);

                if ($isEdited) {
                    if ($inArray) {
                        // $node = $node; // arrays are value types in PHP
                    } else {
                        $node = clone $node;
                    }
                    $editOffset = 0;
                    for ($ii = 0; $ii < count($edits); $ii++) {
                        $editKey = $edits[$ii][0];
                        $editValue = $edits[$ii][1];

                        if ($inArray) {
                            $editKey -= $editOffset;
                        }
                        if ($inArray && $editValue === null) {
                            array_splice($node, $editKey, 1);
                            $editOffset++;
                        } else {
                            if (is_array($node)) {
                                $node[$editKey] = $editValue;
                            } else {
                                $node->{$editKey} = $editValue;
                            }
                        }
                    }
                }
                $index = $stack['index'];
                $keys = $stack['keys'];
                $edits = $stack['edits'];
                $inArray = $stack['inArray'];
                $stack = $stack['prev'];
            } else {
                $key = $parent ? ($inArray ? $index : $keys[$index]) : $UNDEFINED;
                $node = $parent ? (is_array($parent) ? $parent[$key] : $parent->{$key}) : $newRoot;
                if ($node === null || $node === $UNDEFINED) {
                    continue;
                }
                if ($parent) {
                    $path[] = $key;
                }
            }

            $result = null;
            if (!is_array($node)) {
                if (!($node instanceof Node)) {
                    throw new \Exception('Invalid AST Node: ' . json_encode($node));
                }

                $visitFn = self::getVisitFn($visitor, $isLeaving, $node->kind);

                if ($visitFn) {
                    $result = call_user_func($visitFn, $node, $key, $parent, $path, $ancestors);

                    if ($result !== null) {
                        if ($result instanceof VisitorOperation) {
                            if ($result->doBreak) {
                                break;
                            }
                            if (!$isLeaving && $result->doContinue) {
                                array_pop($path);
                                continue;
                            }
                            $editValue = null;
                        } else {
                            $editValue = $result;
                        }

                        $edits[] = [$key, $editValue];
                        if (!$isLeaving) {
                            if ($editValue instanceof Node) {
                                $node = $editValue;
                            } else {
                                array_pop($path);
                                continue;
                            }
                        }
                    }
                }
            }

            if ($result === null && $isEdited) {
                $edits[] = [$key, $node];
            }

            if (!$isLeaving) {
                $stack = array(
                    'inArray' => $inArray,
                    'index' => $index,
                    'keys' => $keys,
                    'edits' => $edits,
                    'prev' => $stack
                );
                $inArray = is_array($node);

                $keys = ($inArray ? $node : $visitorKeys[$node->kind]) ?: array();
                $index = -1;
                $edits = [];
                if ($parent) {
                    $ancestors[] = $parent;
                }
                $parent = $node;
            }

        } while ($stack);

        if (count($edits) !== 0) {
            $newRoot = $edits[0][1];
        }

        return $newRoot;
    }

    /**
     * @param $visitor
     * @param $isLeaving
     * @param $kind
     * @return null
     */
    public static function getVisitFn($visitor, $isLeaving, $kind)
    {
        if (!$visitor) {
            return null;
        }
        $kindVisitor = isset($visitor[$kind]) ? $visitor[$kind] : null;

        if (!$isLeaving && is_callable($kindVisitor)) {
            // { Kind() {} }
            return $kindVisitor;
        }

        if (is_array($kindVisitor)) {
            if ($isLeaving) {
                $kindSpecificVisitor = isset($kindVisitor['leave']) ? $kindVisitor['leave'] : null;
            } else {
                $kindSpecificVisitor = isset($kindVisitor['enter']) ? $kindVisitor['enter'] : null;
            }

            if ($kindSpecificVisitor && is_callable($kindSpecificVisitor)) {
                // { Kind: { enter() {}, leave() {} } }
                return $kindSpecificVisitor;
            }
            return null;
        }

        $visitor += ['leave' => null, 'enter' => null];
        $specificVisitor = $isLeaving ? $visitor['leave'] : $visitor['enter'];

        if ($specificVisitor) {
            if (is_callable($specificVisitor)) {
                // { enter() {}, leave() {} }
                return $specificVisitor;
            }
            $specificKindVisitor = isset($specificVisitor[$kind]) ? $specificVisitor[$kind] : null;

            if (is_callable($specificKindVisitor)) {
                // { enter: { Kind() {} }, leave: { Kind() {} } }
                return $specificKindVisitor;
            }
        }
        return null;
    }
}


class VisitorOperation
{
    public $doBreak;

    public $doContinue;

    public $removeNode;
}
