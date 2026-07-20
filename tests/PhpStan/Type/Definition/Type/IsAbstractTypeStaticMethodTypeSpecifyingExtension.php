<?php declare(strict_types=1);

namespace GraphQL\Tests\PhpStan\Type\Definition\Type;

use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\Type;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierAwareExtension;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StaticMethodTypeSpecifyingExtension;

final class IsAbstractTypeStaticMethodTypeSpecifyingExtension implements StaticMethodTypeSpecifyingExtension, TypeSpecifierAwareExtension
{
    private TypeSpecifier $typeSpecifier;

    public function getClass(): string
    {
        return Type::class;
    }

    public function setTypeSpecifier(TypeSpecifier $typeSpecifier): void
    {
        $this->typeSpecifier = $typeSpecifier;
    }

    public function isStaticMethodSupported(MethodReflection $staticMethodReflection, StaticCall $node, TypeSpecifierContext $context): bool
    {
        // The $context argument tells us if we're in an if condition or not (as in this case).
        return $staticMethodReflection->getName() === 'isAbstractType' && ! $context->null();
    }

    public function specifyTypes(MethodReflection $staticMethodReflection, StaticCall $node, Scope $scope, TypeSpecifierContext $context): SpecifiedTypes
    {
        return $this->typeSpecifier->create($node->getArgs()[0]->value, new ObjectType(AbstractType::class), $context, $scope);
    }
}
