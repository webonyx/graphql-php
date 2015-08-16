<?php
namespace GraphQL\Language;

// language/parser.js

use GraphQL\Language\AST\Argument;
use GraphQL\Language\AST\ListValue;
use GraphQL\Language\AST\BooleanValue;
use GraphQL\Language\AST\Directive;
use GraphQL\Language\AST\Document;
use GraphQL\Language\AST\EnumValue;
use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\FloatValue;
use GraphQL\Language\AST\FragmentDefinition;
use GraphQL\Language\AST\FragmentSpread;
use GraphQL\Language\AST\InlineFragment;
use GraphQL\Language\AST\IntValue;
use GraphQL\Language\AST\ListType;
use GraphQL\Language\AST\Location;
use GraphQL\Language\AST\Name;
use GraphQL\Language\AST\NamedType;
use GraphQL\Language\AST\NonNullType;
use GraphQL\Language\AST\ObjectField;
use GraphQL\Language\AST\ObjectValue;
use GraphQL\Language\AST\OperationDefinition;
use GraphQL\Language\AST\SelectionSet;
use GraphQL\Language\AST\StringValue;
use GraphQL\Language\AST\Variable;
use GraphQL\Language\AST\VariableDefinition;
use GraphQL\SyntaxError;

class Parser
{
    /**
     * Available options:
     *
     * noLocation: boolean,
     * (By default, the parser creates AST nodes that know the location
     * in the source that they correspond to. This configuration flag
     * disables that behavior for performance or testing.)
     *
     * noSource: boolean,
     * By default, the parser creates AST nodes that contain a reference
     * to the source that they were created from. This configuration flag
     * disables that behavior for performance or testing.
     *
     * @param Source|string $source
     * @param array $options
     * @return Document
     */
    public static function parse($source, array $options = array())
    {
        $sourceObj = $source instanceof Source ? $source : new Source($source);
        $parser = new self($sourceObj, $options);
        return $parser->parseDocument();
    }

    /**
     * @var Source
     */
    private $source;

    /**
     * @var array
     */
    private $options;

    /**
     * @var int
     */
    private $prevEnd;

    /**
     * @var Lexer
     */
    private $lexer;

    /**
     * @var Token
     */
    private $token;

    function __construct(Source $source, array $options = array())
    {
        $this->lexer = new Lexer($source);
        $this->source = $source;
        $this->options = $options;
        $this->prevEnd = 0;
        $this->token = $this->lexer->nextToken();
    }

    /**
     * Returns a location object, used to identify the place in
     * the source that created a given parsed object.
     *
     * @param int $start
     * @return Location|null
     */
    function loc($start)
    {
        if (!empty($this->options['noLocation'])) {
            return null;
        }
        if (!empty($this->options['noSource'])) {
            return new Location($start, $this->prevEnd);
        }
        return new Location($start, $this->prevEnd, $this->source);
    }

    /**
     * Moves the internal parser object to the next lexed token.
     */
    function advance()
    {
        $prevEnd = $this->token->end;
        $this->prevEnd = $prevEnd;
        $this->token = $this->lexer->nextToken($prevEnd);
    }

    /**
     * Determines if the next token is of a given kind
     *
     * @param $kind
     * @return bool
     */
    function peek($kind)
    {
        return $this->token->kind === $kind;
    }

    /**
     * If the next token is of the given kind, return true after advancing
     * the parser. Otherwise, do not change the parser state and return false.
     *
     * @param $kind
     * @return bool
     */
    function skip($kind)
    {
        $match = $this->token->kind === $kind;

        if ($match) {
            $this->advance();
        }
        return $match;
    }

    /**
     * If the next token is of the given kind, return that token after advancing
     * the parser. Otherwise, do not change the parser state and return false.
     * @param string $kind
     * @return Token
     * @throws SyntaxError
     */
    function expect($kind)
    {
        $token = $this->token;

        if ($token->kind === $kind) {
            $this->advance();
            return $token;
        }

        throw new SyntaxError(
            $this->source,
            $token->start,
            "Expected " . Token::getKindDescription($kind) . ", found " . $token->getDescription()
        );
    }

    /**
     * If the next token is a keyword with the given value, return that token after
     * advancing the parser. Otherwise, do not change the parser state and return
     * false.
     *
     * @param string $value
     * @return Token
     * @throws SyntaxError
     */
    function expectKeyword($value)
    {
        $token = $this->token;

        if ($token->kind === Token::NAME && $token->value === $value) {
            $this->advance();
            return $token;
        }
        throw new SyntaxError(
            $this->source,
            $token->start,
            'Expected "' . $value . '", found ' . $token->getDescription()
        );
    }

    /**
     * @param Token|null $atToken
     * @return SyntaxError
     */
    function unexpected(Token $atToken = null)
    {
        $token = $atToken ?: $this->token;
        return new SyntaxError($this->source, $token->start, "Unexpected " . $token->getDescription());
    }

    /**
     * Returns a possibly empty list of parse nodes, determined by
     * the parseFn. This list begins with a lex token of openKind
     * and ends with a lex token of closeKind. Advances the parser
     * to the next lex token after the closing token.
     *
     * @param int $openKind
     * @param callable $parseFn
     * @param int $closeKind
     * @return array
     * @throws Exception
     */
    function any($openKind, $parseFn, $closeKind)
    {
        $this->expect($openKind);

        $nodes = array();
        while (!$this->skip($closeKind)) {
            $nodes[] = $parseFn($this);
        }
        return $nodes;
    }

    /**
     * Returns a non-empty list of parse nodes, determined by
     * the parseFn. This list begins with a lex token of openKind
     * and ends with a lex token of closeKind. Advances the parser
     * to the next lex token after the closing token.
     *
     * @param $openKind
     * @param $parseFn
     * @param $closeKind
     * @return array
     * @throws Exception
     */
    function many($openKind, $parseFn, $closeKind)
    {
        $this->expect($openKind);

        $nodes = array($parseFn($this));
        while (!$this->skip($closeKind)) {
            $nodes[] = $parseFn($this);
        }
        return $nodes;
    }

    /**
     * Converts a name lex token into a name parse node.
     *
     * @return Name
     * @throws Exception
     */
    function parseName()
    {
        $token = $this->expect(Token::NAME);

        return new Name(array(
            'value' => $token->value,
            'loc' => $this->loc($token->start)
        ));
    }

    /**
     * @return Name
     * @throws SyntaxError
     */
    function parseFragmentName()
    {
        if ($this->token->value === 'on') {
            throw $this->unexpected();
        }
        return $this->parseName();
    }

    /**
     * Implements the parsing rules in the Document section.
     *
     * @return Document
     * @throws Exception
     */
    function parseDocument()
    {
        $start = $this->token->start;
        $definitions = array();

        do {
            if ($this->peek(Token::BRACE_L)) {
                $definitions[] = $this->parseOperationDefinition();
            } else if ($this->peek(Token::NAME)) {
                if ($this->token->value === 'query' || $this->token->value === 'mutation') {
                    $definitions[] = $this->parseOperationDefinition();
                } else if ($this->token->value === 'fragment') {
                    $definitions[] = $this->parseFragmentDefinition();
                } else {
                    throw $this->unexpected();
                }
            } else {
                throw $this->unexpected();
            }
        } while (!$this->skip(Token::EOF));

        return new Document(array(
            'definitions' => $definitions,
            'loc' => $this->loc($start)
        ));
    }

    // Implements the parsing rules in the Operations section.

    /**
     * @return OperationDefinition
     * @throws Exception
     */
    function parseOperationDefinition()
    {
        $start = $this->token->start;
        if ($this->peek(Token::BRACE_L)) {
            return new OperationDefinition(array(
                'operation' => 'query',
                'name' => null,
                'variableDefinitions' => null,
                'directives' => array(),
                'selectionSet' => $this->parseSelectionSet(),
                'loc' => $this->loc($start)
            ));
        }

        $operationToken = $this->expect(Token::NAME);
        $operation = $operationToken->value;

        return new OperationDefinition(array(
            'operation' => $operation,
            'name' => $this->parseName(),
            'variableDefinitions' => $this->parseVariableDefinitions(),
            'directives' => $this->parseDirectives(),
            'selectionSet' => $this->parseSelectionSet(),
            'loc' => $this->loc($start)
        ));
    }

    /**
     * @return array<VariableDefinition>
     */
    function parseVariableDefinitions()
    {
      return $this->peek(Token::PAREN_L) ?
        $this->many(
          Token::PAREN_L,
          array($this, 'parseVariableDefinition'),
          Token::PAREN_R
        ) :
        array();
    }

    /**
     * @return VariableDefinition
     * @throws Exception
     */
    function parseVariableDefinition()
    {
        $start = $this->token->start;
        $var = $this->parseVariable();

        $this->expect(Token::COLON);
        $type = $this->parseType();

        return new VariableDefinition(array(
            'variable' => $var,
            'type' => $type,
            'defaultValue' =>
                ($this->skip(Token::EQUALS) ? $this->parseValueLiteral(true) : null),
            'loc' => $this->loc($start)
        ));
    }

    /**
     * @return Variable
     * @throws Exception
     */
    function parseVariable() {
        $start = $this->token->start;
        $this->expect(Token::DOLLAR);

        return new Variable(array(
            'name' => $this->parseName(),
            'loc' => $this->loc($start)
        ));
    }

    /**
     * @return SelectionSet
     */
    function parseSelectionSet() {
        $start = $this->token->start;
        return new SelectionSet(array(
            'selections' => $this->many(Token::BRACE_L, array($this, 'parseSelection'), Token::BRACE_R),
            'loc' => $this->loc($start)
        ));
    }

    /**
     * @return mixed
     */
    function parseSelection() {
        return $this->peek(Token::SPREAD) ?
            $this->parseFragment() :
            $this->parseField();
    }

    /**
     * @return Field
     */
    function parseField() {
        $start = $this->token->start;
        $nameOrAlias = $this->parseName();

        if ($this->skip(Token::COLON)) {
            $alias = $nameOrAlias;
            $name = $this->parseName();
        } else {
            $alias = null;
            $name = $nameOrAlias;
        }

        return new Field(array(
            'alias' => $alias,
            'name' => $name,
            'arguments' => $this->parseArguments(),
            'directives' => $this->parseDirectives(),
            'selectionSet' => $this->peek(Token::BRACE_L) ? $this->parseSelectionSet() : null,
            'loc' => $this->loc($start)
        ));
    }

    /**
     * @return array<Argument>
     */
    function parseArguments() {
        return $this->peek(Token::PAREN_L) ?
            $this->many(Token::PAREN_L, array($this, 'parseArgument'), Token::PAREN_R) :
            array();
    }

    /**
     * @return Argument
     * @throws Exception
     */
    function parseArgument()
    {
        $start = $this->token->start;
        $name = $this->parseName();

        $this->expect(Token::COLON);
        $value = $this->parseValueLiteral(false);

        return new Argument(array(
            'name' => $name,
            'value' => $value,
            'loc' => $this->loc($start)
        ));
    }

    // Implements the parsing rules in the Fragments section.

    /**
     * @return FragmentSpread|InlineFragment
     * @throws Exception
     */
    function parseFragment() {
        $start = $this->token->start;
        $this->expect(Token::SPREAD);

        if ($this->token->value === 'on') {
            $this->advance();
            return new InlineFragment(array(
                'typeCondition' => $this->parseNamedType(),
                'directives' => $this->parseDirectives(),
                'selectionSet' => $this->parseSelectionSet(),
                'loc' => $this->loc($start)
            ));
        }
        return new FragmentSpread(array(
            'name' => $this->parseFragmentName(),
            'directives' => $this->parseDirectives(),
            'loc' => $this->loc($start)
        ));
    }

    /**
     * @return FragmentDefinition
     * @throws SyntaxError
     */
    function parseFragmentDefinition() {
        $start = $this->token->start;
        $this->expectKeyword('fragment');

        $name = $this->parseFragmentName();
        $this->expectKeyword('on');
        $typeCondition = $this->parseNamedType();

        return new FragmentDefinition(array(
            'name' => $name,
            'typeCondition' => $typeCondition,
            'directives' => $this->parseDirectives(),
            'selectionSet' => $this->parseSelectionSet(),
            'loc' => $this->loc($start)
        ));
    }

    // Implements the parsing rules in the Values section.
    function parseVariableValue()
    {
        return $this->parseValueLiteral(false);
    }

    /**
     * @return BooleanValue|EnumValue|FloatValue|IntValue|StringValue|Variable
     * @throws Exception
     */
    function parseConstValue()
    {
        return $this->parseValueLiteral(true);
    }

    /**
     * @param $isConst
     * @return BooleanValue|EnumValue|FloatValue|IntValue|StringValue|Variable
     * @throws SyntaxError
     */
    function parseValueLiteral($isConst) {
        $token = $this->token;
        switch ($token->kind) {
            case Token::BRACKET_L:
                return $this->parseArray($isConst);
            case Token::BRACE_L:
                return $this->parseObject($isConst);
            case Token::INT:
                $this->advance();
                return new IntValue(array(
                    'value' => $token->value,
                    'loc' => $this->loc($token->start)
                ));
            case Token::FLOAT:
                $this->advance();
                return new FloatValue(array(
                    'value' => $token->value,
                    'loc' => $this->loc($token->start)
                ));
            case Token::STRING:
                $this->advance();
                return new StringValue(array(
                    'value' => $token->value,
                    'loc' => $this->loc($token->start)
                ));
            case Token::NAME:
                if ($token->value === 'true' || $token->value === 'false') {
                    $this->advance();
                    return new BooleanValue(array(
                        'value' => $token->value === 'true',
                        'loc' => $this->loc($token->start)
                    ));
                } else if ($token->value !== 'null') {
                    $this->advance();
                    return new EnumValue(array(
                        'value' => $token->value,
                        'loc' => $this->loc($token->start)
                    ));
                }
                break;

            case Token::DOLLAR:
                if (!$isConst) {
                    return $this->parseVariable();
                }
                break;
        }
        throw $this->unexpected();
    }

    /**
     * @param bool $isConst
     * @return ListValue
     */
    function parseArray($isConst)
    {
        $start = $this->token->start;
        $item = $isConst ? 'parseConstValue' : 'parseVariableValue';
        return new ListValue(array(
            'values' => $this->any(Token::BRACKET_L, array($this, $item), Token::BRACKET_R),
            'loc' => $this->loc($start)
        ));
    }

    function parseObject($isConst)
    {
        $start = $this->token->start;
        $this->expect(Token::BRACE_L);
        $fieldNames = array();
        $fields = array();
        while (!$this->skip(Token::BRACE_R)) {
            $fields[] = $this->parseObjectField($isConst, $fieldNames);
        }
        return new ObjectValue(array(
            'fields' => $fields,
            'loc' => $this->loc($start)
        ));
    }

    function parseObjectField($isConst, &$fieldNames)
    {
        $start = $this->token->start;
        $name = $this->parseName();

        if (array_key_exists($name->value, $fieldNames)) {
            throw new SyntaxError($this->source, $start, "Duplicate input object field " . $name->value . '.');
        }
        $fieldNames[$name->value] = true;
        $this->expect(Token::COLON);

        return new ObjectField(array(
            'name' => $name,
            'value' => $this->parseValueLiteral($isConst),
            'loc' => $this->loc($start)
        ));
    }

    // Implements the parsing rules in the Directives section.

    /**
     * @return array<Directive>
     */
    function parseDirectives()
    {
        $directives = array();
        while ($this->peek(Token::AT)) {
            $directives[] = $this->parseDirective();
        }
        return $directives;
    }

    /**
     * @return Directive
     * @throws Exception
     */
    function parseDirective()
    {
        $start = $this->token->start;
        $this->expect(Token::AT);
        return new Directive(array(
            'name' => $this->parseName(),
            'arguments' => $this->parseArguments(),
            'loc' => $this->loc($start)
        ));
    }

    // Implements the parsing rules in the Types section.

    /**
     * Handles the Type: TypeName, ListType, and NonNullType parsing rules.
     *
     * @return ListType|Name|NonNullType
     * @throws SyntaxError
     */
    function parseType()
    {
        $start = $this->token->start;

        if ($this->skip(Token::BRACKET_L)) {
            $type = $this->parseType();
            $this->expect(Token::BRACKET_R);
            $type = new ListType(array(
                'type' => $type,
                'loc' => $this->loc($start)
            ));
        } else {
            $type = $this->parseNamedType();
        }
        if ($this->skip(Token::BANG)) {
            return new NonNullType(array(
                'type' => $type,
                'loc' => $this->loc($start)
            ));

        }
        return $type;
    }

    function parseNamedType()
    {
        $start = $this->token->start;

        return new NamedType([
            'name' => $this->parseName(),
            'loc' => $this->loc($start)
        ]);

    }
}
