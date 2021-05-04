<?php

declare(strict_types=1);

namespace GraphQL\Examples\Blog;

use Closure;
use Exception;
use GraphQL\Examples\Blog\Type\CommentType;
use GraphQL\Examples\Blog\Type\Enum\ContentFormatEnum;
use GraphQL\Examples\Blog\Type\Enum\ImageSizeEnumType;
use GraphQL\Examples\Blog\Type\Field\HtmlField;
use GraphQL\Examples\Blog\Type\ImageType;
use GraphQL\Examples\Blog\Type\NodeType;
use GraphQL\Examples\Blog\Type\Scalar\EmailType;
use GraphQL\Examples\Blog\Type\Scalar\UrlType;
use GraphQL\Examples\Blog\Type\SearchResultType;
use GraphQL\Examples\Blog\Type\StoryType;
use GraphQL\Examples\Blog\Type\UserType;
use GraphQL\Type\Definition\BooleanType;
use GraphQL\Type\Definition\FloatType;
use GraphQL\Type\Definition\IDType;
use GraphQL\Type\Definition\IntType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Definition\Type;

use function class_exists;
use function count;
use function explode;
use function lcfirst;
use function method_exists;
use function preg_replace;
use function strtolower;

/**
 * Acts as a registry and factory for your types.
 *
 * As simplistic as possible for the sake of clarity of this example.
 * Your own may be more dynamic (or even code-generated).
 */
class Types
{
    /** @var array<string, Type> */
    private static array $types = [];

    public static function user(): callable
    {
        return static::get(UserType::class);
    }

    public static function story(): callable
    {
        return static::get(StoryType::class);
    }

    public static function comment(): callable
    {
        return static::get(CommentType::class);
    }

    public static function image(): callable
    {
        return static::get(ImageType::class);
    }

    public static function node(): callable
    {
        return static::get(NodeType::class);
    }

    public static function mention(): callable
    {
        return static::get(SearchResultType::class);
    }

    public static function imageSizeEnum(): callable
    {
        return static::get(ImageSizeEnumType::class);
    }

    public static function contentFormatEnum(): callable
    {
        return static::get(ContentFormatEnum::class);
    }

    public static function email(): callable
    {
        return static::get(EmailType::class);
    }

    public static function url(): callable
    {
        return static::get(UrlType::class);
    }

    /**
     * @return Closure(): Type
     */
    public static function get(string $classname): Closure
    {
        return static fn () => static::byClassName($classname);
    }

    protected static function byClassName(string $classname): Type
    {
        $parts = explode('\\', $classname);

        $cacheName = strtolower(preg_replace('~Type$~', '', $parts[count($parts) - 1]));
        $type      = null;

        if (! isset(self::$types[$cacheName])) {
            if (class_exists($classname)) {
                $type = new $classname();
            }

            self::$types[$cacheName] = $type;
        }

        $type = self::$types[$cacheName];

        if (! $type) {
            throw new Exception('Unknown graphql type: ' . $classname);
        }

        return $type;
    }

    public static function byTypeName(string $shortName, bool $removeType = true): Type
    {
        $cacheName = strtolower($shortName);
        $type      = null;

        if (isset(self::$types[$cacheName])) {
            return self::$types[$cacheName];
        }

        $method = lcfirst($shortName);
        if (method_exists(static::class, $method)) {
            $type = self::{$method}();
        }

        if (! $type) {
            throw new Exception('Unknown graphql type: ' . $shortName);
        }

        return $type;
    }

    /**
     * @return array<string, mixed>
     */
    public static function htmlField(string $name, ?string $objectKey = null): array
    {
        return HtmlField::build($name, $objectKey);
    }

    // Let's add internal types as well for consistent experience

    public static function boolean(): BooleanType
    {
        return Type::boolean();
    }

    public static function float(): FloatType
    {
        return Type::float();
    }

    public static function id(): IDType
    {
        return Type::id();
    }

    public static function int(): IntType
    {
        return Type::int();
    }

    public static function string(): StringType
    {
        return Type::string();
    }

    public static function listOf(Type $type): ListOfType
    {
        return new ListOfType($type);
    }

    public static function nonNull(Type $type): NonNull
    {
        return new NonNull($type);
    }
}
