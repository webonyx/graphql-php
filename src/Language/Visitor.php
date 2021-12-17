<?php

declare(strict_types=1);

namespace GraphQL\Language;

use ArrayObject;
use Exception;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Utils\TypeInfo;
use SplFixedArray;
use stdClass;

use function array_pop;
use function count;
use function func_get_args;
use function is_array;
use function json_encode;

/**
 * Utility for efficient AST traversal and modification.
 *
 * `visit()` will walk through an AST using a depth first traversal, calling
 * the visitor's enter function at each node in the traversal, and calling the
 * leave function after visiting that node and all of its child nodes.
 *
 * By returning different values from the enter and leave functions, the
 * behavior of the visitor can be altered, including skipping over a sub-tree of
 * the AST (by returning false), editing the AST by returning a value or null
 * to remove the value, or to stop the whole traversal by returning BREAK.
 *
 * When using `visit()` to edit an AST, the original AST will not be modified, and
 * a new version of the AST with the changes applied will be returned from the
 * visit function.
 *
 *     $editedAST = Visitor::visit($ast, [
 *       'enter' => function ($node, $key, $parent, $path, $ancestors) {
 *         // return
 *         //   null: no action
 *         //   Visitor::skipNode(): skip visiting this node
 *         //   Visitor::stop(): stop visiting altogether
 *         //   Visitor::removeNode(): delete this node
 *         //   any value: replace this node with the returned value
 *       },
 *       'leave' => function ($node, $key, $parent, $path, $ancestors) {
 *         // return
 *         //   null: no action
 *         //   Visitor::stop(): stop visiting altogether
 *         //   Visitor::removeNode(): delete this node
 *         //   any value: replace this node with the returned value
 *       }
 *     ]);
 *
 * Alternatively to providing enter() and leave() functions, a visitor can
 * instead provide functions named the same as the [kinds of AST nodes](class-reference.md#graphqllanguageastnodekind),
 * or enter/leave visitors at a named key, leading to four permutations of
 * visitor API:
 *
 * 1. Named visitors triggered when entering a node a specific kind.
 *
 *     Visitor::visit($ast, [
 *       'Kind' => function ($node) {
 *         // enter the "Kind" node
 *       }
 *     ]);
 *
 * 2. Named visitors that trigger upon entering and leaving a node of
 *    a specific kind.
 *
 *     Visitor::visit($ast, [
 *       'Kind' => [
 *         'enter' => function ($node) {
 *           // enter the "Kind" node
 *         }
 *         'leave' => function ($node) {
 *           // leave the "Kind" node
 *         }
 *       ]
 *     ]);
 *
 * 3. Generic visitors that trigger upon entering and leaving any node.
 *
 *     Visitor::visit($ast, [
 *       'enter' => function ($node) {
 *         // enter any node
 *       },
 *       'leave' => function ($node) {
 *         // leave any node
 *       }
 *     ]);
 *
 * 4. Parallel visitors for entering and leaving nodes of a specific kind.
 *
 *     Visitor::visit($ast, [
 *       'enter' => [
 *         'Kind' => function($node) {
 *           // enter the "Kind" node
 *         }
 *       },
 *       'leave' => [
 *         'Kind' => function ($node) {
 *           // leave the "Kind" node
 *         }
 *       ]
 *     ]);
 *
 * @phpstan-type VisitorArray array<string, callable(Node): VisitorOperation|mixed|null>|array<string, array<string, callable(Node): VisitorOperation|mixed|null>>
 */
class Visitor
{
    public const VISITOR_KEYS = [
        NodeKind::NAME                 => [],
        NodeKind::DOCUMENT             => ['definitions'],
        NodeKind::OPERATION_DEFINITION => ['name', 'variableDefinitions', 'directives', 'selectionSet'],
        NodeKind::VARIABLE_DEFINITION  => ['variable', 'type', 'defaultValue', 'directives'],
        NodeKind::VARIABLE             => ['name'],
        NodeKind::SELECTION_SET        => ['selections'],
        NodeKind::FIELD                => ['alias', 'name', 'arguments', 'directives', 'selectionSet'],
        NodeKind::ARGUMENT             => ['name', 'value'],
        NodeKind::FRAGMENT_SPREAD      => ['name', 'directives'],
        NodeKind::INLINE_FRAGMENT      => ['typeCondition', 'directives', 'selectionSet'],
        NodeKind::FRAGMENT_DEFINITION  => [
            'name',
            // Note: fragment variable definitions are experimental and may be changed
            // or removed in the future.
            'variableDefinitions',
            'typeCondition',
            'directives',
            'selectionSet',
        ],

        NodeKind::INT           => [],
        NodeKind::FLOAT         => [],
        NodeKind::STRING        => [],
        NodeKind::BOOLEAN       => [],
        NodeKind::NULL          => [],
        NodeKind::ENUM          => [],
        NodeKind::LST           => ['values'],
        NodeKind::OBJECT        => ['fields'],
        NodeKind::OBJECT_FIELD  => ['name', 'value'],
        NodeKind::DIRECTIVE     => ['name', 'arguments'],
        NodeKind::NAMED_TYPE    => ['name'],
        NodeKind::LIST_TYPE     => ['type'],
        NodeKind::NON_NULL_TYPE => ['type'],

        NodeKind::SCHEMA_DEFINITION            => ['directives', 'operationTypes'],
        NodeKind::OPERATION_TYPE_DEFINITION    => ['type'],
        NodeKind::SCALAR_TYPE_DEFINITION       => ['description', 'name', 'directives'],
        NodeKind::OBJECT_TYPE_DEFINITION       => ['description', 'name', 'interfaces', 'directives', 'fields'],
        NodeKind::FIELD_DEFINITION             => ['description', 'name', 'arguments', 'type', 'directives'],
        NodeKind::INPUT_VALUE_DEFINITION       => ['description', 'name', 'type', 'defaultValue', 'directives'],
        NodeKind::INTERFACE_TYPE_DEFINITION    => ['description', 'name', 'interfaces', 'directives', 'fields'],
        NodeKind::UNION_TYPE_DEFINITION        => ['description', 'name', 'directives', 'types'],
        NodeKind::ENUM_TYPE_DEFINITION         => ['description', 'name', 'directives', 'values'],
        NodeKind::ENUM_VALUE_DEFINITION        => ['description', 'name', 'directives'],
        NodeKind::INPUT_OBJECT_TYPE_DEFINITION => ['description', 'name', 'directives', 'fields'],

        NodeKind::SCALAR_TYPE_EXTENSION       => ['name', 'directives'],
        NodeKind::OBJECT_TYPE_EXTENSION       => ['name', 'interfaces', 'directives', 'fields'],
        NodeKind::INTERFACE_TYPE_EXTENSION    => ['name', 'interfaces', 'directives', 'fields'],
        NodeKind::UNION_TYPE_EXTENSION        => ['name', 'directives', 'types'],
        NodeKind::ENUM_TYPE_EXTENSION         => ['name', 'directives', 'values'],
        NodeKind::INPUT_OBJECT_TYPE_EXTENSION => ['name', 'directives', 'fields'],

        NodeKind::DIRECTIVE_DEFINITION => ['description', 'name', 'arguments', 'locations'],

        NodeKind::SCHEMA_EXTENSION => ['directives', 'operationTypes'],
    ];

    /**
     * Visit the AST (see class description for details).
     *
     * @param NodeList|Node|ArrayObject|stdClass $root
     * @param VisitorArray                       $visitor
     * @param array<string, mixed>|null          $keyMap
     *
     * @return Node|mixed
     *
     * @throws Exception
     *
     * @api
     */
    public static function visit(object $root, array $visitor, ?array $keyMap = null)
    {
        $visitorKeys = $keyMap ?? self::VISITOR_KEYS;

        $stack     = null;
        $inArray   = $root instanceof NodeList;
        $keys      = [$root];
        $index     = -1;
        $edits     = [];
        $parent    = null;
        $path      = [];
        $ancestors = [];
        $newRoot   = $root;

        $UNDEFINED = null;

        do {
            $index++;
            $isLeaving = $index === count($keys);
            $key       = null;
            $node      = null;
            $isEdited  = $isLeaving && count($edits) > 0;

            if ($isLeaving) {
                $key    = $ancestors === []
                    ? $UNDEFINED
                    : $path[count($path) - 1];
                $node   = $parent;
                $parent = array_pop($ancestors);

                if ($isEdited) {
                    if ($inArray) {
                        // $node = $node; // arrays are value types in PHP
                        if ($node instanceof NodeList) {
                            $node = clone $node;
                        }
                    } else {
                        $node = clone $node;
                    }

                    $editOffset = 0;
                    for ($ii = 0; $ii < count($edits); $ii++) {
                        $editKey   = $edits[$ii][0];
                        $editValue = $edits[$ii][1];

                        if ($inArray) {
                            $editKey -= $editOffset;
                        }

                        if ($inArray && $editValue === null) {
                            $node->splice($editKey, 1);
                            $editOffset++;
                        } else {
                            if ($node instanceof NodeList || is_array($node)) {
                                $node[$editKey] = $editValue;
                            } else {
                                $node->{$editKey} = $editValue;
                            }
                        }
                    }
                }

                $index   = $stack['index'];
                $keys    = $stack['keys'];
                $edits   = $stack['edits'];
                $inArray = $stack['inArray'];
                $stack   = $stack['prev'];
            } else {
                $key  = $parent !== null
                    ? ($inArray
                        ? $index
                        : $keys[$index]
                    )
                    : $UNDEFINED;
                $node = $parent !== null
                    ? ($parent instanceof NodeList || is_array($parent)
                        ? $parent[$key]
                        : $parent->{$key}
                    )
                    : $newRoot;
                if ($node === null || $node === $UNDEFINED) {
                    continue;
                }

                if ($parent !== null) {
                    $path[] = $key;
                }
            }

            $result = null;
            if (! $node instanceof NodeList && ! is_array($node)) {
                if (! ($node instanceof Node)) {
                    throw new Exception('Invalid AST Node: ' . json_encode($node));
                }

                $visitFn = self::getVisitFn($visitor, $node->kind, $isLeaving);

                if ($visitFn !== null) {
                    $result    = $visitFn($node, $key, $parent, $path, $ancestors);
                    $editValue = null;

                    if ($result !== null) {
                        if ($result instanceof VisitorOperation) {
                            if ($result instanceof VisitorStop) {
                                break;
                            }

                            if (! $isLeaving && $result instanceof VisitorSkipNode) {
                                array_pop($path);
                                continue;
                            }

                            if ($result instanceof VisitorRemoveNode) {
                                $editValue = null;
                            }
                        } else {
                            $editValue = $result;
                        }

                        $edits[] = [$key, $editValue];
                        if (! $isLeaving) {
                            if (! ($editValue instanceof Node)) {
                                array_pop($path);
                                continue;
                            }

                            $node = $editValue;
                        }
                    }
                }
            }

            if ($result === null && $isEdited) {
                $edits[] = [$key, $node];
            }

            if ($isLeaving) {
                array_pop($path);
            } else {
                $stack   = [
                    'inArray' => $inArray,
                    'index'   => $index,
                    'keys'    => $keys,
                    'edits'   => $edits,
                    'prev'    => $stack,
                ];
                $inArray = $node instanceof NodeList || is_array($node);

                $keys  = ($inArray ? $node : $visitorKeys[$node->kind]) ?? [];
                $index = -1;
                $edits = [];
                if ($parent !== null) {
                    $ancestors[] = $parent;
                }

                $parent = $node;
            }
        } while ($stack);

        if (count($edits) > 0) {
            $newRoot = $edits[0][1];
        }

        return $newRoot;
    }

    /**
     * Returns marker for stopping.
     *
     * @api
     */
    public static function stop(): VisitorStop
    {
        static $stop;

        return $stop ??= new VisitorStop();
    }

    /**
     * Returns marker for skipping the current node.
     *
     * @api
     */
    public static function skipNode(): VisitorSkipNode
    {
        static $skipNode;

        return $skipNode ??= new VisitorSkipNode();
    }

    /**
     * Returns marker for removing the current node.
     *
     * @api
     */
    public static function removeNode(): VisitorRemoveNode
    {
        static $removeNode;

        return $removeNode ??= new VisitorRemoveNode();
    }

    /**
     * @phpstan-param array<int, VisitorArray> $visitors
     *
     * @return VisitorArray
     */
    public static function visitInParallel(array $visitors): array
    {
        $visitorsCount = count($visitors);
        $skipping      = new SplFixedArray($visitorsCount);

        return [
            'enter' => static function (Node $node) use ($visitors, $skipping, $visitorsCount) {
                for ($i = 0; $i < $visitorsCount; $i++) {
                    if ($skipping[$i] !== null) {
                        continue;
                    }

                    $fn = self::getVisitFn(
                        $visitors[$i],
                        $node->kind,
                        false
                    );

                    if ($fn === null) {
                        continue;
                    }

                    $result = $fn(...func_get_args());

                    if ($result instanceof VisitorSkipNode) {
                        $skipping[$i] = $node;
                    } elseif ($result instanceof VisitorStop) {
                        $skipping[$i] = $result;
                    } elseif ($result instanceof VisitorRemoveNode) {
                        return $result;
                    } elseif ($result !== null) {
                        return $result;
                    }
                }

                return null;
            },
            'leave' => static function (Node $node) use ($visitors, $skipping, $visitorsCount) {
                for ($i = 0; $i < $visitorsCount; $i++) {
                    if ($skipping[$i] === null) {
                        $fn = self::getVisitFn(
                            $visitors[$i],
                            $node->kind,
                            true
                        );

                        if ($fn !== null) {
                            $result = $fn(...func_get_args());

                            if ($result instanceof VisitorStop) {
                                $skipping[$i] = $result;
                            } elseif ($result instanceof VisitorRemoveNode) {
                                return $result;
                            } elseif ($result !== null) {
                                return $result;
                            }
                        }
                    } elseif ($skipping[$i] === $node) {
                        $skipping[$i] = null;
                    }
                }

                return null;
            },
        ];
    }

    /**
     * Creates a new visitor instance which maintains a provided TypeInfo instance
     * along with visiting visitor.
     *
     * @phpstan-param VisitorArray $visitor
     *
     * @phpstan-return VisitorArray
     */
    public static function visitWithTypeInfo(TypeInfo $typeInfo, array $visitor): array
    {
        return [
            'enter' => static function (Node $node) use ($typeInfo, $visitor) {
                $typeInfo->enter($node);
                $fn = self::getVisitFn($visitor, $node->kind, false);

                if ($fn === null) {
                    return null;
                }

                $result = $fn(...func_get_args());
                if ($result === null) {
                    return null;
                }

                $typeInfo->leave($node);
                if ($result instanceof Node) {
                    $typeInfo->enter($result);
                }

                return $result;
            },
            'leave' => static function (Node $node) use ($typeInfo, $visitor) {
                $fn     = self::getVisitFn($visitor, $node->kind, true);
                $result = $fn !== null
                    ? $fn(...func_get_args())
                    : null;

                $typeInfo->leave($node);

                return $result;
            },
        ];
    }

    /**
     * @return callable(Node $node, string $key, Node|NodeList $parent, array<int, int|string $path, array<int, Node|NodeList> $ancestors): VisitorOperation|Node|null
     */
    public static function getVisitFn(array $visitor, string $kind, bool $isLeaving): ?callable
    {
        $kindVisitor = $visitor[$kind] ?? null;

        if (is_array($kindVisitor)) {
            return $isLeaving
                ? $kindVisitor['leave'] ?? null
                : $kindVisitor['enter'] ?? null;
        }

        if ($kindVisitor !== null && ! $isLeaving) {
            return $kindVisitor;
        }

        $specificVisitor = $isLeaving
            ? $visitor['leave'] ?? null
            : $visitor['enter'] ?? null;

        if (is_array($specificVisitor)) {
            return $specificVisitor[$kind] ?? null;
        }

        return $specificVisitor;
    }
}
