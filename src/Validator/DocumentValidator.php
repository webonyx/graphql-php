<?php

declare(strict_types=1);

namespace GraphQL\Validator;

use Exception;
use GraphQL\Error\Error;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Visitor;
use GraphQL\Type\Schema;
use GraphQL\Utils\TypeInfo;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\ExecutableDefinitions;
use GraphQL\Validator\Rules\FieldsOnCorrectType;
use GraphQL\Validator\Rules\FragmentsOnCompositeTypes;
use GraphQL\Validator\Rules\KnownArgumentNames;
use GraphQL\Validator\Rules\KnownArgumentNamesOnDirectives;
use GraphQL\Validator\Rules\KnownDirectives;
use GraphQL\Validator\Rules\KnownFragmentNames;
use GraphQL\Validator\Rules\KnownTypeNames;
use GraphQL\Validator\Rules\LoneAnonymousOperation;
use GraphQL\Validator\Rules\LoneSchemaDefinition;
use GraphQL\Validator\Rules\NoFragmentCycles;
use GraphQL\Validator\Rules\NoUndefinedVariables;
use GraphQL\Validator\Rules\NoUnusedFragments;
use GraphQL\Validator\Rules\NoUnusedVariables;
use GraphQL\Validator\Rules\OverlappingFieldsCanBeMerged;
use GraphQL\Validator\Rules\PossibleFragmentSpreads;
use GraphQL\Validator\Rules\ProvidedRequiredArguments;
use GraphQL\Validator\Rules\ProvidedRequiredArgumentsOnDirectives;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use GraphQL\Validator\Rules\QuerySecurityRule;
use GraphQL\Validator\Rules\ScalarLeafs;
use GraphQL\Validator\Rules\SingleFieldSubscription;
use GraphQL\Validator\Rules\UniqueArgumentNames;
use GraphQL\Validator\Rules\UniqueDirectivesPerLocation;
use GraphQL\Validator\Rules\UniqueFragmentNames;
use GraphQL\Validator\Rules\UniqueInputFieldNames;
use GraphQL\Validator\Rules\UniqueOperationNames;
use GraphQL\Validator\Rules\UniqueVariableNames;
use GraphQL\Validator\Rules\ValidationRule;
use GraphQL\Validator\Rules\ValuesOfCorrectType;
use GraphQL\Validator\Rules\VariablesAreInputTypes;
use GraphQL\Validator\Rules\VariablesInAllowedPosition;
use Throwable;

use function array_filter;
use function array_merge;
use function count;
use function is_array;
use function sprintf;

/**
 * Implements the "Validation" section of the spec.
 *
 * Validation runs synchronously, returning an array of encountered errors, or
 * an empty array if no errors were encountered and the document is valid.
 *
 * A list of specific validation rules may be provided. If not provided, the
 * default list of rules defined by the GraphQL specification will be used.
 *
 * Each validation rule is an instance of GraphQL\Validator\Rules\ValidationRule
 * which returns a visitor (see the [GraphQL\Language\Visitor API](class-reference.md#graphqllanguagevisitor)).
 *
 * Visitor methods are expected to return an instance of [GraphQL\Error\Error](class-reference.md#graphqlerrorerror),
 * or array of such instances when invalid.
 *
 * Optionally a custom TypeInfo instance may be provided. If not provided, one
 * will be created from the provided schema.
 */
class DocumentValidator
{
    /** @var ValidationRule[] */
    private static $rules = [];

    /** @var ValidationRule[]|null */
    private static $defaultRules;

    /** @var QuerySecurityRule[]|null */
    private static $securityRules;

    /** @var ValidationRule[]|null */
    private static $sdlRules;

    /** @var bool */
    private static $initRules = false;

    /**
     * Primary method for query validation. See class description for details.
     *
     * @param array<ValidationRule>|null $rules
     *
     * @return array<int, Error>
     *
     * @api
     */
    public static function validate(
        Schema $schema,
        DocumentNode $ast,
        ?array $rules = null,
        ?TypeInfo $typeInfo = null
    ): array {
        if ($rules === null) {
            $rules = static::allRules();
        }

        if (count($rules) === 0) {
            // Skip validation if there are no rules
            return [];
        }

        $typeInfo ??= new TypeInfo($schema);

        return static::visitUsingRules($schema, $typeInfo, $ast, $rules);
    }

    /**
     * Returns all global validation rules.
     *
     * @return array<class-string<ValidationRule>, ValidationRule>
     *
     * @api
     */
    public static function allRules(): array
    {
        if (! self::$initRules) {
            static::$rules     = array_merge(static::defaultRules(), self::securityRules(), self::$rules);
            static::$initRules = true;
        }

        return self::$rules;
    }

    /**
     * @return array<class-string<ValidationRule>, ValidationRule>
     */
    public static function defaultRules(): array
    {
        if (self::$defaultRules === null) {
            self::$defaultRules = [
                ExecutableDefinitions::class        => new ExecutableDefinitions(),
                UniqueOperationNames::class         => new UniqueOperationNames(),
                LoneAnonymousOperation::class       => new LoneAnonymousOperation(),
                SingleFieldSubscription::class      => new SingleFieldSubscription(),
                KnownTypeNames::class               => new KnownTypeNames(),
                FragmentsOnCompositeTypes::class    => new FragmentsOnCompositeTypes(),
                VariablesAreInputTypes::class       => new VariablesAreInputTypes(),
                ScalarLeafs::class                  => new ScalarLeafs(),
                FieldsOnCorrectType::class          => new FieldsOnCorrectType(),
                UniqueFragmentNames::class          => new UniqueFragmentNames(),
                KnownFragmentNames::class           => new KnownFragmentNames(),
                NoUnusedFragments::class            => new NoUnusedFragments(),
                PossibleFragmentSpreads::class      => new PossibleFragmentSpreads(),
                NoFragmentCycles::class             => new NoFragmentCycles(),
                UniqueVariableNames::class          => new UniqueVariableNames(),
                NoUndefinedVariables::class         => new NoUndefinedVariables(),
                NoUnusedVariables::class            => new NoUnusedVariables(),
                KnownDirectives::class              => new KnownDirectives(),
                UniqueDirectivesPerLocation::class  => new UniqueDirectivesPerLocation(),
                KnownArgumentNames::class           => new KnownArgumentNames(),
                UniqueArgumentNames::class          => new UniqueArgumentNames(),
                ValuesOfCorrectType::class          => new ValuesOfCorrectType(),
                ProvidedRequiredArguments::class    => new ProvidedRequiredArguments(),
                VariablesInAllowedPosition::class   => new VariablesInAllowedPosition(),
                OverlappingFieldsCanBeMerged::class => new OverlappingFieldsCanBeMerged(),
                UniqueInputFieldNames::class        => new UniqueInputFieldNames(),
            ];
        }

        return self::$defaultRules;
    }

    /**
     * @return QuerySecurityRule[]
     */
    public static function securityRules(): array
    {
        // This way of defining rules is deprecated
        // When custom security rule is required - it should be just added via DocumentValidator::addRule();
        // TODO: deprecate this

        if (self::$securityRules === null) {
            self::$securityRules = [
                DisableIntrospection::class => new DisableIntrospection(DisableIntrospection::DISABLED), // DEFAULT DISABLED
                QueryDepth::class           => new QueryDepth(QueryDepth::DISABLED), // default disabled
                QueryComplexity::class      => new QueryComplexity(QueryComplexity::DISABLED), // default disabled
            ];
        }

        return self::$securityRules;
    }

    public static function sdlRules()
    {
        if (self::$sdlRules === null) {
            self::$sdlRules = [
                LoneSchemaDefinition::class                  => new LoneSchemaDefinition(),
                KnownDirectives::class                       => new KnownDirectives(),
                KnownArgumentNamesOnDirectives::class        => new KnownArgumentNamesOnDirectives(),
                UniqueDirectivesPerLocation::class           => new UniqueDirectivesPerLocation(),
                UniqueArgumentNames::class                   => new UniqueArgumentNames(),
                UniqueInputFieldNames::class                 => new UniqueInputFieldNames(),
                ProvidedRequiredArgumentsOnDirectives::class => new ProvidedRequiredArgumentsOnDirectives(),
            ];
        }

        return self::$sdlRules;
    }

    /**
     * This uses a specialized visitor which runs multiple visitors in parallel,
     * while maintaining the visitor skip and break API.
     *
     * @param array<ValidationRule> $rules
     *
     * @return array<int, Error>
     */
    public static function visitUsingRules(Schema $schema, TypeInfo $typeInfo, DocumentNode $documentNode, array $rules): array
    {
        $context  = new ValidationContext($schema, $documentNode, $typeInfo);
        $visitors = [];
        foreach ($rules as $rule) {
            $visitors[] = $rule->getVisitor($context);
        }

        Visitor::visit($documentNode, Visitor::visitWithTypeInfo($typeInfo, Visitor::visitInParallel($visitors)));

        return $context->getErrors();
    }

    /**
     * Returns global validation rule by name. Standard rules are named by class name, so
     * example usage for such rules:
     *
     * $rule = DocumentValidator::getRule(GraphQL\Validator\Rules\QueryComplexity::class);
     *
     * @param string $name
     *
     * @api
     */
    public static function getRule($name): ?ValidationRule
    {
        $rules = static::allRules();

        if (isset($rules[$name])) {
            return $rules[$name];
        }

        $name = sprintf('GraphQL\\Validator\\Rules\\%s', $name);

        return $rules[$name] ?? null;
    }

    /**
     * Add rule to list of global validation rules
     *
     * @api
     */
    public static function addRule(ValidationRule $rule): void
    {
        self::$rules[$rule->getName()] = $rule;
    }

    public static function isError($value)
    {
        return is_array($value)
            ? count(array_filter(
                $value,
                static function ($item): bool {
                    return $item instanceof Throwable;
                }
            )) === count($value)
            : $value instanceof Throwable;
    }

    public static function append(&$arr, $items)
    {
        if (is_array($items)) {
            $arr = array_merge($arr, $items);
        } else {
            $arr[] = $items;
        }

        return $arr;
    }

    /**
     * @param ValidationRule[]|null $rules
     *
     * @return Error[]
     *
     * @throws Exception
     */
    public static function validateSDL(
        DocumentNode $documentAST,
        ?Schema $schemaToExtend = null,
        ?array $rules = null
    ): array {
        $usedRules = $rules ?? self::sdlRules();
        $context   = new SDLValidationContext($documentAST, $schemaToExtend);
        $visitors  = [];
        foreach ($usedRules as $rule) {
            $visitors[] = $rule->getSDLVisitor($context);
        }

        Visitor::visit($documentAST, Visitor::visitInParallel($visitors));

        return $context->getErrors();
    }

    public static function assertValidSDL(DocumentNode $documentAST): void
    {
        $errors = self::validateSDL($documentAST);
        if (count($errors) > 0) {
            throw new Error(self::combineErrorMessages($errors));
        }
    }

    public static function assertValidSDLExtension(DocumentNode $documentAST, Schema $schema): void
    {
        $errors = self::validateSDL($documentAST, $schema);
        if (count($errors) > 0) {
            throw new Error(self::combineErrorMessages($errors));
        }
    }

    /**
     * @param Error[] $errors
     */
    private static function combineErrorMessages(array $errors): string
    {
        $str = '';
        foreach ($errors as $error) {
            $str .= $error->getMessage() . "\n\n";
        }

        return $str;
    }
}
