<?php
namespace GraphQL\Examples\Blog;

use GraphQL\Examples\Blog\Type\CommentType;
use GraphQL\Examples\Blog\Type\Enum\ContentFormatEnum;
use GraphQL\Examples\Blog\Type\Enum\ImageSizeEnumType;
use GraphQL\Examples\Blog\Type\Field\HtmlField;
use GraphQL\Examples\Blog\Type\SearchResultType;
use GraphQL\Examples\Blog\Type\NodeType;
use GraphQL\Examples\Blog\Type\QueryType;
use GraphQL\Examples\Blog\Type\Scalar\EmailType;
use GraphQL\Examples\Blog\Type\StoryType;
use GraphQL\Examples\Blog\Type\Scalar\UrlType;
use GraphQL\Examples\Blog\Type\UserType;
use GraphQL\Examples\Blog\Type\ImageType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\Type;

/**
 * Acts as a registry and factory for your types.
 *
 * As simplistic as possible for the sake of clarity of this example.
 * Your own may be more dynamic (or even code-generated).
 */
class Types
{
    private static $types = [];
    const LAZY_LOAD_GRAPHQL_TYPES = true;

    public static function user() : callable { return static::get(UserType::class); }
    public static function story() : callable { return static::get(StoryType::class); }
    public static function comment() : callable { return static::get(CommentType::class); }
    public static function image() : callable { return static::get(ImageType::class); }
    public static function node() : callable { return static::get(NodeType::class); }
    public static function mention() : callable { return static::get(SearchResultType::class); }
    public static function imageSizeEnum() : callable { return static::get(ImageSizeEnumType::class); }
    public static function contentFormatEnum() : callable { return static::get(ContentFormatEnum::class); }
    public static function email() : callable { return static::get(EmailType::class); }
    public static function url() : callable { return static::get(UrlType::class); }

    public static function get($classname)
    {
        return static::LAZY_LOAD_GRAPHQL_TYPES ? function() use ($classname) {
            return static::byClassName($classname);
        } : static::byClassName($classname);
    }

    protected static function byClassName($classname) {
        $parts = explode("\\", $classname);
        $cacheName = strtolower(preg_replace('~Type$~', '', $parts[count($parts) - 1]));
        $type = null;

        if (!isset(self::$types[$cacheName])) {
            if (class_exists($classname)) {
                $type = new $classname();
            }

            self::$types[$cacheName] = $type;
        }

        $type = self::$types[$cacheName];

        if (!$type) {
            throw new Exception("Unknown graphql type: " . $classname);
        }
        return $type;
    }

    public static function byTypeName($shortName, $removeType=true)
    {
        $cacheName = strtolower($shortName);
        $type = null;

        if (isset(self::$types[$cacheName])) {
            return self::$types[$cacheName];
        }

        $method = lcfirst($shortName);
        if(method_exists(get_called_class(), $method)) {
            $type = self::{$method}();
        }

        if(!$type) {
            throw new Exception("Unknown graphql type: " . $shortName);
        }
        return $type;
    }

    /**
     * @param $name
     * @param null $objectKey
     * @return array
     */
    public static function htmlField($name, $objectKey = null)
    {
        return HtmlField::build($name, $objectKey);
    }



    // Let's add internal types as well for consistent experience

    public static function boolean()
    {
        return Type::boolean();
    }

    /**
     * @return \GraphQL\Type\Definition\FloatType
     */
    public static function float()
    {
        return Type::float();
    }

    /**
     * @return \GraphQL\Type\Definition\IDType
     */
    public static function id()
    {
        return Type::id();
    }

    /**
     * @return \GraphQL\Type\Definition\IntType
     */
    public static function int()
    {
        return Type::int();
    }

    /**
     * @return \GraphQL\Type\Definition\StringType
     */
    public static function string()
    {
        return Type::string();
    }

    /**
     * @param Type $type
     * @return ListOfType
     */
    public static function listOf($type)
    {
        return new ListOfType($type);
    }

    /**
     * @param Type $type
     * @return NonNull
     */
    public static function nonNull($type)
    {
        return new NonNull($type);
    }
}
