<?php
namespace GraphQL\Tests\Language;

use GraphQL\Language\AST\Document;
use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\Name;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
use GraphQL\Language\AST\OperationDefinition;
use GraphQL\Language\AST\SelectionSet;
use GraphQL\Language\Lexer;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Language\Visitor;
use GraphQL\Tests\Validator\TestCase;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\TypeInfo;

class VisitorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @it allows editing a node both on enter and on leave
     */
    public function testAllowsEditingNodeOnEnterAndOnLeave()
    {
        $parser = new Parser(new Lexer(), [ 'noLocation' => true ]);
        $ast = $parser->parse('{ a, b, c { a, b, c } }');

        $selectionSet = null;
        $editedAst = Visitor::visit($ast, [
            NodeType::OPERATION_DEFINITION => [
                'enter' => function(OperationDefinition $node) use (&$selectionSet) {
                    $selectionSet = $node->getSelectionSet();

                    $newNode = clone $node;
                    $newNode->setSelectionSet(new SelectionSet([]));
                    $newNode->didEnter = true;
                    return $newNode;
                },
                'leave' => function(OperationDefinition $node) use (&$selectionSet) {
                    $newNode = clone $node;
                    $newNode->setSelectionSet($selectionSet);
                    $newNode->didLeave = true;
                    return $newNode;
                }
            ]
        ]);

        $this->assertNotEquals($ast, $editedAst);

        $expected = $ast->cloneDeep();
        $expected->getDefinitions()[0]->didEnter = true;
        $expected->getDefinitions()[0]->didLeave = true;

        $this->assertEquals($expected, $editedAst);
    }

    /**
     * @it allows editing the root node on enter and on leave
     */
    public function testAllowsEditingRootNodeOnEnterAndLeave()
    {
        $parser = new Parser(new Lexer(), [ 'noLocation' => true ]);
        $ast = $parser->parse('{ a, b, c { a, b, c } }');
        $definitions = $ast->getDefinitions();

        $editedAst = Visitor::visit($ast, [
            NodeType::DOCUMENT => [
                'enter' => function (Document $node) {
                    $tmp = clone $node;
                    $tmp->setDefinitions([]);
                    $tmp->didEnter = true;
                    return $tmp;
                },
                'leave' => function(Document $node) use ($definitions) {
                    $tmp = clone $node;
                    $node->setDefinitions($definitions);
                    $node->didLeave = true;
                }
            ]
        ]);

        $this->assertNotEquals($ast, $editedAst);

        $tmp = $ast->cloneDeep();
        $tmp->didEnter = true;
        $tmp->didLeave = true;

        $this->assertEquals($tmp, $editedAst);
    }

    /**
     * @it allows for editing on enter
     */
    public function testAllowsForEditingOnEnter()
    {
        $parser = new Parser(new Lexer(), [ 'noLocation' => true ]);
        $ast = $parser->parse('{ a, b, c { a, b, c } }');
        $editedAst = Visitor::visit($ast, [
            'enter' => function($node) {
                if ($node instanceof Field && $node->getName()->getValue() === 'b') {
                    return Visitor::removeNode();
                }
            }
        ]);

        $this->assertEquals(
            $parser->parse('{ a, b, c { a, b, c } }'),
            $ast
        );
        $this->assertEquals(
            $parser->parse('{ a,    c { a,    c } }'),
            $editedAst
        );
    }

    /**
     * @it allows for editing on leave
     */
    public function testAllowsForEditingOnLeave()
    {
        $parser = new Parser(new Lexer(), [ 'noLocation' => true ]);
        $ast = $parser->parse('{ a, b, c { a, b, c } }');
        $editedAst = Visitor::visit($ast, [
            'leave' => function($node) {
                if ($node instanceof Field && $node->getName()->getValue() === 'b') {
                    return Visitor::removeNode();
                }
            }
        ]);

        $this->assertEquals(
            $parser->parse('{ a, b, c { a, b, c } }'),
            $ast
        );

        $this->assertEquals(
            $parser->parse('{ a,    c { a,    c } }'),
            $editedAst
        );
    }

    /**
     * @it visits edited node
     */
    public function testVisitsEditedNode()
    {
        $addedField = new Field(
            new Name(
                '__typename'
            )
        );

        $didVisitAddedField = false;

        $parser = new Parser(new Lexer());
        $ast = $parser->parse('{ a { x } }');

        Visitor::visit($ast, [
            'enter' => function($node) use ($addedField, &$didVisitAddedField) {
                if ($node instanceof Field && $node->getName()->getValue() === 'a') {
                    return new Field(
                        null,
                        null,
                        [],
                        [],
                        new SelectionSet(
                            array_merge([$addedField], $node->getSelectionSet()->getSelections())
                        )
                    );
                }
                if ($node === $addedField) {
                    $didVisitAddedField = true;
                }
            }
        ]);

        $this->assertTrue($didVisitAddedField);
    }

    /**
     * @it allows skipping a sub-tree
     */
    public function testAllowsSkippingASubTree()
    {
        $visited = [];
        $parser = new Parser(new Lexer());
        $ast = $parser->parse('{ a, b { x }, c }');

        Visitor::visit($ast, [
            'enter' => function(Node $node) use (&$visited) {
                $visited[] = ['enter', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null];
                if ($node instanceof Field && $node->getName()->getValue() === 'b') {
                    return Visitor::skipNode();
                }
            },
            'leave' => function (Node $node) use (&$visited) {
                $visited[] = ['leave', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null];
            }
        ]);

        $expected = [
            [ 'enter', 'Document', null ],
            [ 'enter', 'OperationDefinition', null ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'a' ],
            [ 'leave', 'Name', 'a' ],
            [ 'leave', 'Field', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'c' ],
            [ 'leave', 'Name', 'c' ],
            [ 'leave', 'Field', null ],
            [ 'leave', 'SelectionSet', null ],
            [ 'leave', 'OperationDefinition', null ],
            [ 'leave', 'Document', null ]
        ];

        $this->assertEquals($expected, $visited);
    }

    /**
     * @it allows early exit while visiting
     */
    public function testAllowsEarlyExitWhileVisiting()
    {
        $visited = [];
        $parser = new Parser(new Lexer());
        $ast = $parser->parse('{ a, b { x }, c }');

        Visitor::visit($ast, [
            'enter' => function(Node $node) use (&$visited) {
                $visited[] = ['enter', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null];
                if ($node instanceof Name && $node->getValue() === 'x') {
                    return Visitor::stop();
                }
            },
            'leave' => function(Node $node) use (&$visited) {
                $visited[] = ['leave', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null];
            }
        ]);

        $expected = [
            [ 'enter', 'Document', null ],
            [ 'enter', 'OperationDefinition', null ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'a' ],
            [ 'leave', 'Name', 'a' ],
            [ 'leave', 'Field', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'b' ],
            [ 'leave', 'Name', 'b' ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'x' ]
        ];

        $this->assertEquals($expected, $visited);
    }

    /**
     * @it allows early exit while leaving
     */
    public function testAllowsEarlyExitWhileLeaving()
    {
        $visited = [];
        $parser = new Parser(new Lexer());
        $ast = $parser->parse('{ a, b { x }, c }');
        Visitor::visit($ast, [
            'enter' => function($node) use (&$visited) {
                $visited[] = ['enter', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null];
            },
            'leave' => function($node) use (&$visited) {
                $visited[] = ['leave', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null];

                if ($node->getKind() === NodeType::NAME && $node->getValue() === 'x') {
                    return Visitor::stop();
                }
            }
        ]);

        $this->assertEquals($visited, [
            [ 'enter', 'Document', null ],
            [ 'enter', 'OperationDefinition', null ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'a' ],
            [ 'leave', 'Name', 'a' ],
            [ 'leave', 'Field', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'b' ],
            [ 'leave', 'Name', 'b' ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'x' ],
            [ 'leave', 'Name', 'x' ]
        ]);
    }

    /**
     * @it allows a named functions visitor API
     */
    public function testAllowsANamedFunctionsVisitorAPI()
    {
        $visited = [];
        $parser = new Parser(new Lexer());
        $ast = $parser->parse('{ a, b { x }, c }');

        Visitor::visit($ast, [
            NodeType::NAME => function(Name $node) use (&$visited) {
                $visited[] = ['enter', $node->getKind(), $node->getValue()];
            },
            NodeType::SELECTION_SET => [
                'enter' => function(SelectionSet $node) use (&$visited) {
                    $visited[] = ['enter', $node->getKind(), null];
                },
                'leave' => function(SelectionSet $node) use (&$visited) {
                    $visited[] = ['leave', $node->getKind(), null];
                }
            ]
        ]);

        $expected = [
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Name', 'a' ],
            [ 'enter', 'Name', 'b' ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Name', 'x' ],
            [ 'leave', 'SelectionSet', null ],
            [ 'enter', 'Name', 'c' ],
            [ 'leave', 'SelectionSet', null ],
        ];

        $this->assertEquals($expected, $visited);
    }

    /**
     * @it visits kitchen sink
     */
    public function testVisitsKitchenSink()
    {
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $parser = new Parser(new Lexer());
        $ast = $parser->parse($kitchenSink);

        $visited = [];
        Visitor::visit($ast, [
            'enter' => function(Node $node, $key, $parent) use (&$visited) {
                $r = ['enter', $node->getKind(), $key, $parent instanceof Node ? $parent->getKind() : null];
                $visited[] = $r;
            },
            'leave' => function(Node $node, $key, $parent) use (&$visited) {
                $r = ['leave', $node->getKind(), $key, $parent instanceof Node ? $parent->getKind() : null];
                $visited[] = $r;
            }
        ]);

        $expected = [
            [ 'enter', 'Document', null, null ],
            [ 'enter', 'OperationDefinition', 0, null ],
            [ 'enter', 'Name', 'name', 'OperationDefinition' ],
            [ 'leave', 'Name', 'name', 'OperationDefinition' ],
            [ 'enter', 'VariableDefinition', 0, null ],
            [ 'enter', 'Variable', 'variable', 'VariableDefinition' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'variable', 'VariableDefinition' ],
            [ 'enter', 'NamedType', 'type', 'VariableDefinition' ],
            [ 'enter', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'NamedType', 'type', 'VariableDefinition' ],
            [ 'leave', 'VariableDefinition', 0, null ],
            [ 'enter', 'VariableDefinition', 1, null ],
            [ 'enter', 'Variable', 'variable', 'VariableDefinition' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'variable', 'VariableDefinition' ],
            [ 'enter', 'NamedType', 'type', 'VariableDefinition' ],
            [ 'enter', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'NamedType', 'type', 'VariableDefinition' ],
            [ 'enter', 'EnumValue', 'defaultValue', 'VariableDefinition' ],
            [ 'leave', 'EnumValue', 'defaultValue', 'VariableDefinition' ],
            [ 'leave', 'VariableDefinition', 1, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'alias', 'Field' ],
            [ 'leave', 'Name', 'alias', 'Field' ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'ListValue', 'value', 'Argument' ],
            [ 'enter', 'IntValue', 0, null ],
            [ 'leave', 'IntValue', 0, null ],
            [ 'enter', 'IntValue', 1, null ],
            [ 'leave', 'IntValue', 1, null ],
            [ 'leave', 'ListValue', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'enter', 'InlineFragment', 1, null ],
            [ 'enter', 'NamedType', 'typeCondition', 'InlineFragment' ],
            [ 'enter', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'NamedType', 'typeCondition', 'InlineFragment' ],
            [ 'enter', 'Directive', 0, null ],
            [ 'enter', 'Name', 'name', 'Directive' ],
            [ 'leave', 'Name', 'name', 'Directive' ],
            [ 'leave', 'Directive', 0, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'InlineFragment' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'enter', 'Field', 1, null ],
            [ 'enter', 'Name', 'alias', 'Field' ],
            [ 'leave', 'Name', 'alias', 'Field' ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'IntValue', 'value', 'Argument' ],
            [ 'leave', 'IntValue', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'enter', 'Argument', 1, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'Variable', 'value', 'Argument' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'value', 'Argument' ],
            [ 'leave', 'Argument', 1, null ],
            [ 'enter', 'Directive', 0, null ],
            [ 'enter', 'Name', 'name', 'Directive' ],
            [ 'leave', 'Name', 'name', 'Directive' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'Variable', 'value', 'Argument' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'leave', 'Directive', 0, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'enter', 'FragmentSpread', 1, null ],
            [ 'enter', 'Name', 'name', 'FragmentSpread' ],
            [ 'leave', 'Name', 'name', 'FragmentSpread' ],
            [ 'leave', 'FragmentSpread', 1, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 1, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'InlineFragment' ],
            [ 'leave', 'InlineFragment', 1, null ],
            [ 'enter', 'InlineFragment', 2, null ],
            [ 'enter', 'Directive', 0, null ],
            [ 'enter', 'Name', 'name', 'Directive' ],
            [ 'leave', 'Name', 'name', 'Directive' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'Variable', 'value', 'Argument' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'leave', 'Directive', 0, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'InlineFragment' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'InlineFragment' ],
            [ 'leave', 'InlineFragment', 2, null ],
            [ 'enter', 'InlineFragment', 3, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'InlineFragment' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'InlineFragment' ],
            [ 'leave', 'InlineFragment', 3, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'leave', 'OperationDefinition', 0, null ],
            [ 'enter', 'OperationDefinition', 1, null ],
            [ 'enter', 'Name', 'name', 'OperationDefinition' ],
            [ 'leave', 'Name', 'name', 'OperationDefinition' ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'IntValue', 'value', 'Argument' ],
            [ 'leave', 'IntValue', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'enter', 'Directive', 0, null ],
            [ 'enter', 'Name', 'name', 'Directive' ],
            [ 'leave', 'Name', 'name', 'Directive' ],
            [ 'leave', 'Directive', 0, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'leave', 'OperationDefinition', 1, null ],
            [ 'enter', 'OperationDefinition', 2, null ],
            [ 'enter', 'Name', 'name', 'OperationDefinition' ],
            [ 'leave', 'Name', 'name', 'OperationDefinition' ],
            [ 'enter', 'VariableDefinition', 0, null ],
            [ 'enter', 'Variable', 'variable', 'VariableDefinition' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'variable', 'VariableDefinition' ],
            [ 'enter', 'NamedType', 'type', 'VariableDefinition' ],
            [ 'enter', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'NamedType', 'type', 'VariableDefinition' ],
            [ 'leave', 'VariableDefinition', 0, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'Variable', 'value', 'Argument' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'enter', 'Field', 1, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 1, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'Field' ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'leave', 'OperationDefinition', 2, null ],
            [ 'enter', 'FragmentDefinition', 3, null ],
            [ 'enter', 'Name', 'name', 'FragmentDefinition' ],
            [ 'leave', 'Name', 'name', 'FragmentDefinition' ],
            [ 'enter', 'NamedType', 'typeCondition', 'FragmentDefinition' ],
            [ 'enter', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'Name', 'name', 'NamedType' ],
            [ 'leave', 'NamedType', 'typeCondition', 'FragmentDefinition' ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'FragmentDefinition' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'Variable', 'value', 'Argument' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'enter', 'Argument', 1, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'Variable', 'value', 'Argument' ],
            [ 'enter', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Name', 'name', 'Variable' ],
            [ 'leave', 'Variable', 'value', 'Argument' ],
            [ 'leave', 'Argument', 1, null ],
            [ 'enter', 'Argument', 2, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'ObjectValue', 'value', 'Argument' ],
            [ 'enter', 'ObjectField', 0, null ],
            [ 'enter', 'Name', 'name', 'ObjectField' ],
            [ 'leave', 'Name', 'name', 'ObjectField' ],
            [ 'enter', 'StringValue', 'value', 'ObjectField' ],
            [ 'leave', 'StringValue', 'value', 'ObjectField' ],
            [ 'leave', 'ObjectField', 0, null ],
            [ 'leave', 'ObjectValue', 'value', 'Argument' ],
            [ 'leave', 'Argument', 2, null ],
            [ 'leave', 'Field', 0, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'FragmentDefinition' ],
            [ 'leave', 'FragmentDefinition', 3, null ],
            [ 'enter', 'OperationDefinition', 4, null ],
            [ 'enter', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'enter', 'Field', 0, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'enter', 'Argument', 0, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'BooleanValue', 'value', 'Argument' ],
            [ 'leave', 'BooleanValue', 'value', 'Argument' ],
            [ 'leave', 'Argument', 0, null ],
            [ 'enter', 'Argument', 1, null ],
            [ 'enter', 'Name', 'name', 'Argument' ],
            [ 'leave', 'Name', 'name', 'Argument' ],
            [ 'enter', 'BooleanValue', 'value', 'Argument' ],
            [ 'leave', 'BooleanValue', 'value', 'Argument' ],
            [ 'leave', 'Argument', 1, null ],
            [ 'leave', 'Field', 0, null ],
            [ 'enter', 'Field', 1, null ],
            [ 'enter', 'Name', 'name', 'Field' ],
            [ 'leave', 'Name', 'name', 'Field' ],
            [ 'leave', 'Field', 1, null ],
            [ 'leave', 'SelectionSet', 'selectionSet', 'OperationDefinition' ],
            [ 'leave', 'OperationDefinition', 4, null ],
            [ 'leave', 'Document', null, null ]
        ];

        $this->assertEquals($expected, $visited);
    }

    // Describe: visitInParallel
    // Note: nearly identical to the above test of the same test but using visitInParallel.

    /**
     * @it allows skipping a sub-tree
     */
    public function testAllowsSkippingSubTree()
    {
        $visited = [];

        $parser = new Parser(new Lexer());
        $ast = $parser->parse('{ a, b { x }, c }');
        Visitor::visit($ast, Visitor::visitInParallel([
            [
                'enter' => function(Node $node) use (&$visited) {
                    $visited[] = [ 'enter', $node->getKind(), method_exists($node, 'getValue') ?  $node->getValue() : null];

                    if ($node->getKind() === 'Field' && $node->getName() && $node->getName()->getValue() === 'b') {
                        return Visitor::skipNode();
                    }
                },

                'leave' => function(Node $node) use (&$visited) {
                    $visited[] = ['leave', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null];
                }
            ]
        ]));

        $this->assertEquals([
            [ 'enter', 'Document', null ],
            [ 'enter', 'OperationDefinition', null ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'a' ],
            [ 'leave', 'Name', 'a' ],
            [ 'leave', 'Field', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'c' ],
            [ 'leave', 'Name', 'c' ],
            [ 'leave', 'Field', null ],
            [ 'leave', 'SelectionSet', null ],
            [ 'leave', 'OperationDefinition', null ],
            [ 'leave', 'Document', null ],
        ], $visited);
    }

    /**
     * @it allows skipping different sub-trees
     */
    public function testAllowsSkippingDifferentSubTrees()
    {
        $visited = [];

        $parser = new Parser(new Lexer());
        $ast = $parser->parse('{ a { x }, b { y} }');
        Visitor::visit($ast, Visitor::visitInParallel([
        [
            'enter' => function(Node $node) use (&$visited) {
                $visited[] = ['no-a', 'enter', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null];
                if ($node->getKind() === 'Field' && method_exists($node, 'getName') && $node->getName()->getValue() === 'a') {
                    return Visitor::skipNode();
                }
            },
            'leave' => function(Node $node) use (&$visited) {
                $visited[] = [ 'no-a', 'leave', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null ];
            }
        ],
        [
            'enter' => function(Node $node) use (&$visited) {
                $visited[] = ['no-b', 'enter', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null];
                if ($node->getKind() === 'Field' && method_exists($node, 'getName') && $node->getName()->getValue() === 'b') {
                    return Visitor::skipNode();
                }
            },
            'leave' => function(Node $node) use (&$visited) {
                $visited[] = ['no-b', 'leave', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null];
            }
        ]
        ]));

        $this->assertEquals([
            [ 'no-a', 'enter', 'Document', null ],
            [ 'no-b', 'enter', 'Document', null ],
            [ 'no-a', 'enter', 'OperationDefinition', null ],
            [ 'no-b', 'enter', 'OperationDefinition', null ],
            [ 'no-a', 'enter', 'SelectionSet', null ],
            [ 'no-b', 'enter', 'SelectionSet', null ],
            [ 'no-a', 'enter', 'Field', null ],
            [ 'no-b', 'enter', 'Field', null ],
            [ 'no-b', 'enter', 'Name', 'a' ],
            [ 'no-b', 'leave', 'Name', 'a' ],
            [ 'no-b', 'enter', 'SelectionSet', null ],
            [ 'no-b', 'enter', 'Field', null ],
            [ 'no-b', 'enter', 'Name', 'x' ],
            [ 'no-b', 'leave', 'Name', 'x' ],
            [ 'no-b', 'leave', 'Field', null ],
            [ 'no-b', 'leave', 'SelectionSet', null ],
            [ 'no-b', 'leave', 'Field', null ],
            [ 'no-a', 'enter', 'Field', null ],
            [ 'no-b', 'enter', 'Field', null ],
            [ 'no-a', 'enter', 'Name', 'b' ],
            [ 'no-a', 'leave', 'Name', 'b' ],
            [ 'no-a', 'enter', 'SelectionSet', null ],
            [ 'no-a', 'enter', 'Field', null ],
            [ 'no-a', 'enter', 'Name', 'y' ],
            [ 'no-a', 'leave', 'Name', 'y' ],
            [ 'no-a', 'leave', 'Field', null ],
            [ 'no-a', 'leave', 'SelectionSet', null ],
            [ 'no-a', 'leave', 'Field', null ],
            [ 'no-a', 'leave', 'SelectionSet', null ],
            [ 'no-b', 'leave', 'SelectionSet', null ],
            [ 'no-a', 'leave', 'OperationDefinition', null ],
            [ 'no-b', 'leave', 'OperationDefinition', null ],
            [ 'no-a', 'leave', 'Document', null ],
            [ 'no-b', 'leave', 'Document', null ],
        ], $visited);
    }

    /**
     * @it allows early exit while visiting
     */
    public function testAllowsEarlyExitWhileVisiting2()
    {
        $visited = [];

        $parser = new Parser(new Lexer());
        $ast = $parser->parse('{ a, b { x }, c }');
        Visitor::visit($ast, Visitor::visitInParallel([ [
            'enter' => function(Node $node) use (&$visited) {
                $value = method_exists($node, 'getValue') ? $node->getValue() : null;
                $visited[] = ['enter', $node->getKind(), $value];
                if ($node->getKind() === 'Name' && $value === 'x') {
                    return Visitor::stop();
                }
            },
            'leave' => function(Node $node) use (&$visited) {
                $visited[] = ['leave', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null];
            }
        ] ]));

        $this->assertEquals([
            [ 'enter', 'Document', null ],
            [ 'enter', 'OperationDefinition', null ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'a' ],
            [ 'leave', 'Name', 'a' ],
            [ 'leave', 'Field', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'b' ],
            [ 'leave', 'Name', 'b' ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'x' ]
        ], $visited);
    }

    /**
     * @it allows early exit from different points
     */
    public function testAllowsEarlyExitFromDifferentPoints()
    {
        $visited = [];

        $parser = new Parser(new Lexer());
        $ast = $parser->parse('{ a { y }, b { x } }');
        Visitor::visit($ast, Visitor::visitInParallel([
        [
            'enter' => function(Node $node) use (&$visited) {
                $value = method_exists($node, 'getValue') ? $node->getValue() : null;
                $visited[] = ['break-a', 'enter', $node->getKind(), $value];
                if ($node->getKind() === 'Name' && $value === 'a') {
                    return Visitor::stop();
                }
            },
            'leave' => function(Node $node) use (&$visited) {
                $visited[] = [ 'break-a', 'leave', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null ];
            }
        ],
        [
            'enter' => function(Node $node) use (&$visited) {
                $value = method_exists($node, 'getValue') ? $node->getValue() : null;
                $visited[] = ['break-b', 'enter', $node->getKind(), $value];
                if ($node->getKind() === 'Name' && $value === 'b') {
                    return Visitor::stop();
                }
            },
            'leave' => function(Node $node) use (&$visited) {
                $visited[] = ['break-b', 'leave', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null];
            }
        ],
        ]));

        $this->assertEquals([
            [ 'break-a', 'enter', 'Document', null ],
            [ 'break-b', 'enter', 'Document', null ],
            [ 'break-a', 'enter', 'OperationDefinition', null ],
            [ 'break-b', 'enter', 'OperationDefinition', null ],
            [ 'break-a', 'enter', 'SelectionSet', null ],
            [ 'break-b', 'enter', 'SelectionSet', null ],
            [ 'break-a', 'enter', 'Field', null ],
            [ 'break-b', 'enter', 'Field', null ],
            [ 'break-a', 'enter', 'Name', 'a' ],
            [ 'break-b', 'enter', 'Name', 'a' ],
            [ 'break-b', 'leave', 'Name', 'a' ],
            [ 'break-b', 'enter', 'SelectionSet', null ],
            [ 'break-b', 'enter', 'Field', null ],
            [ 'break-b', 'enter', 'Name', 'y' ],
            [ 'break-b', 'leave', 'Name', 'y' ],
            [ 'break-b', 'leave', 'Field', null ],
            [ 'break-b', 'leave', 'SelectionSet', null ],
            [ 'break-b', 'leave', 'Field', null ],
            [ 'break-b', 'enter', 'Field', null ],
            [ 'break-b', 'enter', 'Name', 'b' ]
        ], $visited);
    }

    /**
     * @it allows early exit while leaving
     */
    public function testAllowsEarlyExitWhileLeaving2()
    {
        $visited = [];

        $parser = new Parser(new Lexer());
        $ast = $parser->parse('{ a, b { x }, c }');
        Visitor::visit($ast, Visitor::visitInParallel([ [
            'enter' => function(Node $node) use (&$visited) {
                $visited[] = ['enter', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null];
            },
            'leave' => function(Node $node) use (&$visited) {
                $value = method_exists($node, 'getValue') ? $node->getValue() : null;
                $visited[] = ['leave', $node->getKind(), $value];
                if ($node->getKind() === 'Name' && $value === 'x') {
                    return Visitor::stop();
                }
            }
        ] ]));

        $this->assertEquals([
            [ 'enter', 'Document', null ],
            [ 'enter', 'OperationDefinition', null ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'a' ],
            [ 'leave', 'Name', 'a' ],
            [ 'leave', 'Field', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'b' ],
            [ 'leave', 'Name', 'b' ],
            [ 'enter', 'SelectionSet', null ],
            [ 'enter', 'Field', null ],
            [ 'enter', 'Name', 'x' ],
            [ 'leave', 'Name', 'x' ]
        ], $visited);
    }

    /**
     * @it allows early exit from leaving different points
     */
    public function testAllowsEarlyExitFromLeavingDifferentPoints()
    {
        $visited = [];

        $parser = new Parser(new Lexer());
        $ast = $parser->parse('{ a { y }, b { x } }');
        Visitor::visit($ast, Visitor::visitInParallel([
            [
                'enter' => function(Node $node) use (&$visited) {
                    $visited[] = ['break-a', 'enter', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null];
                },
                'leave' => function(Node $node) use (&$visited) {
                    $visited[] = ['break-a', 'leave', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null];
                    if ($node->getKind() === 'Field' && method_exists($node, 'getName') && $node->getName()->getValue() === 'a') {
                        return Visitor::stop();
                    }
                }
            ],
            [
                'enter' => function($node) use (&$visited) {
                    $visited[] = ['break-b', 'enter', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null];
                },
                'leave' => function($node) use (&$visited) {
                    $visited[] = ['break-b', 'leave', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null];
                    if ($node->getKind() === 'Field' && method_exists($node, 'getName') && $node->getName()->getValue() === 'b') {
                        return Visitor::stop();
                    }
                }
            ],
        ]));

        $this->assertEquals([
            [ 'break-a', 'enter', 'Document', null ],
            [ 'break-b', 'enter', 'Document', null ],
            [ 'break-a', 'enter', 'OperationDefinition', null ],
            [ 'break-b', 'enter', 'OperationDefinition', null ],
            [ 'break-a', 'enter', 'SelectionSet', null ],
            [ 'break-b', 'enter', 'SelectionSet', null ],
            [ 'break-a', 'enter', 'Field', null ],
            [ 'break-b', 'enter', 'Field', null ],
            [ 'break-a', 'enter', 'Name', 'a' ],
            [ 'break-b', 'enter', 'Name', 'a' ],
            [ 'break-a', 'leave', 'Name', 'a' ],
            [ 'break-b', 'leave', 'Name', 'a' ],
            [ 'break-a', 'enter', 'SelectionSet', null ],
            [ 'break-b', 'enter', 'SelectionSet', null ],
            [ 'break-a', 'enter', 'Field', null ],
            [ 'break-b', 'enter', 'Field', null ],
            [ 'break-a', 'enter', 'Name', 'y' ],
            [ 'break-b', 'enter', 'Name', 'y' ],
            [ 'break-a', 'leave', 'Name', 'y' ],
            [ 'break-b', 'leave', 'Name', 'y' ],
            [ 'break-a', 'leave', 'Field', null ],
            [ 'break-b', 'leave', 'Field', null ],
            [ 'break-a', 'leave', 'SelectionSet', null ],
            [ 'break-b', 'leave', 'SelectionSet', null ],
            [ 'break-a', 'leave', 'Field', null ],
            [ 'break-b', 'leave', 'Field', null ],
            [ 'break-b', 'enter', 'Field', null ],
            [ 'break-b', 'enter', 'Name', 'b' ],
            [ 'break-b', 'leave', 'Name', 'b' ],
            [ 'break-b', 'enter', 'SelectionSet', null ],
            [ 'break-b', 'enter', 'Field', null ],
            [ 'break-b', 'enter', 'Name', 'x' ],
            [ 'break-b', 'leave', 'Name', 'x' ],
            [ 'break-b', 'leave', 'Field', null ],
            [ 'break-b', 'leave', 'SelectionSet', null ],
            [ 'break-b', 'leave', 'Field', null ]
        ], $visited);
    }

    /**
     * @it allows for editing on enter
     */
    public function testAllowsForEditingOnEnter2()
    {
        $visited = [];

        $parser = new Parser(new Lexer(), [ 'noLocation' => true ]);
        $ast = $parser->parse('{ a, b, c { a, b, c } }');
        $editedAst = Visitor::visit($ast, Visitor::visitInParallel([
            [
                'enter' => function (Node $node) use (&$visited) {
                    if ($node->getKind() === 'Field' && method_exists($node, 'getName') && $node->getName()->getValue() === 'b') {
                        return Visitor::removeNode();
                    }
                }
            ],
            [
                'enter' => function (Node $node) use (&$visited) {
                    $visited[] = ['enter', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null];
                },
                'leave' => function (Node $node) use (&$visited) {
                    $visited[] = ['leave', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null];
                }
            ],
        ]));

        $this->assertEquals(
            $parser->parse('{ a, b, c { a, b, c } }'),
            $ast
        );

        $this->assertEquals(
            $parser->parse('{ a,    c { a,    c } }'),
            $editedAst
        );

        $this->assertEquals([
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
            ['leave', 'Document', null]
        ], $visited);
    }

    /**
     * @it allows for editing on leave
     */
    public function testAllowsForEditingOnLeave2()
    {
        $visited = [];

        $parser = new Parser(new Lexer(), [ 'noLocation' => true ]);
        $ast = $parser->parse('{ a, b, c { a, b, c } }');
        $editedAst = Visitor::visit($ast, Visitor::visitInParallel([
            [
                'leave' => function (Node $node) use (&$visited) {
                    if ($node->getKind() === 'Field' && method_exists($node, 'getName') && $node->getName()->getValue() === 'b') {
                        return Visitor::removeNode();
                    }
                }
            ],
            [
                'enter' => function (Node $node) use (&$visited) {
                    $visited[] = ['enter', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null];
                },
                'leave' => function (Node $node) use (&$visited) {
                    $visited[] = ['leave', $node->getKind(), method_exists($node, 'getValue') ? $node->getValue() : null];
                }
            ],
        ]));

        $this->assertEquals(
            $parser->parse('{ a, b, c { a, b, c } }', ['noLocation' => true]),
            $ast
        );

        $this->assertEquals(
            $parser->parse('{ a,    c { a,    c } }', ['noLocation' => true]),
            $editedAst
        );

        $this->assertEquals([
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
            ['leave', 'Document', null]
        ], $visited);
    }

    // Describe: visitWithTypeInfo

    /**
     * @it maintains type info during visit
     */
    public function testMaintainsTypeInfoDuringVisit()
    {
        $visited = [];

        $typeInfo = new TypeInfo(TestCase::getDefaultSchema());

        $parser = new Parser(new Lexer());
        $ast = $parser->parse('{ human(id: 4) { name, pets { name }, unknown } }');
        Visitor::visit($ast, Visitor::visitWithTypeInfo($typeInfo, [
            'enter' => function (Node $node) use ($typeInfo, &$visited) {
                $parentType = $typeInfo->getParentType();
                $type = $typeInfo->getType();
                $inputType = $typeInfo->getInputType();
                $visited[] = [
                    'enter',
                    $node->getKind(),
                    $node->getKind() === 'Name' ? $node->getValue() : null,
                    $parentType ? (string)$parentType : null,
                    $type ? (string)$type : null,
                    $inputType ? (string)$inputType : null
                ];
            },
            'leave' => function (Node $node) use ($typeInfo, &$visited) {
                $parentType = $typeInfo->getParentType();
                $type = $typeInfo->getType();
                $inputType = $typeInfo->getInputType();
                $visited[] = [
                    'leave',
                    $node->getKind(),
                    $node->getKind() === 'Name' ? $node->getValue() : null,
                    $parentType ? (string)$parentType : null,
                    $type ? (string)$type : null,
                    $inputType ? (string)$inputType : null
                ];
            }
        ]));

        $this->assertEquals([
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
            ['enter', 'Field', null, 'Pet', 'String', null],
            ['enter', 'Name', 'name', 'Pet', 'String', null],
            ['leave', 'Name', 'name', 'Pet', 'String', null],
            ['leave', 'Field', null, 'Pet', 'String', null],
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
            ['leave', 'Document', null, null, null, null]
        ], $visited);
    }

    /**
     * @it maintains type info during edit
     */
    public function testMaintainsTypeInfoDuringEdit()
    {
        $visited = [];
        $typeInfo = new TypeInfo(TestCase::getDefaultSchema());

        $parser = new Parser(new Lexer());
        $ast = $parser->parse(
            '{ human(id: 4) { name, pets }, alien }'
        );
        $editedAst = Visitor::visit($ast, Visitor::visitWithTypeInfo($typeInfo, [
            'enter' => function (Node $node) use ($typeInfo, &$visited) {
                $parentType = $typeInfo->getParentType();
                $type = $typeInfo->getType();
                $inputType = $typeInfo->getInputType();
                $visited[] = [
                    'enter',
                    $node->getKind(),
                    $node->getKind() === 'Name' ? $node->getValue() : null,
                    $parentType ? (string)$parentType : null,
                    $type ? (string)$type : null,
                    $inputType ? (string)$inputType : null
                ];

                // Make a query valid by adding missing selection sets.
                if (
                    $node->getKind() === 'Field' &&
                    !$node->getSelectionSet() &&
                    Type::isCompositeType(Type::getNamedType($type))
                ) {
                    return new Field(
                        $node->getName(),
                        $node->getAlias(),
                        $node->getArguments(),
                        $node->getDirectives(),
                        new SelectionSet(
                            [
                                new Field(
                                    new Name('__typename')
                                )
                            ]
                        )
                    );
                }
            },
            'leave' => function (Node $node) use ($typeInfo, &$visited) {
                $parentType = $typeInfo->getParentType();
                $type = $typeInfo->getType();
                $inputType = $typeInfo->getInputType();
                $visited[] = [
                    'leave',
                    $node->getKind(),
                    $node->getKind() === 'Name' ? $node->getValue() : null,
                    $parentType ? (string)$parentType : null,
                    $type ? (string)$type : null,
                    $inputType ? (string)$inputType : null
                ];
            }
        ]));

        $printer = new Printer();
        $this->assertEquals($printer->doPrint($parser->parse(
            '{ human(id: 4) { name, pets }, alien }'
        )), $printer->doPrint($ast));

        $this->assertEquals($printer->doPrint($parser->parse(
            '{ human(id: 4) { name, pets { __typename } }, alien { __typename } }'
        )), $printer->doPrint($editedAst));

        $this->assertEquals([
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
            ['leave', 'Document', null, null, null, null]
        ], $visited);
    }
}
