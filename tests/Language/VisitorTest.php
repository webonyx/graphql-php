<?php declare(strict_types=1);

namespace GraphQL\Tests\Language;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Tests\Validator\ValidatorTestCase;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\TypeInfo;

use function Safe\file_get_contents;

final class VisitorTest extends ValidatorTestCase
{
    /** @param array<int, mixed> $args */
    private function checkVisitorFnArgs(DocumentNode $ast, array $args, bool $isEdited = false): void
    {
        self::assertCount(5, $args);
        [$node, $key, $parent, $path, $ancestors] = $args;

        self::assertInstanceOf(Node::class, $node);
        self::assertContains($node->kind, array_keys(NodeKind::CLASS_MAP));

        $isRoot = $key === null;
        if ($isRoot) {
            if (! $isEdited) {
                self::assertEquals($ast, $node);
            }

            self::assertEquals(null, $parent);
            self::assertSame([], $path);
            self::assertSame([], $ancestors);

            return;
        }

        if ($parent instanceof NodeList) {
            self::assertIsInt($key);
            self::assertArrayHasKey($key, $parent);
        } else {
            self::assertIsString($key);
            self::assertTrue(property_exists($parent, $key));
        }

        self::assertIsArray($path);
        self::assertEquals($key, $path[count($path) - 1]);

        self::assertIsArray($ancestors);
        self::assertCount(count($path) - 1, $ancestors);

        if ($isEdited) {
            return;
        }

        if ($parent instanceof NodeList) {
            self::assertEquals($node, $parent[$key]);
        } else {
            // @phpstan-ignore-next-line dynamic property access
            self::assertEquals($node, $parent->{$key});
        }

        self::assertEquals($node, $this->getNodeByPath($ast, $path));
        $ancestorsLength = count($ancestors);
        for ($i = 0; $i < $ancestorsLength; ++$i) {
            $ancestorPath = array_slice($path, 0, $i);
            self::assertEquals($ancestors[$i], $this->getNodeByPath($ast, $ancestorPath));
        }
    }

    /**
     * @param array<string|int> $path
     *
     * @return Node|NodeList<SelectionNode&Node>
     */
    private function getNodeByPath(DocumentNode $ast, array $path): object
    {
        $result = $ast;

        foreach ($path as $key) {
            $result = $result instanceof NodeList
                ? $result[$key]
                // @phpstan-ignore-next-line variable property access on mixed
                : $result->{$key};
        }

        return $result;
    }

    /** @see it('handles empty visitor', () => { */
    public function testHandlesEmptyVisitor(): void
    {
        $ast = Parser::parse('{ a }', ['noLocation' => true]);
        Visitor::visit($ast, []);
        $this->expectNotToPerformAssertions();
    }

    /** @see it('validates path argument') */
    public function testValidatesPathArgument(): void
    {
        $visited = [];

        $ast = Parser::parse('{ a }', ['noLocation' => true]);

        Visitor::visit(
            $ast,
            [
                'enter' => function ($node, $key, $parent, $path) use ($ast, &$visited): void {
                    $this->checkVisitorFnArgs($ast, func_get_args());
                    $visited[] = ['enter', $path];
                },
                'leave' => function ($node, $key, $parent, $path) use ($ast, &$visited): void {
                    $this->checkVisitorFnArgs($ast, func_get_args());
                    $visited[] = ['leave', $path];
                },
            ]
        );

        $expected = [
            ['enter', []],
            ['enter', ['definitions', 0]],
            ['enter', ['definitions', 0, 'selectionSet']],
            ['enter', ['definitions', 0, 'selectionSet', 'selections', 0]],
            ['enter', ['definitions', 0, 'selectionSet', 'selections', 0, 'name']],
            ['leave', ['definitions', 0, 'selectionSet', 'selections', 0, 'name']],
            ['leave', ['definitions', 0, 'selectionSet', 'selections', 0]],
            ['leave', ['definitions', 0, 'selectionSet']],
            ['leave', ['definitions', 0]],
            ['leave', []],
        ];

        self::assertSame($expected, $visited);
    }

    /** @see it('validates ancestors argument') */
    public function testValidatesAncestorsArgument(): void
    {
        $ast = Parser::parse('{ a }', ['noLocation' => true]);
        $visitedNodes = [];

        Visitor::visit($ast, [
            'enter' => static function ($node, $key, $parent, $path, $ancestors) use (&$visitedNodes): void {
                $inArray = is_numeric($key);
                if ($inArray) {
                    $visitedNodes[] = $parent;
                }

                $visitedNodes[] = $node;

                $expectedAncestors = array_slice($visitedNodes, 0, -2);
                self::assertEquals($expectedAncestors, $ancestors);
            },
            'leave' => static function ($node, $key, $parent, $path, $ancestors) use (&$visitedNodes): void {
                $expectedAncestors = array_slice($visitedNodes, 0, -2);
                self::assertEquals($expectedAncestors, $ancestors);

                $inArray = is_numeric($key);
                if ($inArray) {
                    array_pop($visitedNodes);
                }

                array_pop($visitedNodes);
            },
        ]);
    }

    /** @see it('allows editing a node both on enter and on leave', () => { */
    public function testAllowsEditingANodeBothOnEnterAndOnLeave(): void
    {
        $ast = Parser::parse('{ a, b, c { a, b, c } }', ['noLocation' => true]);

        $directive1 = Parser::directive('@x');
        $directive2 = Parser::directive('@y');

        $editedAst = Visitor::visit(
            $ast,
            [
                NodeKind::OPERATION_DEFINITION => [
                    'enter' => function (OperationDefinitionNode $node) use ($ast, $directive1): OperationDefinitionNode {
                        $this->checkVisitorFnArgs($ast, func_get_args());

                        $newNode = clone $node;
                        $newNode->directives = new NodeList([$directive1]);

                        return $newNode;
                    },
                    'leave' => function (OperationDefinitionNode $node) use ($ast, $directive2): OperationDefinitionNode {
                        $this->checkVisitorFnArgs($ast, func_get_args(), true);

                        $newNode = clone $node;
                        $newNode->directives = $node->directives->merge([$directive2]);

                        return $newNode;
                    },
                ],
            ]
        );

        self::assertNotEquals($ast, $editedAst);

        $expected = $ast->cloneDeep();
        $operationNode = $expected->definitions[0];
        assert($operationNode instanceof OperationDefinitionNode);
        $operationNode->directives = new NodeList([$directive1, $directive2]);

        self::assertEquals($expected, $editedAst);
    }

    public function testAllowsEditingRootNodeOnEnterAndLeave(): void
    {
        $ast = Parser::parse('{ a, b, c { a, b, c } }', ['noLocation' => true]);

        $definition1 = Parser::operationDefinition('{ x }');
        $definition2 = Parser::operationDefinition('{ x }');

        $editedAst = Visitor::visit(
            $ast,
            [
                NodeKind::DOCUMENT => [
                    'enter' => function (DocumentNode $node) use ($ast, $definition1): DocumentNode {
                        $this->checkVisitorFnArgs($ast, func_get_args());

                        $newNode = clone $node;
                        $newNode->definitions = $node->definitions->merge([$definition1]);

                        return $newNode;
                    },
                    'leave' => function (DocumentNode $node) use ($ast, $definition2): void {
                        $this->checkVisitorFnArgs($ast, func_get_args(), true);

                        $node->definitions = $node->definitions->merge([$definition2]);
                    },
                ],
            ]
        );

        self::assertNotEquals($ast, $editedAst);

        $expected = $ast->cloneDeep();
        $expected->definitions = $ast->definitions->merge([$definition1, $definition2]);

        self::assertEquals($expected, $editedAst);
    }

    public function testAllowsForEditingOnEnter(): void
    {
        $ast = Parser::parse('{ a, b, c { a, b, c } }', ['noLocation' => true]);
        $editedAst = Visitor::visit(
            $ast,
            [
                'enter' => function ($node) use ($ast): ?VisitorOperation {
                    $this->checkVisitorFnArgs($ast, func_get_args());
                    if ($node instanceof FieldNode && $node->name->value === 'b') {
                        return Visitor::removeNode();
                    }

                    return null;
                },
            ]
        );

        self::assertEquals(
            Parser::parse('{ a, b, c { a, b, c } }', ['noLocation' => true]),
            $ast
        );
        self::assertEquals(
            Parser::parse('{ a,    c { a,    c } }', ['noLocation' => true]),
            $editedAst
        );
    }

    public function testAllowsForEditingOnLeave(): void
    {
        $ast = Parser::parse('{ a, b, c { a, b, c } }', ['noLocation' => true]);
        $editedAst = Visitor::visit(
            $ast,
            [
                'leave' => function ($node) use ($ast): ?VisitorOperation {
                    $this->checkVisitorFnArgs($ast, func_get_args(), true);
                    if ($node instanceof FieldNode && $node->name->value === 'b') {
                        return Visitor::removeNode();
                    }

                    return null;
                },
            ]
        );

        self::assertEquals(
            Parser::parse('{ a, b, c { a, b, c } }', ['noLocation' => true]),
            $ast
        );

        self::assertEquals(
            Parser::parse('{ a,    c { a,    c } }', ['noLocation' => true]),
            $editedAst
        );
    }

    public function testVisitsEditedNode(): void
    {
        $addedField = new FieldNode([
            'name' => new NameNode(['value' => '__typename']),
            'arguments' => new NodeList([]),
            'directives' => new NodeList([]),
        ]);

        $didVisitAddedField = false;

        $ast = Parser::parse('{ a { x } }', ['noLocation' => true]);

        Visitor::visit(
            $ast,
            [
                'enter' => function ($node) use ($addedField, &$didVisitAddedField, $ast): ?FieldNode {
                    $this->checkVisitorFnArgs($ast, func_get_args(), true);
                    if ($node instanceof FieldNode && $node->name->value === 'a') {
                        /** @var NodeList<SelectionNode&Node> $newSelection */
                        $newSelection = new NodeList([$addedField]);

                        $selectionSet = $node->selectionSet;
                        self::assertInstanceOf(SelectionSetNode::class, $selectionSet);

                        return new FieldNode([
                            'name' => $node->name,
                            'arguments' => new NodeList([]),
                            'directives' => new NodeList([]),
                            'selectionSet' => new SelectionSetNode([
                                'selections' => $newSelection->merge($selectionSet->selections),
                            ]),
                        ]);
                    }

                    if ($node !== $addedField) {
                        return null;
                    }

                    $didVisitAddedField = true;

                    return null;
                },
            ]
        );

        self::assertTrue($didVisitAddedField);
    }

    public function testAllowsSkippingASubTree(): void
    {
        $visited = [];
        $ast = Parser::parse('{ a, b { x }, c }', ['noLocation' => true]);

        Visitor::visit(
            $ast,
            [
                'enter' => function (Node $node) use (&$visited, $ast): ?VisitorOperation {
                    $this->checkVisitorFnArgs($ast, func_get_args());
                    $visited[] = ['enter', $node->kind, property_exists($node, 'value') ? $node->value : null];
                    if ($node instanceof FieldNode && $node->name->value === 'b') {
                        return Visitor::skipNode();
                    }

                    return null;
                },
                'leave' => function (Node $node) use (&$visited, $ast): void {
                    $this->checkVisitorFnArgs($ast, func_get_args());
                    $visited[] = ['leave', $node->kind, property_exists($node, 'value') ? $node->value : null];
                },
            ]
        );

        $expected = [
            ['enter', 'Document', null],
            ['enter', 'OperationDefinition', null],
            ['enter', 'SelectionSet', null],
            ['enter', 'Field', null],
            ['enter', 'Name', 'a'],
            ['leave', 'Name', 'a'],
            ['leave', 'Field', null],
            ['enter', 'Field', null],
            ['enter', 'Field', null],
            ['enter', 'Name', 'c'],
            ['leave', 'Name', 'c'],
            ['leave', 'Field', null],
            ['leave', 'SelectionSet', null],
            ['leave', 'OperationDefinition', null],
            ['leave', 'Document', null],
        ];

        self::assertEquals($expected, $visited);
    }

    public function testAllowsEarlyExitWhileVisiting(): void
    {
        $visited = [];
        $ast = Parser::parse('{ a, b { x }, c }', ['noLocation' => true]);

        Visitor::visit(
            $ast,
            [
                'enter' => function (Node $node) use (&$visited, $ast): ?VisitorOperation {
                    $this->checkVisitorFnArgs($ast, func_get_args());
                    $visited[] = ['enter', $node->kind, property_exists($node, 'value') ? $node->value : null];
                    if ($node instanceof NameNode && $node->value === 'x') {
                        return Visitor::stop();
                    }

                    return null;
                },
                'leave' => function (Node $node) use (&$visited, $ast): void {
                    $this->checkVisitorFnArgs($ast, func_get_args());
                    $visited[] = ['leave', $node->kind, property_exists($node, 'value') ? $node->value : null];
                },
            ]
        );

        $expected = [
            ['enter', 'Document', null],
            ['enter', 'OperationDefinition', null],
            ['enter', 'SelectionSet', null],
            ['enter', 'Field', null],
            ['enter', 'Name', 'a'],
            ['leave', 'Name', 'a'],
            ['leave', 'Field', null],
            ['enter', 'Field', null],
            ['enter', 'Name', 'b'],
            ['leave', 'Name', 'b'],
            ['enter', 'SelectionSet', null],
            ['enter', 'Field', null],
            ['enter', 'Name', 'x'],
        ];

        self::assertEquals($expected, $visited);
    }

    public function testAllowsEarlyExitWhileLeaving(): void
    {
        $visited = [];

        $ast = Parser::parse('{ a, b { x }, c }', ['noLocation' => true]);
        Visitor::visit(
            $ast,
            [
                'enter' => function ($node) use (&$visited, $ast): void {
                    $this->checkVisitorFnArgs($ast, func_get_args());
                    $visited[] = ['enter', $node->kind, $node->value ?? null];
                },
                'leave' => function ($node) use (&$visited, $ast): ?VisitorOperation {
                    $this->checkVisitorFnArgs($ast, func_get_args());
                    $visited[] = ['leave', $node->kind, $node->value ?? null];

                    if ($node instanceof NameNode && $node->value === 'x') {
                        return Visitor::stop();
                    }

                    return null;
                },
            ]
        );

        self::assertEquals(
            $visited,
            [
                ['enter', 'Document', null],
                ['enter', 'OperationDefinition', null],
                ['enter', 'SelectionSet', null],
                ['enter', 'Field', null],
                ['enter', 'Name', 'a'],
                ['leave', 'Name', 'a'],
                ['leave', 'Field', null],
                ['enter', 'Field', null],
                ['enter', 'Name', 'b'],
                ['leave', 'Name', 'b'],
                ['enter', 'SelectionSet', null],
                ['enter', 'Field', null],
                ['enter', 'Name', 'x'],
                ['leave', 'Name', 'x'],
            ]
        );
    }

    public function testAllowsANamedFunctionsVisitorAPI(): void
    {
        $visited = [];
        $ast = Parser::parse('{ a, b { x }, c }', ['noLocation' => true]);

        Visitor::visit(
            $ast,
            [
                NodeKind::NAME => function (NameNode $node) use (&$visited, $ast): void {
                    $this->checkVisitorFnArgs($ast, func_get_args());
                    $visited[] = ['enter', $node->kind, $node->value];
                },
                NodeKind::SELECTION_SET => [
                    'enter' => function (SelectionSetNode $node) use (&$visited, $ast): void {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $visited[] = ['enter', $node->kind, null];
                    },
                    'leave' => function (SelectionSetNode $node) use (&$visited, $ast): void {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $visited[] = ['leave', $node->kind, null];
                    },
                ],
            ]
        );

        $expected = [
            ['enter', 'SelectionSet', null],
            ['enter', 'Name', 'a'],
            ['enter', 'Name', 'b'],
            ['enter', 'SelectionSet', null],
            ['enter', 'Name', 'x'],
            ['leave', 'SelectionSet', null],
            ['enter', 'Name', 'c'],
            ['leave', 'SelectionSet', null],
        ];

        self::assertEquals($expected, $visited);
    }

    public function testExperimentalVisitsVariablesDefinedInFragments(): void
    {
        $ast = Parser::parse(
            'fragment a($v: Boolean = false) on t { f }',
            [
                'noLocation' => true,
                'experimentalFragmentVariables' => true,
            ]
        );
        $visited = [];

        Visitor::visit(
            $ast,
            [
                'enter' => function ($node) use (&$visited, $ast): void {
                    $this->checkVisitorFnArgs($ast, func_get_args());
                    $visited[] = ['enter', $node->kind, $node->value ?? null];
                },
                'leave' => function ($node) use (&$visited, $ast): void {
                    $this->checkVisitorFnArgs($ast, func_get_args());
                    $visited[] = ['leave', $node->kind, $node->value ?? null];
                },
            ]
        );

        $expected = [
            ['enter', 'Document', null],
            ['enter', 'FragmentDefinition', null],
            ['enter', 'Name', 'a'],
            ['leave', 'Name', 'a'],
            ['enter', 'VariableDefinition', null],
            ['enter', 'Variable', null],
            ['enter', 'Name', 'v'],
            ['leave', 'Name', 'v'],
            ['leave', 'Variable', null],
            ['enter', 'NamedType', null],
            ['enter', 'Name', 'Boolean'],
            ['leave', 'Name', 'Boolean'],
            ['leave', 'NamedType', null],
            ['enter', 'BooleanValue', false],
            ['leave', 'BooleanValue', false],
            ['leave', 'VariableDefinition', null],
            ['enter', 'NamedType', null],
            ['enter', 'Name', 't'],
            ['leave', 'Name', 't'],
            ['leave', 'NamedType', null],
            ['enter', 'SelectionSet', null],
            ['enter', 'Field', null],
            ['enter', 'Name', 'f'],
            ['leave', 'Name', 'f'],
            ['leave', 'Field', null],
            ['leave', 'SelectionSet', null],
            ['leave', 'FragmentDefinition', null],
            ['leave', 'Document', null],
        ];

        self::assertEquals($expected, $visited);
    }

    public function testVisitsKitchenSink(): void
    {
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $ast = Parser::parse($kitchenSink);

        $visited = [];
        Visitor::visit(
            $ast,
            [
                'enter' => function (Node $node, $key, $parent) use (&$visited, $ast): void {
                    $this->checkVisitorFnArgs($ast, func_get_args());
                    $r = ['enter', $node->kind, $key, $parent instanceof Node ? $parent->kind : null];
                    $visited[] = $r;
                },
                'leave' => function (Node $node, $key, $parent) use (&$visited, $ast): void {
                    $this->checkVisitorFnArgs($ast, func_get_args());
                    $r = ['leave', $node->kind, $key, $parent instanceof Node ? $parent->kind : null];
                    $visited[] = $r;
                },
            ]
        );

        $expected = [
            ['enter', 'Document', null, null],
            ['enter', 'OperationDefinition', 0, null],
            ['enter', 'Name', 'name', 'OperationDefinition'],
            ['leave', 'Name', 'name', 'OperationDefinition'],
            ['enter', 'VariableDefinition', 0, null],
            ['enter', 'Variable', 'variable', 'VariableDefinition'],
            ['enter', 'Name', 'name', 'Variable'],
            ['leave', 'Name', 'name', 'Variable'],
            ['leave', 'Variable', 'variable', 'VariableDefinition'],
            ['enter', 'NamedType', 'type', 'VariableDefinition'],
            ['enter', 'Name', 'name', 'NamedType'],
            ['leave', 'Name', 'name', 'NamedType'],
            ['leave', 'NamedType', 'type', 'VariableDefinition'],
            ['leave', 'VariableDefinition', 0, null],
            ['enter', 'VariableDefinition', 1, null],
            ['enter', 'Variable', 'variable', 'VariableDefinition'],
            ['enter', 'Name', 'name', 'Variable'],
            ['leave', 'Name', 'name', 'Variable'],
            ['leave', 'Variable', 'variable', 'VariableDefinition'],
            ['enter', 'NamedType', 'type', 'VariableDefinition'],
            ['enter', 'Name', 'name', 'NamedType'],
            ['leave', 'Name', 'name', 'NamedType'],
            ['leave', 'NamedType', 'type', 'VariableDefinition'],
            ['enter', 'EnumValue', 'defaultValue', 'VariableDefinition'],
            ['leave', 'EnumValue', 'defaultValue', 'VariableDefinition'],
            ['leave', 'VariableDefinition', 1, null],
            ['enter', 'SelectionSet', 'selectionSet', 'OperationDefinition'],
            ['enter', 'Field', 0, null],
            ['enter', 'Name', 'alias', 'Field'],
            ['leave', 'Name', 'alias', 'Field'],
            ['enter', 'Name', 'name', 'Field'],
            ['leave', 'Name', 'name', 'Field'],
            ['enter', 'Argument', 0, null],
            ['enter', 'Name', 'name', 'Argument'],
            ['leave', 'Name', 'name', 'Argument'],
            ['enter', 'ListValue', 'value', 'Argument'],
            ['enter', 'IntValue', 0, null],
            ['leave', 'IntValue', 0, null],
            ['enter', 'IntValue', 1, null],
            ['leave', 'IntValue', 1, null],
            ['leave', 'ListValue', 'value', 'Argument'],
            ['leave', 'Argument', 0, null],
            ['enter', 'SelectionSet', 'selectionSet', 'Field'],
            ['enter', 'Field', 0, null],
            ['enter', 'Name', 'name', 'Field'],
            ['leave', 'Name', 'name', 'Field'],
            ['leave', 'Field', 0, null],
            ['enter', 'InlineFragment', 1, null],
            ['enter', 'NamedType', 'typeCondition', 'InlineFragment'],
            ['enter', 'Name', 'name', 'NamedType'],
            ['leave', 'Name', 'name', 'NamedType'],
            ['leave', 'NamedType', 'typeCondition', 'InlineFragment'],
            ['enter', 'Directive', 0, null],
            ['enter', 'Name', 'name', 'Directive'],
            ['leave', 'Name', 'name', 'Directive'],
            ['leave', 'Directive', 0, null],
            ['enter', 'SelectionSet', 'selectionSet', 'InlineFragment'],
            ['enter', 'Field', 0, null],
            ['enter', 'Name', 'name', 'Field'],
            ['leave', 'Name', 'name', 'Field'],
            ['enter', 'SelectionSet', 'selectionSet', 'Field'],
            ['enter', 'Field', 0, null],
            ['enter', 'Name', 'name', 'Field'],
            ['leave', 'Name', 'name', 'Field'],
            ['leave', 'Field', 0, null],
            ['enter', 'Field', 1, null],
            ['enter', 'Name', 'alias', 'Field'],
            ['leave', 'Name', 'alias', 'Field'],
            ['enter', 'Name', 'name', 'Field'],
            ['leave', 'Name', 'name', 'Field'],
            ['enter', 'Argument', 0, null],
            ['enter', 'Name', 'name', 'Argument'],
            ['leave', 'Name', 'name', 'Argument'],
            ['enter', 'IntValue', 'value', 'Argument'],
            ['leave', 'IntValue', 'value', 'Argument'],
            ['leave', 'Argument', 0, null],
            ['enter', 'Argument', 1, null],
            ['enter', 'Name', 'name', 'Argument'],
            ['leave', 'Name', 'name', 'Argument'],
            ['enter', 'Variable', 'value', 'Argument'],
            ['enter', 'Name', 'name', 'Variable'],
            ['leave', 'Name', 'name', 'Variable'],
            ['leave', 'Variable', 'value', 'Argument'],
            ['leave', 'Argument', 1, null],
            ['enter', 'Directive', 0, null],
            ['enter', 'Name', 'name', 'Directive'],
            ['leave', 'Name', 'name', 'Directive'],
            ['enter', 'Argument', 0, null],
            ['enter', 'Name', 'name', 'Argument'],
            ['leave', 'Name', 'name', 'Argument'],
            ['enter', 'Variable', 'value', 'Argument'],
            ['enter', 'Name', 'name', 'Variable'],
            ['leave', 'Name', 'name', 'Variable'],
            ['leave', 'Variable', 'value', 'Argument'],
            ['leave', 'Argument', 0, null],
            ['leave', 'Directive', 0, null],
            ['enter', 'SelectionSet', 'selectionSet', 'Field'],
            ['enter', 'Field', 0, null],
            ['enter', 'Name', 'name', 'Field'],
            ['leave', 'Name', 'name', 'Field'],
            ['leave', 'Field', 0, null],
            ['enter', 'FragmentSpread', 1, null],
            ['enter', 'Name', 'name', 'FragmentSpread'],
            ['leave', 'Name', 'name', 'FragmentSpread'],
            ['leave', 'FragmentSpread', 1, null],
            ['leave', 'SelectionSet', 'selectionSet', 'Field'],
            ['leave', 'Field', 1, null],
            ['leave', 'SelectionSet', 'selectionSet', 'Field'],
            ['leave', 'Field', 0, null],
            ['leave', 'SelectionSet', 'selectionSet', 'InlineFragment'],
            ['leave', 'InlineFragment', 1, null],
            ['enter', 'InlineFragment', 2, null],
            ['enter', 'Directive', 0, null],
            ['enter', 'Name', 'name', 'Directive'],
            ['leave', 'Name', 'name', 'Directive'],
            ['enter', 'Argument', 0, null],
            ['enter', 'Name', 'name', 'Argument'],
            ['leave', 'Name', 'name', 'Argument'],
            ['enter', 'Variable', 'value', 'Argument'],
            ['enter', 'Name', 'name', 'Variable'],
            ['leave', 'Name', 'name', 'Variable'],
            ['leave', 'Variable', 'value', 'Argument'],
            ['leave', 'Argument', 0, null],
            ['leave', 'Directive', 0, null],
            ['enter', 'SelectionSet', 'selectionSet', 'InlineFragment'],
            ['enter', 'Field', 0, null],
            ['enter', 'Name', 'name', 'Field'],
            ['leave', 'Name', 'name', 'Field'],
            ['leave', 'Field', 0, null],
            ['leave', 'SelectionSet', 'selectionSet', 'InlineFragment'],
            ['leave', 'InlineFragment', 2, null],
            ['enter', 'InlineFragment', 3, null],
            ['enter', 'SelectionSet', 'selectionSet', 'InlineFragment'],
            ['enter', 'Field', 0, null],
            ['enter', 'Name', 'name', 'Field'],
            ['leave', 'Name', 'name', 'Field'],
            ['leave', 'Field', 0, null],
            ['leave', 'SelectionSet', 'selectionSet', 'InlineFragment'],
            ['leave', 'InlineFragment', 3, null],
            ['leave', 'SelectionSet', 'selectionSet', 'Field'],
            ['leave', 'Field', 0, null],
            ['leave', 'SelectionSet', 'selectionSet', 'OperationDefinition'],
            ['leave', 'OperationDefinition', 0, null],
            ['enter', 'OperationDefinition', 1, null],
            ['enter', 'Name', 'name', 'OperationDefinition'],
            ['leave', 'Name', 'name', 'OperationDefinition'],
            ['enter', 'SelectionSet', 'selectionSet', 'OperationDefinition'],
            ['enter', 'Field', 0, null],
            ['enter', 'Name', 'name', 'Field'],
            ['leave', 'Name', 'name', 'Field'],
            ['enter', 'Argument', 0, null],
            ['enter', 'Name', 'name', 'Argument'],
            ['leave', 'Name', 'name', 'Argument'],
            ['enter', 'IntValue', 'value', 'Argument'],
            ['leave', 'IntValue', 'value', 'Argument'],
            ['leave', 'Argument', 0, null],
            ['enter', 'Directive', 0, null],
            ['enter', 'Name', 'name', 'Directive'],
            ['leave', 'Name', 'name', 'Directive'],
            ['leave', 'Directive', 0, null],
            ['enter', 'SelectionSet', 'selectionSet', 'Field'],
            ['enter', 'Field', 0, null],
            ['enter', 'Name', 'name', 'Field'],
            ['leave', 'Name', 'name', 'Field'],
            ['enter', 'SelectionSet', 'selectionSet', 'Field'],
            ['enter', 'Field', 0, null],
            ['enter', 'Name', 'name', 'Field'],
            ['leave', 'Name', 'name', 'Field'],
            ['leave', 'Field', 0, null],
            ['leave', 'SelectionSet', 'selectionSet', 'Field'],
            ['leave', 'Field', 0, null],
            ['leave', 'SelectionSet', 'selectionSet', 'Field'],
            ['leave', 'Field', 0, null],
            ['leave', 'SelectionSet', 'selectionSet', 'OperationDefinition'],
            ['leave', 'OperationDefinition', 1, null],
            ['enter', 'OperationDefinition', 2, null],
            ['enter', 'Name', 'name', 'OperationDefinition'],
            ['leave', 'Name', 'name', 'OperationDefinition'],
            ['enter', 'VariableDefinition', 0, null],
            ['enter', 'Variable', 'variable', 'VariableDefinition'],
            ['enter', 'Name', 'name', 'Variable'],
            ['leave', 'Name', 'name', 'Variable'],
            ['leave', 'Variable', 'variable', 'VariableDefinition'],
            ['enter', 'NamedType', 'type', 'VariableDefinition'],
            ['enter', 'Name', 'name', 'NamedType'],
            ['leave', 'Name', 'name', 'NamedType'],
            ['leave', 'NamedType', 'type', 'VariableDefinition'],
            ['leave', 'VariableDefinition', 0, null],
            ['enter', 'SelectionSet', 'selectionSet', 'OperationDefinition'],
            ['enter', 'Field', 0, null],
            ['enter', 'Name', 'name', 'Field'],
            ['leave', 'Name', 'name', 'Field'],
            ['enter', 'Argument', 0, null],
            ['enter', 'Name', 'name', 'Argument'],
            ['leave', 'Name', 'name', 'Argument'],
            ['enter', 'Variable', 'value', 'Argument'],
            ['enter', 'Name', 'name', 'Variable'],
            ['leave', 'Name', 'name', 'Variable'],
            ['leave', 'Variable', 'value', 'Argument'],
            ['leave', 'Argument', 0, null],
            ['enter', 'SelectionSet', 'selectionSet', 'Field'],
            ['enter', 'Field', 0, null],
            ['enter', 'Name', 'name', 'Field'],
            ['leave', 'Name', 'name', 'Field'],
            ['enter', 'SelectionSet', 'selectionSet', 'Field'],
            ['enter', 'Field', 0, null],
            ['enter', 'Name', 'name', 'Field'],
            ['leave', 'Name', 'name', 'Field'],
            ['enter', 'SelectionSet', 'selectionSet', 'Field'],
            ['enter', 'Field', 0, null],
            ['enter', 'Name', 'name', 'Field'],
            ['leave', 'Name', 'name', 'Field'],
            ['leave', 'Field', 0, null],
            ['leave', 'SelectionSet', 'selectionSet', 'Field'],
            ['leave', 'Field', 0, null],
            ['enter', 'Field', 1, null],
            ['enter', 'Name', 'name', 'Field'],
            ['leave', 'Name', 'name', 'Field'],
            ['enter', 'SelectionSet', 'selectionSet', 'Field'],
            ['enter', 'Field', 0, null],
            ['enter', 'Name', 'name', 'Field'],
            ['leave', 'Name', 'name', 'Field'],
            ['leave', 'Field', 0, null],
            ['leave', 'SelectionSet', 'selectionSet', 'Field'],
            ['leave', 'Field', 1, null],
            ['leave', 'SelectionSet', 'selectionSet', 'Field'],
            ['leave', 'Field', 0, null],
            ['leave', 'SelectionSet', 'selectionSet', 'Field'],
            ['leave', 'Field', 0, null],
            ['leave', 'SelectionSet', 'selectionSet', 'OperationDefinition'],
            ['leave', 'OperationDefinition', 2, null],
            ['enter', 'FragmentDefinition', 3, null],
            ['enter', 'Name', 'name', 'FragmentDefinition'],
            ['leave', 'Name', 'name', 'FragmentDefinition'],
            ['enter', 'NamedType', 'typeCondition', 'FragmentDefinition'],
            ['enter', 'Name', 'name', 'NamedType'],
            ['leave', 'Name', 'name', 'NamedType'],
            ['leave', 'NamedType', 'typeCondition', 'FragmentDefinition'],
            ['enter', 'SelectionSet', 'selectionSet', 'FragmentDefinition'],
            ['enter', 'Field', 0, null],
            ['enter', 'Name', 'name', 'Field'],
            ['leave', 'Name', 'name', 'Field'],
            ['enter', 'Argument', 0, null],
            ['enter', 'Name', 'name', 'Argument'],
            ['leave', 'Name', 'name', 'Argument'],
            ['enter', 'Variable', 'value', 'Argument'],
            ['enter', 'Name', 'name', 'Variable'],
            ['leave', 'Name', 'name', 'Variable'],
            ['leave', 'Variable', 'value', 'Argument'],
            ['leave', 'Argument', 0, null],
            ['enter', 'Argument', 1, null],
            ['enter', 'Name', 'name', 'Argument'],
            ['leave', 'Name', 'name', 'Argument'],
            ['enter', 'Variable', 'value', 'Argument'],
            ['enter', 'Name', 'name', 'Variable'],
            ['leave', 'Name', 'name', 'Variable'],
            ['leave', 'Variable', 'value', 'Argument'],
            ['leave', 'Argument', 1, null],
            ['enter', 'Argument', 2, null],
            ['enter', 'Name', 'name', 'Argument'],
            ['leave', 'Name', 'name', 'Argument'],
            ['enter', 'ObjectValue', 'value', 'Argument'],
            ['enter', 'ObjectField', 0, null],
            ['enter', 'Name', 'name', 'ObjectField'],
            ['leave', 'Name', 'name', 'ObjectField'],
            ['enter', 'StringValue', 'value', 'ObjectField'],
            ['leave', 'StringValue', 'value', 'ObjectField'],
            ['leave', 'ObjectField', 0, null],
            ['enter', 'ObjectField', 1, null],
            ['enter', 'Name', 'name', 'ObjectField'],
            ['leave', 'Name', 'name', 'ObjectField'],
            ['enter', 'StringValue', 'value', 'ObjectField'],
            ['leave', 'StringValue', 'value', 'ObjectField'],
            ['leave', 'ObjectField', 1, null],
            ['leave', 'ObjectValue', 'value', 'Argument'],
            ['leave', 'Argument', 2, null],
            ['leave', 'Field', 0, null],
            ['leave', 'SelectionSet', 'selectionSet', 'FragmentDefinition'],
            ['leave', 'FragmentDefinition', 3, null],
            ['enter', 'OperationDefinition', 4, null],
            ['enter', 'SelectionSet', 'selectionSet', 'OperationDefinition'],
            ['enter', 'Field', 0, null],
            ['enter', 'Name', 'name', 'Field'],
            ['leave', 'Name', 'name', 'Field'],
            ['enter', 'Argument', 0, null],
            ['enter', 'Name', 'name', 'Argument'],
            ['leave', 'Name', 'name', 'Argument'],
            ['enter', 'BooleanValue', 'value', 'Argument'],
            ['leave', 'BooleanValue', 'value', 'Argument'],
            ['leave', 'Argument', 0, null],
            ['enter', 'Argument', 1, null],
            ['enter', 'Name', 'name', 'Argument'],
            ['leave', 'Name', 'name', 'Argument'],
            ['enter', 'BooleanValue', 'value', 'Argument'],
            ['leave', 'BooleanValue', 'value', 'Argument'],
            ['leave', 'Argument', 1, null],
            ['enter', 'Argument', 2, null],
            ['enter', 'Name', 'name', 'Argument'],
            ['leave', 'Name', 'name', 'Argument'],
            ['enter', 'NullValue', 'value', 'Argument'],
            ['leave', 'NullValue', 'value', 'Argument'],
            ['leave', 'Argument', 2, null],
            ['leave', 'Field', 0, null],
            ['enter', 'Field', 1, null],
            ['enter', 'Name', 'name', 'Field'],
            ['leave', 'Name', 'name', 'Field'],
            ['leave', 'Field', 1, null],
            ['leave', 'SelectionSet', 'selectionSet', 'OperationDefinition'],
            ['leave', 'OperationDefinition', 4, null],
            ['leave', 'Document', null, null],
        ];

        self::assertEquals($expected, $visited);
    }

    /**
     * Describe: visitInParallel
     * Note: nearly identical to the above test of the same test but using visitInParallel.
     */
    public function testAllowsSkippingSubTree(): void
    {
        $visited = [];

        $ast = Parser::parse('{ a, b { x }, c }');
        Visitor::visit(
            $ast,
            Visitor::visitInParallel([
                [
                    'enter' => function ($node) use (&$visited, $ast): ?VisitorOperation {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $visited[] = ['enter', $node->kind, $node->value ?? null];

                        if ($node->kind === 'Field' && isset($node->name->value) && $node->name->value === 'b') {
                            return Visitor::skipNode();
                        }

                        return null;
                    },

                    'leave' => function ($node) use (&$visited, $ast): void {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $visited[] = ['leave', $node->kind, $node->value ?? null];
                    },
                ],
            ])
        );

        self::assertEquals(
            [
                ['enter', 'Document', null],
                ['enter', 'OperationDefinition', null],
                ['enter', 'SelectionSet', null],
                ['enter', 'Field', null],
                ['enter', 'Name', 'a'],
                ['leave', 'Name', 'a'],
                ['leave', 'Field', null],
                ['enter', 'Field', null],
                ['enter', 'Field', null],
                ['enter', 'Name', 'c'],
                ['leave', 'Name', 'c'],
                ['leave', 'Field', null],
                ['leave', 'SelectionSet', null],
                ['leave', 'OperationDefinition', null],
                ['leave', 'Document', null],
            ],
            $visited
        );
    }

    public function testAllowsSkippingDifferentSubTrees(): void
    {
        $visited = [];

        $ast = Parser::parse('{ a { x }, b { y} }');
        Visitor::visit(
            $ast,
            Visitor::visitInParallel([
                [
                    'enter' => function ($node) use (&$visited, $ast): ?VisitorOperation {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $visited[] = ['no-a', 'enter', $node->kind, $node->value ?? null];
                        if ($node->kind === 'Field' && isset($node->name->value) && $node->name->value === 'a') {
                            return Visitor::skipNode();
                        }

                        return null;
                    },
                    'leave' => function ($node) use (&$visited, $ast): void {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $visited[] = ['no-a', 'leave', $node->kind, $node->value ?? null];
                    },
                ],
                [
                    'enter' => function ($node) use (&$visited, $ast): ?VisitorOperation {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $visited[] = ['no-b', 'enter', $node->kind, $node->value ?? null];
                        if ($node->kind === 'Field' && isset($node->name->value) && $node->name->value === 'b') {
                            return Visitor::skipNode();
                        }

                        return null;
                    },
                    'leave' => function ($node) use (&$visited, $ast): void {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $visited[] = ['no-b', 'leave', $node->kind, $node->value ?? null];
                    },
                ],
            ])
        );

        self::assertEquals(
            [
                ['no-a', 'enter', 'Document', null],
                ['no-b', 'enter', 'Document', null],
                ['no-a', 'enter', 'OperationDefinition', null],
                ['no-b', 'enter', 'OperationDefinition', null],
                ['no-a', 'enter', 'SelectionSet', null],
                ['no-b', 'enter', 'SelectionSet', null],
                ['no-a', 'enter', 'Field', null],
                ['no-b', 'enter', 'Field', null],
                ['no-b', 'enter', 'Name', 'a'],
                ['no-b', 'leave', 'Name', 'a'],
                ['no-b', 'enter', 'SelectionSet', null],
                ['no-b', 'enter', 'Field', null],
                ['no-b', 'enter', 'Name', 'x'],
                ['no-b', 'leave', 'Name', 'x'],
                ['no-b', 'leave', 'Field', null],
                ['no-b', 'leave', 'SelectionSet', null],
                ['no-b', 'leave', 'Field', null],
                ['no-a', 'enter', 'Field', null],
                ['no-b', 'enter', 'Field', null],
                ['no-a', 'enter', 'Name', 'b'],
                ['no-a', 'leave', 'Name', 'b'],
                ['no-a', 'enter', 'SelectionSet', null],
                ['no-a', 'enter', 'Field', null],
                ['no-a', 'enter', 'Name', 'y'],
                ['no-a', 'leave', 'Name', 'y'],
                ['no-a', 'leave', 'Field', null],
                ['no-a', 'leave', 'SelectionSet', null],
                ['no-a', 'leave', 'Field', null],
                ['no-a', 'leave', 'SelectionSet', null],
                ['no-b', 'leave', 'SelectionSet', null],
                ['no-a', 'leave', 'OperationDefinition', null],
                ['no-b', 'leave', 'OperationDefinition', null],
                ['no-a', 'leave', 'Document', null],
                ['no-b', 'leave', 'Document', null],
            ],
            $visited
        );
    }

    public function testAllowsEarlyExitWhileVisiting2(): void
    {
        $visited = [];

        $ast = Parser::parse('{ a, b { x }, c }');
        Visitor::visit(
            $ast,
            Visitor::visitInParallel([
                [
                    'enter' => function ($node) use (&$visited, $ast): ?VisitorOperation {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $value = $node->value ?? null;
                        $visited[] = ['enter', $node->kind, $value];
                        if ($node->kind === 'Name' && $value === 'x') {
                            return Visitor::stop();
                        }

                        return null;
                    },
                    'leave' => function ($node) use (&$visited, $ast): void {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $visited[] = ['leave', $node->kind, $node->value ?? null];
                    },
                ],
            ])
        );

        self::assertEquals(
            [
                ['enter', 'Document', null],
                ['enter', 'OperationDefinition', null],
                ['enter', 'SelectionSet', null],
                ['enter', 'Field', null],
                ['enter', 'Name', 'a'],
                ['leave', 'Name', 'a'],
                ['leave', 'Field', null],
                ['enter', 'Field', null],
                ['enter', 'Name', 'b'],
                ['leave', 'Name', 'b'],
                ['enter', 'SelectionSet', null],
                ['enter', 'Field', null],
                ['enter', 'Name', 'x'],
            ],
            $visited
        );
    }

    public function testAllowsEarlyExitFromDifferentPoints(): void
    {
        $visited = [];

        $ast = Parser::parse('{ a { y }, b { x } }');
        Visitor::visit(
            $ast,
            Visitor::visitInParallel([
                [
                    'enter' => function ($node) use (&$visited, $ast): ?VisitorOperation {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $value = $node->value ?? null;
                        $visited[] = ['break-a', 'enter', $node->kind, $value];
                        if ($node->kind === 'Name' && $value === 'a') {
                            return Visitor::stop();
                        }

                        return null;
                    },
                    'leave' => function ($node) use (&$visited, $ast): void {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $visited[] = ['break-a', 'leave', $node->kind, $node->value ?? null];
                    },
                ],
                [
                    'enter' => function ($node) use (&$visited, $ast): ?VisitorOperation {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $value = $node->value ?? null;
                        $visited[] = ['break-b', 'enter', $node->kind, $value];
                        if ($node->kind === 'Name' && $value === 'b') {
                            return Visitor::stop();
                        }

                        return null;
                    },
                    'leave' => function ($node) use (&$visited, $ast): void {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $visited[] = ['break-b', 'leave', $node->kind, $node->value ?? null];
                    },
                ],
            ])
        );

        self::assertEquals(
            [
                ['break-a', 'enter', 'Document', null],
                ['break-b', 'enter', 'Document', null],
                ['break-a', 'enter', 'OperationDefinition', null],
                ['break-b', 'enter', 'OperationDefinition', null],
                ['break-a', 'enter', 'SelectionSet', null],
                ['break-b', 'enter', 'SelectionSet', null],
                ['break-a', 'enter', 'Field', null],
                ['break-b', 'enter', 'Field', null],
                ['break-a', 'enter', 'Name', 'a'],
                ['break-b', 'enter', 'Name', 'a'],
                ['break-b', 'leave', 'Name', 'a'],
                ['break-b', 'enter', 'SelectionSet', null],
                ['break-b', 'enter', 'Field', null],
                ['break-b', 'enter', 'Name', 'y'],
                ['break-b', 'leave', 'Name', 'y'],
                ['break-b', 'leave', 'Field', null],
                ['break-b', 'leave', 'SelectionSet', null],
                ['break-b', 'leave', 'Field', null],
                ['break-b', 'enter', 'Field', null],
                ['break-b', 'enter', 'Name', 'b'],
            ],
            $visited
        );
    }

    public function testAllowsEarlyExitWhileLeaving2(): void
    {
        $visited = [];

        $ast = Parser::parse('{ a, b { x }, c }');
        Visitor::visit(
            $ast,
            Visitor::visitInParallel([
                [
                    'enter' => function ($node) use (&$visited, $ast): void {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $visited[] = ['enter', $node->kind, $node->value ?? null];
                    },
                    'leave' => function ($node) use (&$visited, $ast): ?VisitorOperation {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $value = $node->value ?? null;
                        $visited[] = ['leave', $node->kind, $value];
                        if ($node->kind === 'Name' && $value === 'x') {
                            return Visitor::stop();
                        }

                        return null;
                    },
                ],
            ])
        );

        self::assertEquals(
            [
                ['enter', 'Document', null],
                ['enter', 'OperationDefinition', null],
                ['enter', 'SelectionSet', null],
                ['enter', 'Field', null],
                ['enter', 'Name', 'a'],
                ['leave', 'Name', 'a'],
                ['leave', 'Field', null],
                ['enter', 'Field', null],
                ['enter', 'Name', 'b'],
                ['leave', 'Name', 'b'],
                ['enter', 'SelectionSet', null],
                ['enter', 'Field', null],
                ['enter', 'Name', 'x'],
                ['leave', 'Name', 'x'],
            ],
            $visited
        );
    }

    public function testAllowsEarlyExitFromLeavingDifferentPoints(): void
    {
        $visited = [];

        $ast = Parser::parse('{ a { y }, b { x } }');
        Visitor::visit(
            $ast,
            Visitor::visitInParallel([
                [
                    'enter' => function ($node) use (&$visited, $ast): void {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $visited[] = ['break-a', 'enter', $node->kind, $node->value ?? null];
                    },
                    'leave' => function ($node) use (&$visited, $ast): ?VisitorOperation {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $visited[] = ['break-a', 'leave', $node->kind, $node->value ?? null];
                        if ($node->kind === 'Field' && isset($node->name->value) && $node->name->value === 'a') {
                            return Visitor::stop();
                        }

                        return null;
                    },
                ],
                [
                    'enter' => function ($node) use (&$visited, $ast): void {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $visited[] = ['break-b', 'enter', $node->kind, $node->value ?? null];
                    },
                    'leave' => function ($node) use (&$visited, $ast): ?VisitorOperation {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $visited[] = ['break-b', 'leave', $node->kind, $node->value ?? null];
                        if ($node->kind === 'Field' && isset($node->name->value) && $node->name->value === 'b') {
                            return Visitor::stop();
                        }

                        return null;
                    },
                ],
            ])
        );

        self::assertEquals(
            [
                ['break-a', 'enter', 'Document', null],
                ['break-b', 'enter', 'Document', null],
                ['break-a', 'enter', 'OperationDefinition', null],
                ['break-b', 'enter', 'OperationDefinition', null],
                ['break-a', 'enter', 'SelectionSet', null],
                ['break-b', 'enter', 'SelectionSet', null],
                ['break-a', 'enter', 'Field', null],
                ['break-b', 'enter', 'Field', null],
                ['break-a', 'enter', 'Name', 'a'],
                ['break-b', 'enter', 'Name', 'a'],
                ['break-a', 'leave', 'Name', 'a'],
                ['break-b', 'leave', 'Name', 'a'],
                ['break-a', 'enter', 'SelectionSet', null],
                ['break-b', 'enter', 'SelectionSet', null],
                ['break-a', 'enter', 'Field', null],
                ['break-b', 'enter', 'Field', null],
                ['break-a', 'enter', 'Name', 'y'],
                ['break-b', 'enter', 'Name', 'y'],
                ['break-a', 'leave', 'Name', 'y'],
                ['break-b', 'leave', 'Name', 'y'],
                ['break-a', 'leave', 'Field', null],
                ['break-b', 'leave', 'Field', null],
                ['break-a', 'leave', 'SelectionSet', null],
                ['break-b', 'leave', 'SelectionSet', null],
                ['break-a', 'leave', 'Field', null],
                ['break-b', 'leave', 'Field', null],
                ['break-b', 'enter', 'Field', null],
                ['break-b', 'enter', 'Name', 'b'],
                ['break-b', 'leave', 'Name', 'b'],
                ['break-b', 'enter', 'SelectionSet', null],
                ['break-b', 'enter', 'Field', null],
                ['break-b', 'enter', 'Name', 'x'],
                ['break-b', 'leave', 'Name', 'x'],
                ['break-b', 'leave', 'Field', null],
                ['break-b', 'leave', 'SelectionSet', null],
                ['break-b', 'leave', 'Field', null],
            ],
            $visited
        );
    }

    public function testAllowsForEditingOnEnter2(): void
    {
        $visited = [];

        $ast = Parser::parse('{ a, b, c { a, b, c } }', ['noLocation' => true]);
        $editedAst = Visitor::visit(
            $ast,
            Visitor::visitInParallel([
                [
                    'enter' => function ($node) use ($ast): ?VisitorOperation {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        if ($node->kind === 'Field' && isset($node->name->value) && $node->name->value === 'b') {
                            return Visitor::removeNode();
                        }

                        return null;
                    },
                ],
                [
                    'enter' => function ($node) use (&$visited, $ast): void {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $visited[] = ['enter', $node->kind, $node->value ?? null];
                    },
                    'leave' => function ($node) use (&$visited, $ast): void {
                        $this->checkVisitorFnArgs($ast, func_get_args(), true);
                        $visited[] = ['leave', $node->kind, $node->value ?? null];
                    },
                ],
            ])
        );

        self::assertEquals(
            Parser::parse('{ a, b, c { a, b, c } }', ['noLocation' => true]),
            $ast
        );

        self::assertEquals(
            Parser::parse('{ a,    c { a,    c } }', ['noLocation' => true]),
            $editedAst
        );

        self::assertEquals(
            [
                ['enter', 'Document', null],
                ['enter', 'OperationDefinition', null],
                ['enter', 'SelectionSet', null],
                ['enter', 'Field', null],
                ['enter', 'Name', 'a'],
                ['leave', 'Name', 'a'],
                ['leave', 'Field', null],
                ['enter', 'Field', null],
                ['enter', 'Name', 'c'],
                ['leave', 'Name', 'c'],
                ['enter', 'SelectionSet', null],
                ['enter', 'Field', null],
                ['enter', 'Name', 'a'],
                ['leave', 'Name', 'a'],
                ['leave', 'Field', null],
                ['enter', 'Field', null],
                ['enter', 'Name', 'c'],
                ['leave', 'Name', 'c'],
                ['leave', 'Field', null],
                ['leave', 'SelectionSet', null],
                ['leave', 'Field', null],
                ['leave', 'SelectionSet', null],
                ['leave', 'OperationDefinition', null],
                ['leave', 'Document', null],
            ],
            $visited
        );
    }

    public function testAllowsForEditingOnLeave2(): void
    {
        $visited = [];

        $ast = Parser::parse('{ a, b, c { a, b, c } }', ['noLocation' => true]);
        $editedAst = Visitor::visit(
            $ast,
            Visitor::visitInParallel([
                [
                    'leave' => function ($node) use ($ast): ?VisitorOperation {
                        $this->checkVisitorFnArgs($ast, func_get_args(), true);
                        if ($node->kind === 'Field' && isset($node->name->value) && $node->name->value === 'b') {
                            return Visitor::removeNode();
                        }

                        return null;
                    },
                ],
                [
                    'enter' => function ($node) use (&$visited, $ast): void {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $visited[] = ['enter', $node->kind, $node->value ?? null];
                    },
                    'leave' => function ($node) use (&$visited, $ast): void {
                        $this->checkVisitorFnArgs($ast, func_get_args(), true);
                        $visited[] = ['leave', $node->kind, $node->value ?? null];
                    },
                ],
            ])
        );

        self::assertEquals(
            Parser::parse('{ a, b, c { a, b, c } }', ['noLocation' => true]),
            $ast
        );

        self::assertEquals(
            Parser::parse('{ a,    c { a,    c } }', ['noLocation' => true]),
            $editedAst
        );

        self::assertEquals(
            [
                ['enter', 'Document', null],
                ['enter', 'OperationDefinition', null],
                ['enter', 'SelectionSet', null],
                ['enter', 'Field', null],
                ['enter', 'Name', 'a'],
                ['leave', 'Name', 'a'],
                ['leave', 'Field', null],
                ['enter', 'Field', null],
                ['enter', 'Name', 'b'],
                ['leave', 'Name', 'b'],
                ['enter', 'Field', null],
                ['enter', 'Name', 'c'],
                ['leave', 'Name', 'c'],
                ['enter', 'SelectionSet', null],
                ['enter', 'Field', null],
                ['enter', 'Name', 'a'],
                ['leave', 'Name', 'a'],
                ['leave', 'Field', null],
                ['enter', 'Field', null],
                ['enter', 'Name', 'b'],
                ['leave', 'Name', 'b'],
                ['enter', 'Field', null],
                ['enter', 'Name', 'c'],
                ['leave', 'Name', 'c'],
                ['leave', 'Field', null],
                ['leave', 'SelectionSet', null],
                ['leave', 'Field', null],
                ['leave', 'SelectionSet', null],
                ['leave', 'OperationDefinition', null],
                ['leave', 'Document', null],
            ],
            $visited
        );
    }

    /** Describe: visitWithTypeInfo. */
    public function testMaintainsTypeInfoDuringVisit(): void
    {
        $visited = [];

        $typeInfo = new TypeInfo(ValidatorTestCase::getTestSchema());

        $ast = Parser::parse('{ human(id: 4) { name, pets { ... { name } }, unknown } }');
        Visitor::visit(
            $ast,
            Visitor::visitWithTypeInfo(
                $typeInfo,
                [
                    'enter' => function ($node) use ($typeInfo, &$visited, $ast): void {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $parentType = $typeInfo->getParentType();
                        $type = $typeInfo->getType();
                        $inputType = $typeInfo->getInputType();
                        $visited[] = [
                            'enter',
                            $node->kind,
                            $node->kind === 'Name' ? $node->value : null,
                            $parentType === null ? null : (string) $parentType,
                            $type === null ? null : (string) $type,
                            $inputType === null ? null : (string) $inputType,
                        ];
                    },
                    'leave' => function ($node) use ($typeInfo, &$visited, $ast): void {
                        $this->checkVisitorFnArgs($ast, func_get_args());
                        $parentType = $typeInfo->getParentType();
                        $type = $typeInfo->getType();
                        $inputType = $typeInfo->getInputType();
                        $visited[] = [
                            'leave',
                            $node->kind,
                            $node->kind === 'Name' ? $node->value : null,
                            $parentType === null ? null : (string) $parentType,
                            $type === null ? null : (string) $type,
                            $inputType === null ? null : (string) $inputType,
                        ];
                    },
                ]
            )
        );

        self::assertEquals(
            [
                ['enter', 'Document', null, null, null, null],
                ['enter', 'OperationDefinition', null, null, 'QueryRoot', null],
                ['enter', 'SelectionSet', null, 'QueryRoot', 'QueryRoot', null],
                ['enter', 'Field', null, 'QueryRoot', 'Human', null],
                ['enter', 'Name', 'human', 'QueryRoot', 'Human', null],
                ['leave', 'Name', 'human', 'QueryRoot', 'Human', null],
                ['enter', 'Argument', null, 'QueryRoot', 'Human', 'ID'],
                ['enter', 'Name', 'id', 'QueryRoot', 'Human', 'ID'],
                ['leave', 'Name', 'id', 'QueryRoot', 'Human', 'ID'],
                ['enter', 'IntValue', null, 'QueryRoot', 'Human', 'ID'],
                ['leave', 'IntValue', null, 'QueryRoot', 'Human', 'ID'],
                ['leave', 'Argument', null, 'QueryRoot', 'Human', 'ID'],
                ['enter', 'SelectionSet', null, 'Human', 'Human', null],
                ['enter', 'Field', null, 'Human', 'String', null],
                ['enter', 'Name', 'name', 'Human', 'String', null],
                ['leave', 'Name', 'name', 'Human', 'String', null],
                ['leave', 'Field', null, 'Human', 'String', null],
                ['enter', 'Field', null, 'Human', '[Pet]', null],
                ['enter', 'Name', 'pets', 'Human', '[Pet]', null],
                ['leave', 'Name', 'pets', 'Human', '[Pet]', null],
                ['enter', 'SelectionSet', null, 'Pet', '[Pet]', null],
                ['enter', 'InlineFragment', null, 'Pet', 'Pet', null],
                ['enter', 'SelectionSet', null, 'Pet', 'Pet', null],
                ['enter', 'Field', null, 'Pet', 'String', null],
                ['enter', 'Name', 'name', 'Pet', 'String', null],
                ['leave', 'Name', 'name', 'Pet', 'String', null],
                ['leave', 'Field', null, 'Pet', 'String', null],
                ['leave', 'SelectionSet', null, 'Pet', 'Pet', null],
                ['leave', 'InlineFragment', null, 'Pet', 'Pet', null],
                ['leave', 'SelectionSet', null, 'Pet', '[Pet]', null],
                ['leave', 'Field', null, 'Human', '[Pet]', null],
                ['enter', 'Field', null, 'Human', null, null],
                ['enter', 'Name', 'unknown', 'Human', null, null],
                ['leave', 'Name', 'unknown', 'Human', null, null],
                ['leave', 'Field', null, 'Human', null, null],
                ['leave', 'SelectionSet', null, 'Human', 'Human', null],
                ['leave', 'Field', null, 'QueryRoot', 'Human', null],
                ['leave', 'SelectionSet', null, 'QueryRoot', 'QueryRoot', null],
                ['leave', 'OperationDefinition', null, null, 'QueryRoot', null],
                ['leave', 'Document', null, null, null, null],
            ],
            $visited
        );
    }

    public function testMaintainsTypeInfoDuringEdit(): void
    {
        $visited = [];
        $typeInfo = new TypeInfo(ValidatorTestCase::getTestSchema());

        $ast = Parser::parse(
            '{ human(id: 4) { name, pets }, alien }'
        );
        $editedAst = Visitor::visit(
            $ast,
            Visitor::visitWithTypeInfo(
                $typeInfo,
                [
                    'enter' => function ($node) use ($typeInfo, &$visited, $ast): ?FieldNode {
                        $this->checkVisitorFnArgs($ast, func_get_args(), true);
                        $parentType = $typeInfo->getParentType();
                        $type = $typeInfo->getType();
                        $inputType = $typeInfo->getInputType();
                        $visited[] = [
                            'enter',
                            $node->kind,
                            $node->kind === 'Name' ? $node->value : null,
                            $parentType === null ? null : (string) $parentType,
                            $type === null ? null : (string) $type,
                            $inputType === null ? null : (string) $inputType,
                        ];

                        // Make a query valid by adding missing selection sets.
                        if (
                            $node->kind === 'Field'
                            && ! $node->selectionSet
                            && Type::isCompositeType(Type::getNamedType($type))
                        ) {
                            return new FieldNode([
                                'name' => $node->name,
                                'alias' => $node->alias,
                                'arguments' => $node->arguments,
                                'directives' => $node->directives,
                                'selectionSet' => new SelectionSetNode([
                                    'selections' => new NodeList([
                                        new FieldNode([
                                            'name' => new NameNode(['value' => '__typename']),
                                            'arguments' => new NodeList([]),
                                            'directives' => new NodeList([]),
                                        ]),
                                    ]),
                                ]),
                            ]);
                        }

                        return null;
                    },
                    'leave' => function ($node) use ($typeInfo, &$visited, $ast): void {
                        $this->checkVisitorFnArgs($ast, func_get_args(), true);
                        $parentType = $typeInfo->getParentType();
                        $type = $typeInfo->getType();
                        $inputType = $typeInfo->getInputType();
                        $visited[] = [
                            'leave',
                            $node->kind,
                            $node->kind === 'Name' ? $node->value : null,
                            $parentType === null ? null : (string) $parentType,
                            $type === null ? null : (string) $type,
                            $inputType === null ? null : (string) $inputType,
                        ];
                    },
                ]
            )
        );

        self::assertSame(
            Printer::doPrint(Parser::parse(
                '{ human(id: 4) { name, pets }, alien }'
            )),
            Printer::doPrint($ast)
        );

        self::assertSame(
            Printer::doPrint(Parser::parse(
                '{ human(id: 4) { name, pets { __typename } }, alien { __typename } }'
            )),
            Printer::doPrint($editedAst)
        );

        self::assertEquals(
            [
                ['enter', 'Document', null, null, null, null],
                ['enter', 'OperationDefinition', null, null, 'QueryRoot', null],
                ['enter', 'SelectionSet', null, 'QueryRoot', 'QueryRoot', null],
                ['enter', 'Field', null, 'QueryRoot', 'Human', null],
                ['enter', 'Name', 'human', 'QueryRoot', 'Human', null],
                ['leave', 'Name', 'human', 'QueryRoot', 'Human', null],
                ['enter', 'Argument', null, 'QueryRoot', 'Human', 'ID'],
                ['enter', 'Name', 'id', 'QueryRoot', 'Human', 'ID'],
                ['leave', 'Name', 'id', 'QueryRoot', 'Human', 'ID'],
                ['enter', 'IntValue', null, 'QueryRoot', 'Human', 'ID'],
                ['leave', 'IntValue', null, 'QueryRoot', 'Human', 'ID'],
                ['leave', 'Argument', null, 'QueryRoot', 'Human', 'ID'],
                ['enter', 'SelectionSet', null, 'Human', 'Human', null],
                ['enter', 'Field', null, 'Human', 'String', null],
                ['enter', 'Name', 'name', 'Human', 'String', null],
                ['leave', 'Name', 'name', 'Human', 'String', null],
                ['leave', 'Field', null, 'Human', 'String', null],
                ['enter', 'Field', null, 'Human', '[Pet]', null],
                ['enter', 'Name', 'pets', 'Human', '[Pet]', null],
                ['leave', 'Name', 'pets', 'Human', '[Pet]', null],
                ['enter', 'SelectionSet', null, 'Pet', '[Pet]', null],
                ['enter', 'Field', null, 'Pet', 'String!', null],
                ['enter', 'Name', '__typename', 'Pet', 'String!', null],
                ['leave', 'Name', '__typename', 'Pet', 'String!', null],
                ['leave', 'Field', null, 'Pet', 'String!', null],
                ['leave', 'SelectionSet', null, 'Pet', '[Pet]', null],
                ['leave', 'Field', null, 'Human', '[Pet]', null],
                ['leave', 'SelectionSet', null, 'Human', 'Human', null],
                ['leave', 'Field', null, 'QueryRoot', 'Human', null],
                ['enter', 'Field', null, 'QueryRoot', 'Alien', null],
                ['enter', 'Name', 'alien', 'QueryRoot', 'Alien', null],
                ['leave', 'Name', 'alien', 'QueryRoot', 'Alien', null],
                ['enter', 'SelectionSet', null, 'Alien', 'Alien', null],
                ['enter', 'Field', null, 'Alien', 'String!', null],
                ['enter', 'Name', '__typename', 'Alien', 'String!', null],
                ['leave', 'Name', '__typename', 'Alien', 'String!', null],
                ['leave', 'Field', null, 'Alien', 'String!', null],
                ['leave', 'SelectionSet', null, 'Alien', 'Alien', null],
                ['leave', 'Field', null, 'QueryRoot', 'Alien', null],
                ['leave', 'SelectionSet', null, 'QueryRoot', 'QueryRoot', null],
                ['leave', 'OperationDefinition', null, null, 'QueryRoot', null],
                ['leave', 'Document', null, null, null, null],
            ],
            $visited
        );
    }
}
