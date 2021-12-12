<?php

declare(strict_types=1);

namespace GraphQL\Validator;

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
use GraphQL\Validator\Rules\UniqueEnumValueNames;
use GraphQL\Validator\Rules\UniqueFragmentNames;
use GraphQL\Validator\Rules\UniqueInputFieldNames;
use GraphQL\Validator\Rules\UniqueOperationNames;
use GraphQL\Validator\Rules\UniqueOperationTypes;
use GraphQL\Validator\Rules\UniqueVariableNames;
use GraphQL\Validator\Rules\ValidationRule;
use GraphQL\Validator\Rules\ValuesOfCorrectType;
use GraphQL\Validator\Rules\VariablesAreInputTypes;
use GraphQL\Validator\Rules\VariablesInAllowedPosition;

use function array_merge;
use function count;
use function get_class;

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
    /** @var array<class-string<ValidationRule>, ValidationRule> */
    private static array $rules = [];

    /** @var array<class-string<ValidationRule>, ValidationRule> */
    private static array $defaultRules;

    /** @var array<class-string<QuerySecurityRule>, QuerySecurityRule> */
    private static array $securityRules;

    /** @var array<class-string<ValidationRule>, ValidationRule> */
    private static array $sdlRules;

    private static bool $initRules = false;

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
            self::$rules     = array_merge(static::defaultRules(), self::securityRules(), self::$rules);
            self::$initRules = true;
        }

        return self::$rules;
    }

    /**
     * @return array<class-string<ValidationRule>, ValidationRule>
     */
    public static function defaultRules(): array
    {
        return self::$defaultRules ??= [
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

    /**
     * @return array<class-string<QuerySecurityRule>, QuerySecurityRule>
     */
    public static function securityRules(): array
    {
        // This way of defining rules is deprecated
        // When custom security rule is required - it should be just added via DocumentValidator::addRule();
        // TODO: deprecate this

        return self::$securityRules ??= [
            DisableIntrospection::class => new DisableIntrospection(DisableIntrospection::DISABLED), // DEFAULT DISABLED
            QueryDepth::class           => new QueryDepth(QueryDepth::DISABLED), // default disabled
            QueryComplexity::class      => new QueryComplexity(QueryComplexity::DISABLED), // default disabled
        ];
    }

    /**
     * @return array<class-string<ValidationRule>, ValidationRule>
     */
    public static function sdlRules(): array
    {
        return self::$sdlRules ??= [
            LoneSchemaDefinition::class                  => new LoneSchemaDefinition(),
            UniqueOperationTypes::class                  => new UniqueOperationTypes(),
            KnownDirectives::class                       => new KnownDirectives(),
            KnownArgumentNamesOnDirectives::class        => new KnownArgumentNamesOnDirectives(),
            UniqueDirectivesPerLocation::class           => new UniqueDirectivesPerLocation(),
            UniqueArgumentNames::class                   => new UniqueArgumentNames(),
            UniqueEnumValueNames::class                  => new UniqueEnumValueNames(),
            UniqueInputFieldNames::class                 => new UniqueInputFieldNames(),
            ProvidedRequiredArgumentsOnDirectives::class => new ProvidedRequiredArgumentsOnDirectives(),
        ];
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
     * @param class-string<T> $name
     *
     * @return T|null
     *
     * @template T of ValidationRule
     * @api
     */
    public static function getRule(string $name): ?ValidationRule
    {
        // @phpstan-ignore-next-line class-strings are always mapped to a matching class instance
        return static::allRules()[$name] ?? null;
    }

    /**
     * Add rule to list of global validation rules.
     *
     * @api
     */
    public static function addRule(ValidationRule $rule): void
    {
        self::$rules[get_class($rule)] = $rule;
    }

    /**
     * @param array<ValidationRule>|null $rules
     *
     * @return array<int, Error>
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
     * @param array<Error> $errors
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
