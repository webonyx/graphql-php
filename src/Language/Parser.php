<?php

declare(strict_types=1);

namespace GraphQL\Language;

use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\DefinitionNode;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumTypeExtensionNode;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\ExecutableDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeExtensionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeExtensionNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\Location;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\OperationTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeExtensionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\SchemaTypeExtensionNode;
use GraphQL\Language\AST\SelectionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\TypeExtensionNode;
use GraphQL\Language\AST\TypeNode;
use GraphQL\Language\AST\TypeSystemDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeExtensionNode;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\AST\VariableNode;
use function count;
use function sprintf;

/**
 * Parses string containing GraphQL query or [type definition](type-system/type-language.md) to Abstract Syntax Tree.
 *
 * @method static NameNode name(Source|string $source, bool[] $options = [])
 * @method static DocumentNode document(Source|string $source, bool[] $options = [])
 * @method static ExecutableDefinitionNode|TypeSystemDefinitionNode definition(Source|string $source, bool[] $options = [])
 * @method static ExecutableDefinitionNode executableDefinition(Source|string $source, bool[] $options = [])
 * @method static OperationDefinitionNode operationDefinition(Source|string $source, bool[] $options = [])
 * @method static string operationType(Source|string $source, bool[] $options = [])
 * @method static NodeList<VariableDefinitionNode> variableDefinitions(Source|string $source, bool[] $options = [])
 * @method static VariableDefinitionNode variableDefinition(Source|string $source, bool[] $options = [])
 * @method static VariableNode variable(Source|string $source, bool[] $options = [])
 * @method static SelectionSetNode selectionSet(Source|string $source, bool[] $options = [])
 * @method static mixed selection(Source|string $source, bool[] $options = [])
 * @method static FieldNode field(Source|string $source, bool[] $options = [])
 * @method static NodeList<ArgumentNode> arguments(Source|string $source, bool[] $options = [])
 * @method static ArgumentNode argument(Source|string $source, bool[] $options = [])
 * @method static ArgumentNode constArgument(Source|string $source, bool[] $options = [])
 * @method static FragmentSpreadNode|InlineFragmentNode fragment(Source|string $source, bool[] $options = [])
 * @method static FragmentDefinitionNode fragmentDefinition(Source|string $source, bool[] $options = [])
 * @method static NameNode fragmentName(Source|string $source, bool[] $options = [])
 * @method static BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|ListValueNode|NullValueNode|ObjectValueNode|StringValueNode|VariableNode valueLiteral(Source|string $source, bool[] $options = [])
 * @method static StringValueNode stringLiteral(Source|string $source, bool[] $options = [])
 * @method static BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|StringValueNode|VariableNode constValue(Source|string $source, bool[] $options = [])
 * @method static BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|ListValueNode|ObjectValueNode|StringValueNode|VariableNode variableValue(Source|string $source, bool[] $options = [])
 * @method static ListValueNode array(Source|string $source, bool[] $options = [])
 * @method static ObjectValueNode object(Source|string $source, bool[] $options = [])
 * @method static ObjectFieldNode objectField(Source|string $source, bool[] $options = [])
 * @method static NodeList<DirectiveNode> directives(Source|string $source, bool[] $options = [])
 * @method static DirectiveNode directive(Source|string $source, bool[] $options = [])
 * @method static ListTypeNode|NameNode|NonNullTypeNode typeReference(Source|string $source, bool[] $options = [])
 * @method static NamedTypeNode namedType(Source|string $source, bool[] $options = [])
 * @method static TypeSystemDefinitionNode typeSystemDefinition(Source|string $source, bool[] $options = [])
 * @method static StringValueNode|null description(Source|string $source, bool[] $options = [])
 * @method static SchemaDefinitionNode schemaDefinition(Source|string $source, bool[] $options = [])
 * @method static OperationTypeDefinitionNode operationTypeDefinition(Source|string $source, bool[] $options = [])
 * @method static ScalarTypeDefinitionNode scalarTypeDefinition(Source|string $source, bool[] $options = [])
 * @method static ObjectTypeDefinitionNode objectTypeDefinition(Source|string $source, bool[] $options = [])
 * @method static NamedTypeNode[] implementsInterfaces(Source|string $source, bool[] $options = [])
 * @method static FieldDefinitionNode[] fieldsDefinition(Source|string $source, bool[] $options = [])
 * @method static FieldDefinitionNode fieldDefinition(Source|string $source, bool[] $options = [])
 * @method static InputValueDefinitionNode[] argumentsDefinition(Source|string $source, bool[] $options = [])
 * @method static InputValueDefinitionNode inputValueDefinition(Source|string $source, bool[] $options = [])
 * @method static InterfaceTypeDefinitionNode interfaceTypeDefinition(Source|string $source, bool[] $options = [])
 * @method static UnionTypeDefinitionNode unionTypeDefinition(Source|string $source, bool[] $options = [])
 * @method static NamedTypeNode[] unionMemberTypes(Source|string $source, bool[] $options = [])
 * @method static EnumTypeDefinitionNode enumTypeDefinition(Source|string $source, bool[] $options = [])
 * @method static EnumValueDefinitionNode[] enumValuesDefinition(Source|string $source, bool[] $options = [])
 * @method static EnumValueDefinitionNode enumValueDefinition(Source|string $source, bool[] $options = [])
 * @method static InputObjectTypeDefinitionNode inputObjectTypeDefinition(Source|string $source, bool[] $options = [])
 * @method static InputValueDefinitionNode[] inputFieldsDefinition(Source|string $source, bool[] $options = [])
 * @method static TypeExtensionNode typeExtension(Source|string $source, bool[] $options = [])
 * @method static SchemaTypeExtensionNode schemaTypeExtension(Source|string $source, bool[] $options = [])
 * @method static ScalarTypeExtensionNode scalarTypeExtension(Source|string $source, bool[] $options = [])
 * @method static ObjectTypeExtensionNode objectTypeExtension(Source|string $source, bool[] $options = [])
 * @method static InterfaceTypeExtensionNode interfaceTypeExtension(Source|string $source, bool[] $options = [])
 * @method static UnionTypeExtensionNode unionTypeExtension(Source|string $source, bool[] $options = [])
 * @method static EnumTypeExtensionNode enumTypeExtension(Source|string $source, bool[] $options = [])
 * @method static InputObjectTypeExtensionNode inputObjectTypeExtension(Source|string $source, bool[] $options = [])
 * @method static DirectiveDefinitionNode directiveDefinition(Source|string $source, bool[] $options = [])
 * @method static DirectiveLocation[] directiveLocations(Source|string $source, bool[] $options = [])
 * @method static DirectiveLocation directiveLocation(Source|string $source, bool[] $options = [])
 */
class Parser
{
    /**
     * Given a GraphQL source, parses it into a `GraphQL\Language\AST\DocumentNode`.
     * Throws `GraphQL\Error\SyntaxError` if a syntax error is encountered.
     *
     * Available options:
     *
     * noLocation: boolean,
     *   (By default, the parser creates AST nodes that know the location
     *   in the source that they correspond to. This configuration flag
     *   disables that behavior for performance or testing.)
     *
     * allowLegacySDLEmptyFields: boolean
     *   If enabled, the parser will parse empty fields sets in the Schema
     *   Definition Language. Otherwise, the parser will follow the current
     *   specification.
     *
     *   This option is provided to ease adoption of the final SDL specification
     *   and will be removed in a future major release.
     *
     * allowLegacySDLImplementsInterfaces: boolean
     *   If enabled, the parser will parse implemented interfaces with no `&`
     *   character between each interface. Otherwise, the parser will follow the
     *   current specification.
     *
     *   This option is provided to ease adoption of the final SDL specification
     *   and will be removed in a future major release.
     *
     * experimentalFragmentVariables: boolean,
     *   (If enabled, the parser will understand and parse variable definitions
     *   contained in a fragment definition. They'll be represented in the
     *   `variableDefinitions` field of the FragmentDefinitionNode.
     *
     *   The syntax is identical to normal, query-defined variables. For example:
     *
     *     fragment A($var: Boolean = false) on T  {
     *       ...
     *     }
     *
     *   Note: this feature is experimental and may change or be removed in the
     *   future.)
     *
     * @param Source|string $source
     * @param bool[]        $options
     *
     * @return DocumentNode
     *
     * @throws SyntaxError
     *
     * @api
     */
    public static function parse($source, array $options = [])
    {
        $parser = new self($source, $options);

        return $parser->parseDocument();
    }

    /**
     * Given a string containing a GraphQL value (ex. `[42]`), parse the AST for
     * that value.
     * Throws `GraphQL\Error\SyntaxError` if a syntax error is encountered.
     *
     * This is useful within tools that operate upon GraphQL Values directly and
     * in isolation of complete GraphQL documents.
     *
     * Consider providing the results to the utility function: `GraphQL\Utils\AST::valueFromAST()`.
     *
     * @param Source|string $source
     * @param bool[]        $options
     *
     * @return BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|ListValueNode|ObjectValueNode|StringValueNode|VariableNode
     *
     * @api
     */
    public static function parseValue($source, array $options = [])
    {
        $parser = new Parser($source, $options);
        $parser->expect(Token::SOF);
        $value = $parser->parseValueLiteral(false);
        $parser->expect(Token::EOF);

        return $value;
    }

    /**
     * Given a string containing a GraphQL Type (ex. `[Int!]`), parse the AST for
     * that type.
     * Throws `GraphQL\Error\SyntaxError` if a syntax error is encountered.
     *
     * This is useful within tools that operate upon GraphQL Types directly and
     * in isolation of complete GraphQL documents.
     *
     * Consider providing the results to the utility function: `GraphQL\Utils\AST::typeFromAST()`.
     *
     * @param Source|string $source
     * @param bool[]        $options
     *
     * @return ListTypeNode|NameNode|NonNullTypeNode
     *
     * @api
     */
    public static function parseType($source, array $options = [])
    {
        $parser = new Parser($source, $options);
        $parser->expect(Token::SOF);
        $type = $parser->parseTypeReference();
        $parser->expect(Token::EOF);

        return $type;
    }

    /**
     * Parse partial source by delegating calls to the internal parseX methods.
     *
     * @param bool[] $arguments
     *
     * @throws SyntaxError
     */
    public static function __callStatic(string $name, array $arguments) : Node
    {
        $parser = new Parser(...$arguments);
        $parser->expect(Token::SOF);
        $type = $parser->{'parse' . $name}();
        $parser->expect(Token::EOF);

        return $type;
    }

    /** @var Lexer */
    private $lexer;

    /**
     * @param Source|string $source
     * @param bool[]        $options
     */
    public function __construct($source, array $options = [])
    {
        $sourceObj   = $source instanceof Source ? $source : new Source($source);
        $this->lexer = new Lexer($sourceObj, $options);
    }

    /**
     * Returns a location object, used to identify the place in
     * the source that created a given parsed object.
     */
    private function loc(Token $startToken) : ?Location
    {
        if (! ($this->lexer->options['noLocation'] ?? false)) {
            return new Location($startToken, $this->lexer->lastToken, $this->lexer->source);
        }

        return null;
    }

    /**
     * Determines if the next token is of a given kind
     */
    private function peek(string $kind) : bool
    {
        return $this->lexer->token->kind === $kind;
    }

    /**
     * If the next token is of the given kind, return true after advancing
     * the parser. Otherwise, do not change the parser state and return false.
     */
    private function skip(string $kind) : bool
    {
        $match = $this->lexer->token->kind === $kind;

        if ($match) {
            $this->lexer->advance();
        }

        return $match;
    }

    /**
     * If the next token is of the given kind, return that token after advancing
     * the parser. Otherwise, do not change the parser state and return false.
     *
     * @throws SyntaxError
     */
    private function expect(string $kind) : Token
    {
        $token = $this->lexer->token;

        if ($token->kind === $kind) {
            $this->lexer->advance();

            return $token;
        }

        throw new SyntaxError(
            $this->lexer->source,
            $token->start,
            sprintf('Expected %s, found %s', $kind, $token->getDescription())
        );
    }

    /**
     * If the next token is a keyword with the given value, advance the lexer.
     * Otherwise, throw an error.
     *
     * @throws SyntaxError
     */
    private function expectKeyword(string $value) : void
    {
        $token = $this->lexer->token;
        if ($token->kind !== Token::NAME || $token->value !== $value) {
            throw new SyntaxError(
                $this->lexer->source,
                $token->start,
                'Expected "' . $value . '", found ' . $token->getDescription()
            );
        }

        $this->lexer->advance();
    }

    /**
     * If the next token is a given keyword, return "true" after advancing
     * the lexer. Otherwise, do not change the parser state and return "false".
     */
    private function expectOptionalKeyword(string $value) : bool
    {
        $token = $this->lexer->token;
        if ($token->kind === Token::NAME && $token->value === $value) {
            $this->lexer->advance();

            return true;
        }

        return false;
    }

    private function unexpected(?Token $atToken = null) : SyntaxError
    {
        $token = $atToken ?? $this->lexer->token;

        return new SyntaxError($this->lexer->source, $token->start, 'Unexpected ' . $token->getDescription());
    }

    /**
     * Returns a possibly empty list of parse nodes, determined by
     * the parseFn. This list begins with a lex token of openKind
     * and ends with a lex token of closeKind. Advances the parser
     * to the next lex token after the closing token.
     *
     * @throws SyntaxError
     */
    private function any(string $openKind, callable $parseFn, string $closeKind) : NodeList
    {
        $this->expect($openKind);

        $nodes = [];
        while (! $this->skip($closeKind)) {
            $nodes[] = $parseFn($this);
        }

        return new NodeList($nodes);
    }

    /**
     * Returns a non-empty list of parse nodes, determined by
     * the parseFn. This list begins with a lex token of openKind
     * and ends with a lex token of closeKind. Advances the parser
     * to the next lex token after the closing token.
     *
     * @throws SyntaxError
     */
    private function many(string $openKind, callable $parseFn, string $closeKind) : NodeList
    {
        $this->expect($openKind);

        $nodes = [$parseFn($this)];
        while (! $this->skip($closeKind)) {
            $nodes[] = $parseFn($this);
        }

        return new NodeList($nodes);
    }

    /**
     * Converts a name lex token into a name parse node.
     *
     * @throws SyntaxError
     */
    private function parseName() : NameNode
    {
        $token = $this->expect(Token::NAME);

        return new NameNode([
            'value' => $token->value,
            'loc'   => $this->loc($token),
        ]);
    }

    /**
     * Implements the parsing rules in the Document section.
     *
     * @throws SyntaxError
     */
    private function parseDocument() : DocumentNode
    {
        $start = $this->lexer->token;

        return new DocumentNode([
            'definitions' => $this->many(
                Token::SOF,
                function () {
                    return $this->parseDefinition();
                },
                Token::EOF
            ),
            'loc'         => $this->loc($start),
        ]);
    }

    /**
     * @return ExecutableDefinitionNode|TypeSystemDefinitionNode
     *
     * @throws SyntaxError
     */
    private function parseDefinition() : DefinitionNode
    {
        if ($this->peek(Token::NAME)) {
            switch ($this->lexer->token->value) {
                case 'query':
                case 'mutation':
                case 'subscription':
                case 'fragment':
                    return $this->parseExecutableDefinition();

                // Note: The schema definition language is an experimental addition.
                case 'schema':
                case 'scalar':
                case 'type':
                case 'interface':
                case 'union':
                case 'enum':
                case 'input':
                case 'extend':
                case 'directive':
                    // Note: The schema definition language is an experimental addition.
                    return $this->parseTypeSystemDefinition();
            }
        } elseif ($this->peek(Token::BRACE_L)) {
            return $this->parseExecutableDefinition();
        } elseif ($this->peekDescription()) {
            // Note: The schema definition language is an experimental addition.
            return $this->parseTypeSystemDefinition();
        }

        throw $this->unexpected();
    }

    /**
     * @throws SyntaxError
     */
    private function parseExecutableDefinition() : ExecutableDefinitionNode
    {
        if ($this->peek(Token::NAME)) {
            switch ($this->lexer->token->value) {
                case 'query':
                case 'mutation':
                case 'subscription':
                    return $this->parseOperationDefinition();
                case 'fragment':
                    return $this->parseFragmentDefinition();
            }
        } elseif ($this->peek(Token::BRACE_L)) {
            return $this->parseOperationDefinition();
        }

        throw $this->unexpected();
    }

    // Implements the parsing rules in the Operations section.

    /**
     * @throws SyntaxError
     */
    private function parseOperationDefinition() : OperationDefinitionNode
    {
        $start = $this->lexer->token;
        if ($this->peek(Token::BRACE_L)) {
            return new OperationDefinitionNode([
                'operation'           => 'query',
                'name'                => null,
                'variableDefinitions' => new NodeList([]),
                'directives'          => new NodeList([]),
                'selectionSet'        => $this->parseSelectionSet(),
                'loc'                 => $this->loc($start),
            ]);
        }

        $operation = $this->parseOperationType();

        $name = null;
        if ($this->peek(Token::NAME)) {
            $name = $this->parseName();
        }

        return new OperationDefinitionNode([
            'operation'           => $operation,
            'name'                => $name,
            'variableDefinitions' => $this->parseVariableDefinitions(),
            'directives'          => $this->parseDirectives(false),
            'selectionSet'        => $this->parseSelectionSet(),
            'loc'                 => $this->loc($start),
        ]);
    }

    /**
     * @throws SyntaxError
     */
    private function parseOperationType() : string
    {
        $operationToken = $this->expect(Token::NAME);
        switch ($operationToken->value) {
            case 'query':
                return 'query';
            case 'mutation':
                return 'mutation';
            case 'subscription':
                return 'subscription';
        }

        throw $this->unexpected($operationToken);
    }

    private function parseVariableDefinitions() : NodeList
    {
        return $this->peek(Token::PAREN_L)
            ? $this->many(
                Token::PAREN_L,
                function () : VariableDefinitionNode {
                    return $this->parseVariableDefinition();
                },
                Token::PAREN_R
            )
            : new NodeList([]);
    }

    /**
     * @throws SyntaxError
     */
    private function parseVariableDefinition() : VariableDefinitionNode
    {
        $start = $this->lexer->token;
        $var   = $this->parseVariable();

        $this->expect(Token::COLON);
        $type = $this->parseTypeReference();

        return new VariableDefinitionNode([
            'variable'     => $var,
            'type'         => $type,
            'defaultValue' =>
                ($this->skip(Token::EQUALS) ? $this->parseValueLiteral(true) : null),
            'directives'   => $this->parseDirectives(true),
            'loc'          => $this->loc($start),
        ]);
    }

    /**
     * @throws SyntaxError
     */
    private function parseVariable() : VariableNode
    {
        $start = $this->lexer->token;
        $this->expect(Token::DOLLAR);

        return new VariableNode([
            'name' => $this->parseName(),
            'loc'  => $this->loc($start),
        ]);
    }

    private function parseSelectionSet() : SelectionSetNode
    {
        $start = $this->lexer->token;

        return new SelectionSetNode(
            [
                'selections' => $this->many(
                    Token::BRACE_L,
                    function () : SelectionNode {
                        return $this->parseSelection();
                    },
                    Token::BRACE_R
                ),
                'loc'        => $this->loc($start),
            ]
        );
    }

    /**
     *  Selection :
     *   - Field
     *   - FragmentSpread
     *   - InlineFragment
     */
    private function parseSelection() : SelectionNode
    {
        return $this->peek(Token::SPREAD)
            ? $this->parseFragment()
            : $this->parseField();
    }

    /**
     * @throws SyntaxError
     */
    private function parseField() : FieldNode
    {
        $start       = $this->lexer->token;
        $nameOrAlias = $this->parseName();

        if ($this->skip(Token::COLON)) {
            $alias = $nameOrAlias;
            $name  = $this->parseName();
        } else {
            $alias = null;
            $name  = $nameOrAlias;
        }

        return new FieldNode([
            'alias'        => $alias,
            'name'         => $name,
            'arguments'    => $this->parseArguments(false),
            'directives'   => $this->parseDirectives(false),
            'selectionSet' => $this->peek(Token::BRACE_L) ? $this->parseSelectionSet() : null,
            'loc'          => $this->loc($start),
        ]);
    }

    /**
     * @throws SyntaxError
     */
    private function parseArguments(bool $isConst) : NodeList
    {
        $parseFn = $isConst
            ? function () : ArgumentNode {
                return $this->parseConstArgument();
            }
            : function () : ArgumentNode {
                return $this->parseArgument();
            };

        return $this->peek(Token::PAREN_L)
            ? $this->many(Token::PAREN_L, $parseFn, Token::PAREN_R)
            : new NodeList([]);
    }

    /**
     * @throws SyntaxError
     */
    private function parseArgument() : ArgumentNode
    {
        $start = $this->lexer->token;
        $name  = $this->parseName();

        $this->expect(Token::COLON);
        $value = $this->parseValueLiteral(false);

        return new ArgumentNode([
            'name'  => $name,
            'value' => $value,
            'loc'   => $this->loc($start),
        ]);
    }

    /**
     * @throws SyntaxError
     */
    private function parseConstArgument() : ArgumentNode
    {
        $start = $this->lexer->token;
        $name  = $this->parseName();

        $this->expect(Token::COLON);
        $value = $this->parseConstValue();

        return new ArgumentNode([
            'name'  => $name,
            'value' => $value,
            'loc'   => $this->loc($start),
        ]);
    }

    // Implements the parsing rules in the Fragments section.

    /**
     * @return FragmentSpreadNode|InlineFragmentNode
     *
     * @throws SyntaxError
     */
    private function parseFragment() : SelectionNode
    {
        $start = $this->lexer->token;
        $this->expect(Token::SPREAD);

        $hasTypeCondition = $this->expectOptionalKeyword('on');
        if (! $hasTypeCondition && $this->peek(Token::NAME)) {
            return new FragmentSpreadNode([
                'name'       => $this->parseFragmentName(),
                'directives' => $this->parseDirectives(false),
                'loc'        => $this->loc($start),
            ]);
        }

        return new InlineFragmentNode([
            'typeCondition' => $hasTypeCondition ? $this->parseNamedType() : null,
            'directives'    => $this->parseDirectives(false),
            'selectionSet'  => $this->parseSelectionSet(),
            'loc'           => $this->loc($start),
        ]);
    }

    /**
     * @throws SyntaxError
     */
    private function parseFragmentDefinition() : FragmentDefinitionNode
    {
        $start = $this->lexer->token;
        $this->expectKeyword('fragment');

        $name = $this->parseFragmentName();

        // Experimental support for defining variables within fragments changes
        // the grammar of FragmentDefinition:
        //   - fragment FragmentName VariableDefinitions? on TypeCondition Directives? SelectionSet
        $variableDefinitions = null;
        if (isset($this->lexer->options['experimentalFragmentVariables'])) {
            $variableDefinitions = $this->parseVariableDefinitions();
        }
        $this->expectKeyword('on');
        $typeCondition = $this->parseNamedType();

        return new FragmentDefinitionNode([
            'name'                => $name,
            'variableDefinitions' => $variableDefinitions,
            'typeCondition'       => $typeCondition,
            'directives'          => $this->parseDirectives(false),
            'selectionSet'        => $this->parseSelectionSet(),
            'loc'                 => $this->loc($start),
        ]);
    }

    /**
     * @throws SyntaxError
     */
    private function parseFragmentName() : NameNode
    {
        if ($this->lexer->token->value === 'on') {
            throw $this->unexpected();
        }

        return $this->parseName();
    }

    // Implements the parsing rules in the Values section.

    /**
     * Value[Const] :
     *   - [~Const] Variable
     *   - IntValue
     *   - FloatValue
     *   - StringValue
     *   - BooleanValue
     *   - NullValue
     *   - EnumValue
     *   - ListValue[?Const]
     *   - ObjectValue[?Const]
     *
     * BooleanValue : one of `true` `false`
     *
     * NullValue : `null`
     *
     * EnumValue : Name but not `true`, `false` or `null`
     *
     * @return BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|StringValueNode|VariableNode|ListValueNode|ObjectValueNode|NullValueNode
     *
     * @throws SyntaxError
     */
    private function parseValueLiteral(bool $isConst) : ValueNode
    {
        $token = $this->lexer->token;
        switch ($token->kind) {
            case Token::BRACKET_L:
                return $this->parseArray($isConst);
            case Token::BRACE_L:
                return $this->parseObject($isConst);
            case Token::INT:
                $this->lexer->advance();

                return new IntValueNode([
                    'value' => $token->value,
                    'loc'   => $this->loc($token),
                ]);
            case Token::FLOAT:
                $this->lexer->advance();

                return new FloatValueNode([
                    'value' => $token->value,
                    'loc'   => $this->loc($token),
                ]);
            case Token::STRING:
            case Token::BLOCK_STRING:
                return $this->parseStringLiteral();
            case Token::NAME:
                if ($token->value === 'true' || $token->value === 'false') {
                    $this->lexer->advance();

                    return new BooleanValueNode([
                        'value' => $token->value === 'true',
                        'loc'   => $this->loc($token),
                    ]);
                }

                if ($token->value === 'null') {
                    $this->lexer->advance();

                    return new NullValueNode([
                        'loc' => $this->loc($token),
                    ]);
                } else {
                    $this->lexer->advance();

                    return new EnumValueNode([
                        'value' => $token->value,
                        'loc'   => $this->loc($token),
                    ]);
                }
                break;

            case Token::DOLLAR:
                if (! $isConst) {
                    return $this->parseVariable();
                }
                break;
        }
        throw $this->unexpected();
    }

    private function parseStringLiteral() : StringValueNode
    {
        $token = $this->lexer->token;
        $this->lexer->advance();

        return new StringValueNode([
            'value' => $token->value,
            'block' => $token->kind === Token::BLOCK_STRING,
            'loc'   => $this->loc($token),
        ]);
    }

    /**
     * @return BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|StringValueNode|VariableNode
     *
     * @throws SyntaxError
     */
    private function parseConstValue() : ValueNode
    {
        return $this->parseValueLiteral(true);
    }

    /**
     * @return BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|ListValueNode|ObjectValueNode|StringValueNode|VariableNode
     */
    private function parseVariableValue() : ValueNode
    {
        return $this->parseValueLiteral(false);
    }

    private function parseArray(bool $isConst) : ListValueNode
    {
        $start   = $this->lexer->token;
        $parseFn = $isConst
            ? function () {
                return $this->parseConstValue();
            }
            : function () {
                return $this->parseVariableValue();
            };

        return new ListValueNode(
            [
                'values' => $this->any(Token::BRACKET_L, $parseFn, Token::BRACKET_R),
                'loc'    => $this->loc($start),
            ]
        );
    }

    private function parseObject(bool $isConst) : ObjectValueNode
    {
        $start = $this->lexer->token;
        $this->expect(Token::BRACE_L);
        $fields = [];
        while (! $this->skip(Token::BRACE_R)) {
            $fields[] = $this->parseObjectField($isConst);
        }

        return new ObjectValueNode([
            'fields' => new NodeList($fields),
            'loc'    => $this->loc($start),
        ]);
    }

    private function parseObjectField(bool $isConst) : ObjectFieldNode
    {
        $start = $this->lexer->token;
        $name  = $this->parseName();

        $this->expect(Token::COLON);

        return new ObjectFieldNode([
            'name'  => $name,
            'value' => $this->parseValueLiteral($isConst),
            'loc'   => $this->loc($start),
        ]);
    }

    // Implements the parsing rules in the Directives section.

    /**
     * @throws SyntaxError
     */
    private function parseDirectives(bool $isConst) : NodeList
    {
        $directives = [];
        while ($this->peek(Token::AT)) {
            $directives[] = $this->parseDirective($isConst);
        }

        return new NodeList($directives);
    }

    /**
     * @throws SyntaxError
     */
    private function parseDirective(bool $isConst) : DirectiveNode
    {
        $start = $this->lexer->token;
        $this->expect(Token::AT);

        return new DirectiveNode([
            'name'      => $this->parseName(),
            'arguments' => $this->parseArguments($isConst),
            'loc'       => $this->loc($start),
        ]);
    }

    // Implements the parsing rules in the Types section.

    /**
     * Handles the Type: TypeName, ListType, and NonNullType parsing rules.
     *
     * @return ListTypeNode|NameNode|NonNullTypeNode
     *
     * @throws SyntaxError
     */
    private function parseTypeReference() : TypeNode
    {
        $start = $this->lexer->token;

        if ($this->skip(Token::BRACKET_L)) {
            $type = $this->parseTypeReference();
            $this->expect(Token::BRACKET_R);
            $type = new ListTypeNode([
                'type' => $type,
                'loc'  => $this->loc($start),
            ]);
        } else {
            $type = $this->parseNamedType();
        }
        if ($this->skip(Token::BANG)) {
            return new NonNullTypeNode([
                'type' => $type,
                'loc'  => $this->loc($start),
            ]);
        }

        return $type;
    }

    private function parseNamedType() : NamedTypeNode
    {
        $start = $this->lexer->token;

        return new NamedTypeNode([
            'name' => $this->parseName(),
            'loc'  => $this->loc($start),
        ]);
    }

    // Implements the parsing rules in the Type Definition section.

    /**
     * TypeSystemDefinition :
     *   - SchemaDefinition
     *   - TypeDefinition
     *   - TypeExtension
     *   - DirectiveDefinition
     *
     * TypeDefinition :
     *   - ScalarTypeDefinition
     *   - ObjectTypeDefinition
     *   - InterfaceTypeDefinition
     *   - UnionTypeDefinition
     *   - EnumTypeDefinition
     *   - InputObjectTypeDefinition
     *
     * @throws SyntaxError
     */
    private function parseTypeSystemDefinition() : TypeSystemDefinitionNode
    {
        // Many definitions begin with a description and require a lookahead.
        $keywordToken = $this->peekDescription()
            ? $this->lexer->lookahead()
            : $this->lexer->token;

        if ($keywordToken->kind === Token::NAME) {
            switch ($keywordToken->value) {
                case 'schema':
                    return $this->parseSchemaDefinition();
                case 'scalar':
                    return $this->parseScalarTypeDefinition();
                case 'type':
                    return $this->parseObjectTypeDefinition();
                case 'interface':
                    return $this->parseInterfaceTypeDefinition();
                case 'union':
                    return $this->parseUnionTypeDefinition();
                case 'enum':
                    return $this->parseEnumTypeDefinition();
                case 'input':
                    return $this->parseInputObjectTypeDefinition();
                case 'extend':
                    return $this->parseTypeExtension();
                case 'directive':
                    return $this->parseDirectiveDefinition();
            }
        }

        throw $this->unexpected($keywordToken);
    }

    private function peekDescription() : bool
    {
        return $this->peek(Token::STRING) || $this->peek(Token::BLOCK_STRING);
    }

    private function parseDescription() : ?StringValueNode
    {
        if ($this->peekDescription()) {
            return $this->parseStringLiteral();
        }

        return null;
    }

    /**
     * @throws SyntaxError
     */
    private function parseSchemaDefinition() : SchemaDefinitionNode
    {
        $start = $this->lexer->token;
        $this->expectKeyword('schema');
        $directives = $this->parseDirectives(true);

        $operationTypes = $this->many(
            Token::BRACE_L,
            function () : OperationTypeDefinitionNode {
                return $this->parseOperationTypeDefinition();
            },
            Token::BRACE_R
        );

        return new SchemaDefinitionNode([
            'directives'     => $directives,
            'operationTypes' => $operationTypes,
            'loc'            => $this->loc($start),
        ]);
    }

    /**
     * @throws SyntaxError
     */
    private function parseOperationTypeDefinition() : OperationTypeDefinitionNode
    {
        $start     = $this->lexer->token;
        $operation = $this->parseOperationType();
        $this->expect(Token::COLON);
        $type = $this->parseNamedType();

        return new OperationTypeDefinitionNode([
            'operation' => $operation,
            'type'      => $type,
            'loc'       => $this->loc($start),
        ]);
    }

    /**
     * @throws SyntaxError
     */
    private function parseScalarTypeDefinition() : ScalarTypeDefinitionNode
    {
        $start       = $this->lexer->token;
        $description = $this->parseDescription();
        $this->expectKeyword('scalar');
        $name       = $this->parseName();
        $directives = $this->parseDirectives(true);

        return new ScalarTypeDefinitionNode([
            'name'        => $name,
            'directives'  => $directives,
            'loc'         => $this->loc($start),
            'description' => $description,
        ]);
    }

    /**
     * @throws SyntaxError
     */
    private function parseObjectTypeDefinition() : ObjectTypeDefinitionNode
    {
        $start       = $this->lexer->token;
        $description = $this->parseDescription();
        $this->expectKeyword('type');
        $name       = $this->parseName();
        $interfaces = $this->parseImplementsInterfaces();
        $directives = $this->parseDirectives(true);
        $fields     = $this->parseFieldsDefinition();

        return new ObjectTypeDefinitionNode([
            'name'        => $name,
            'interfaces'  => $interfaces,
            'directives'  => $directives,
            'fields'      => $fields,
            'loc'         => $this->loc($start),
            'description' => $description,
        ]);
    }

    /**
     * ImplementsInterfaces :
     *   - implements `&`? NamedType
     *   - ImplementsInterfaces & NamedType
     *
     * @return NamedTypeNode[]
     */
    private function parseImplementsInterfaces() : array
    {
        $types = [];
        if ($this->expectOptionalKeyword('implements')) {
            // Optional leading ampersand
            $this->skip(Token::AMP);
            do {
                $types[] = $this->parseNamedType();
            } while ($this->skip(Token::AMP) ||
                // Legacy support for the SDL?
                (($this->lexer->options['allowLegacySDLImplementsInterfaces'] ?? false) && $this->peek(Token::NAME))
            );
        }

        return $types;
    }

    /**
     * @throws SyntaxError
     */
    private function parseFieldsDefinition() : NodeList
    {
        // Legacy support for the SDL?
        if (($this->lexer->options['allowLegacySDLEmptyFields'] ?? false)
            && $this->peek(Token::BRACE_L)
            && $this->lexer->lookahead()->kind === Token::BRACE_R
        ) {
            $this->lexer->advance();
            $this->lexer->advance();

            /** @phpstan-var NodeList<FieldDefinitionNode&Node> $nodeList */
            $nodeList = new NodeList([]);
        } else {
            /** @phpstan-var NodeList<FieldDefinitionNode&Node> $nodeList */
            $nodeList = $this->peek(Token::BRACE_L)
                ? $this->many(
                    Token::BRACE_L,
                    function () : FieldDefinitionNode {
                        return $this->parseFieldDefinition();
                    },
                    Token::BRACE_R
                )
                : new NodeList([]);
        }

        return $nodeList;
    }

    /**
     * @throws SyntaxError
     */
    private function parseFieldDefinition() : FieldDefinitionNode
    {
        $start       = $this->lexer->token;
        $description = $this->parseDescription();
        $name        = $this->parseName();
        $args        = $this->parseArgumentsDefinition();
        $this->expect(Token::COLON);
        $type       = $this->parseTypeReference();
        $directives = $this->parseDirectives(true);

        return new FieldDefinitionNode([
            'name'        => $name,
            'arguments'   => $args,
            'type'        => $type,
            'directives'  => $directives,
            'loc'         => $this->loc($start),
            'description' => $description,
        ]);
    }

    /**
     * @throws SyntaxError
     */
    private function parseArgumentsDefinition() : NodeList
    {
        /** @var NodeList<InputValueDefinitionNode&Node> $nodeList */
        $nodeList = $this->peek(Token::PAREN_L)
            ? $this->many(
                Token::PAREN_L,
                function () : InputValueDefinitionNode {
                    return $this->parseInputValueDefinition();
                },
                Token::PAREN_R
            )
            : new NodeList([]);

        return $nodeList;
    }

    /**
     * @throws SyntaxError
     */
    private function parseInputValueDefinition() : InputValueDefinitionNode
    {
        $start       = $this->lexer->token;
        $description = $this->parseDescription();
        $name        = $this->parseName();
        $this->expect(Token::COLON);
        $type         = $this->parseTypeReference();
        $defaultValue = null;
        if ($this->skip(Token::EQUALS)) {
            $defaultValue = $this->parseConstValue();
        }
        $directives = $this->parseDirectives(true);

        return new InputValueDefinitionNode([
            'name'         => $name,
            'type'         => $type,
            'defaultValue' => $defaultValue,
            'directives'   => $directives,
            'loc'          => $this->loc($start),
            'description'  => $description,
        ]);
    }

    /**
     * @throws SyntaxError
     */
    private function parseInterfaceTypeDefinition() : InterfaceTypeDefinitionNode
    {
        $start       = $this->lexer->token;
        $description = $this->parseDescription();
        $this->expectKeyword('interface');
        $name       = $this->parseName();
        $directives = $this->parseDirectives(true);
        $fields     = $this->parseFieldsDefinition();

        return new InterfaceTypeDefinitionNode([
            'name'        => $name,
            'directives'  => $directives,
            'fields'      => $fields,
            'loc'         => $this->loc($start),
            'description' => $description,
        ]);
    }

    /**
     * UnionTypeDefinition :
     *   - Description? union Name Directives[Const]? UnionMemberTypes?
     *
     * @throws SyntaxError
     */
    private function parseUnionTypeDefinition() : UnionTypeDefinitionNode
    {
        $start       = $this->lexer->token;
        $description = $this->parseDescription();
        $this->expectKeyword('union');
        $name       = $this->parseName();
        $directives = $this->parseDirectives(true);
        $types      = $this->parseUnionMemberTypes();

        return new UnionTypeDefinitionNode([
            'name'        => $name,
            'directives'  => $directives,
            'types'       => $types,
            'loc'         => $this->loc($start),
            'description' => $description,
        ]);
    }

    /**
     * UnionMemberTypes :
     *   - = `|`? NamedType
     *   - UnionMemberTypes | NamedType
     *
     * @return NamedTypeNode[]
     */
    private function parseUnionMemberTypes() : array
    {
        $types = [];
        if ($this->skip(Token::EQUALS)) {
            // Optional leading pipe
            $this->skip(Token::PIPE);
            do {
                $types[] = $this->parseNamedType();
            } while ($this->skip(Token::PIPE));
        }

        return $types;
    }

    /**
     * @throws SyntaxError
     */
    private function parseEnumTypeDefinition() : EnumTypeDefinitionNode
    {
        $start       = $this->lexer->token;
        $description = $this->parseDescription();
        $this->expectKeyword('enum');
        $name       = $this->parseName();
        $directives = $this->parseDirectives(true);
        $values     = $this->parseEnumValuesDefinition();

        return new EnumTypeDefinitionNode([
            'name'        => $name,
            'directives'  => $directives,
            'values'      => $values,
            'loc'         => $this->loc($start),
            'description' => $description,
        ]);
    }

    /**
     * @throws SyntaxError
     */
    private function parseEnumValuesDefinition() : NodeList
    {
        /** @var NodeList<EnumValueDefinitionNode&Node> $nodeList */
        $nodeList = $this->peek(Token::BRACE_L)
            ? $this->many(
                Token::BRACE_L,
                function () : EnumValueDefinitionNode {
                    return $this->parseEnumValueDefinition();
                },
                Token::BRACE_R
            )
            : new NodeList([]);

        return $nodeList;
    }

    /**
     * @throws SyntaxError
     */
    private function parseEnumValueDefinition() : EnumValueDefinitionNode
    {
        $start       = $this->lexer->token;
        $description = $this->parseDescription();
        $name        = $this->parseName();
        $directives  = $this->parseDirectives(true);

        return new EnumValueDefinitionNode([
            'name'        => $name,
            'directives'  => $directives,
            'loc'         => $this->loc($start),
            'description' => $description,
        ]);
    }

    /**
     * @throws SyntaxError
     */
    private function parseInputObjectTypeDefinition() : InputObjectTypeDefinitionNode
    {
        $start       = $this->lexer->token;
        $description = $this->parseDescription();
        $this->expectKeyword('input');
        $name       = $this->parseName();
        $directives = $this->parseDirectives(true);
        $fields     = $this->parseInputFieldsDefinition();

        return new InputObjectTypeDefinitionNode([
            'name'        => $name,
            'directives'  => $directives,
            'fields'      => $fields,
            'loc'         => $this->loc($start),
            'description' => $description,
        ]);
    }

    /**
     * @throws SyntaxError
     */
    private function parseInputFieldsDefinition() : NodeList
    {
        /** @var NodeList<InputValueDefinitionNode&Node> $nodeList */
        $nodeList = $this->peek(Token::BRACE_L)
            ? $this->many(
                Token::BRACE_L,
                function () : InputValueDefinitionNode {
                    return $this->parseInputValueDefinition();
                },
                Token::BRACE_R
            )
            : new NodeList([]);

        return $nodeList;
    }

    /**
     * TypeExtension :
     *   - ScalarTypeExtension
     *   - ObjectTypeExtension
     *   - InterfaceTypeExtension
     *   - UnionTypeExtension
     *   - EnumTypeExtension
     *   - InputObjectTypeDefinition
     *
     * @throws SyntaxError
     */
    private function parseTypeExtension() : TypeExtensionNode
    {
        $keywordToken = $this->lexer->lookahead();

        if ($keywordToken->kind === Token::NAME) {
            switch ($keywordToken->value) {
                case 'schema':
                    return $this->parseSchemaTypeExtension();
                case 'scalar':
                    return $this->parseScalarTypeExtension();
                case 'type':
                    return $this->parseObjectTypeExtension();
                case 'interface':
                    return $this->parseInterfaceTypeExtension();
                case 'union':
                    return $this->parseUnionTypeExtension();
                case 'enum':
                    return $this->parseEnumTypeExtension();
                case 'input':
                    return $this->parseInputObjectTypeExtension();
            }
        }

        throw $this->unexpected($keywordToken);
    }

    /**
     * @throws SyntaxError
     */
    private function parseSchemaTypeExtension() : SchemaTypeExtensionNode
    {
        $start = $this->lexer->token;
        $this->expectKeyword('extend');
        $this->expectKeyword('schema');
        $directives     = $this->parseDirectives(true);
        $operationTypes = $this->peek(Token::BRACE_L)
            ? $this->many(
                Token::BRACE_L,
                [$this, 'parseOperationTypeDefinition'],
                Token::BRACE_R
            )
            : [];
        if (count($directives) === 0 && count($operationTypes) === 0) {
            $this->unexpected();
        }

        return new SchemaTypeExtensionNode([
            'directives' => $directives,
            'operationTypes' => $operationTypes,
            'loc' => $this->loc($start),
        ]);
    }

    /**
     * @throws SyntaxError
     */
    private function parseScalarTypeExtension() : ScalarTypeExtensionNode
    {
        $start = $this->lexer->token;
        $this->expectKeyword('extend');
        $this->expectKeyword('scalar');
        $name       = $this->parseName();
        $directives = $this->parseDirectives(true);
        if (count($directives) === 0) {
            throw $this->unexpected();
        }

        return new ScalarTypeExtensionNode([
            'name'       => $name,
            'directives' => $directives,
            'loc'        => $this->loc($start),
        ]);
    }

    /**
     * @throws SyntaxError
     */
    private function parseObjectTypeExtension() : ObjectTypeExtensionNode
    {
        $start = $this->lexer->token;
        $this->expectKeyword('extend');
        $this->expectKeyword('type');
        $name       = $this->parseName();
        $interfaces = $this->parseImplementsInterfaces();
        $directives = $this->parseDirectives(true);
        $fields     = $this->parseFieldsDefinition();

        if (count($interfaces) === 0 &&
            count($directives) === 0 &&
            count($fields) === 0
        ) {
            throw $this->unexpected();
        }

        return new ObjectTypeExtensionNode([
            'name'       => $name,
            'interfaces' => $interfaces,
            'directives' => $directives,
            'fields'     => $fields,
            'loc'        => $this->loc($start),
        ]);
    }

    /**
     * @throws SyntaxError
     */
    private function parseInterfaceTypeExtension() : InterfaceTypeExtensionNode
    {
        $start = $this->lexer->token;
        $this->expectKeyword('extend');
        $this->expectKeyword('interface');
        $name       = $this->parseName();
        $directives = $this->parseDirectives(true);
        $fields     = $this->parseFieldsDefinition();
        if (count($directives) === 0 &&
            count($fields) === 0
        ) {
            throw $this->unexpected();
        }

        return new InterfaceTypeExtensionNode([
            'name'       => $name,
            'directives' => $directives,
            'fields'     => $fields,
            'loc'        => $this->loc($start),
        ]);
    }

    /**
     * UnionTypeExtension :
     *   - extend union Name Directives[Const]? UnionMemberTypes
     *   - extend union Name Directives[Const]
     *
     * @throws SyntaxError
     */
    private function parseUnionTypeExtension() : UnionTypeExtensionNode
    {
        $start = $this->lexer->token;
        $this->expectKeyword('extend');
        $this->expectKeyword('union');
        $name       = $this->parseName();
        $directives = $this->parseDirectives(true);
        $types      = $this->parseUnionMemberTypes();
        if (count($directives) === 0 && count($types) === 0) {
            throw $this->unexpected();
        }

        return new UnionTypeExtensionNode([
            'name'       => $name,
            'directives' => $directives,
            'types'      => $types,
            'loc'        => $this->loc($start),
        ]);
    }

    /**
     * @throws SyntaxError
     */
    private function parseEnumTypeExtension() : EnumTypeExtensionNode
    {
        $start = $this->lexer->token;
        $this->expectKeyword('extend');
        $this->expectKeyword('enum');
        $name       = $this->parseName();
        $directives = $this->parseDirectives(true);
        $values     = $this->parseEnumValuesDefinition();
        if (count($directives) === 0 &&
            count($values) === 0
        ) {
            throw $this->unexpected();
        }

        return new EnumTypeExtensionNode([
            'name'       => $name,
            'directives' => $directives,
            'values'     => $values,
            'loc'        => $this->loc($start),
        ]);
    }

    /**
     * @throws SyntaxError
     */
    private function parseInputObjectTypeExtension() : InputObjectTypeExtensionNode
    {
        $start = $this->lexer->token;
        $this->expectKeyword('extend');
        $this->expectKeyword('input');
        $name       = $this->parseName();
        $directives = $this->parseDirectives(true);
        $fields     = $this->parseInputFieldsDefinition();
        if (count($directives) === 0 &&
            count($fields) === 0
        ) {
            throw $this->unexpected();
        }

        return new InputObjectTypeExtensionNode([
            'name'       => $name,
            'directives' => $directives,
            'fields'     => $fields,
            'loc'        => $this->loc($start),
        ]);
    }

    /**
     * DirectiveDefinition :
     *   - Description? directive @ Name ArgumentsDefinition? `repeatable`? on DirectiveLocations
     *
     * @throws SyntaxError
     */
    private function parseDirectiveDefinition() : DirectiveDefinitionNode
    {
        $start       = $this->lexer->token;
        $description = $this->parseDescription();
        $this->expectKeyword('directive');
        $this->expect(Token::AT);
        $name       = $this->parseName();
        $args       = $this->parseArgumentsDefinition();
        $repeatable = $this->expectOptionalKeyword('repeatable');
        $this->expectKeyword('on');
        $locations = $this->parseDirectiveLocations();

        return new DirectiveDefinitionNode([
            'name'        => $name,
            'description' => $description,
            'arguments'   => $args,
            'repeatable'  => $repeatable,
            'locations'   => $locations,
            'loc'         => $this->loc($start),
        ]);
    }

    /**
     * @return NameNode[]
     *
     * @throws SyntaxError
     */
    private function parseDirectiveLocations() : array
    {
        // Optional leading pipe
        $this->skip(Token::PIPE);
        $locations = [];
        do {
            $locations[] = $this->parseDirectiveLocation();
        } while ($this->skip(Token::PIPE));

        return $locations;
    }

    /**
     * @throws SyntaxError
     */
    private function parseDirectiveLocation() : NameNode
    {
        $start = $this->lexer->token;
        $name  = $this->parseName();
        if (DirectiveLocation::has($name->value)) {
            return $name;
        }

        throw $this->unexpected($start);
    }
}
