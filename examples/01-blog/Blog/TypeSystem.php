<?php
namespace GraphQL\Examples\Blog;

use GraphQL\Examples\Blog\Type\Enum\ImageSizeEnumType;
use GraphQL\Examples\Blog\Type\NodeType;
use GraphQL\Examples\Blog\Type\QueryType;
use GraphQL\Examples\Blog\Type\Scalar\EmailType;
use GraphQL\Examples\Blog\Type\StoryType;
use GraphQL\Examples\Blog\Type\Scalar\UrlType;
use GraphQL\Examples\Blog\Type\UserType;
use GraphQL\Examples\Blog\Type\ImageType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * Class TypeSystem
 *
 * Acts as a registry and factory for your types.
 * As simplistic as possible for the sake of clarity of this example.
 * Your own may be more dynamic (or even code-generated).
 *
 * @package GraphQL\Examples\Blog
 */
class TypeSystem
{
    // Object types:
    private $story;
    private $user;
    private $image;
    private $query;

    /**
     * @return ObjectType
     */
    public function story()
    {
        return $this->story ?: ($this->story = StoryType::getDefinition($this));
    }

    /**
     * @return ObjectType
     */
    public function user()
    {
        return $this->user ?: ($this->user = UserType::getDefinition($this));
    }

    /**
     * @return ObjectType
     */
    public function image()
    {
        return $this->image ?: ($this->image = ImageType::getDefinition($this));
    }

    /**
     * @return ObjectType
     */
    public function query()
    {
        return $this->query ?: ($this->query = QueryType::getDefinition($this));
    }


    // Interfaces
    private $nodeDefinition;

    /**
     * @return \GraphQL\Type\Definition\InterfaceType
     */
    public function node()
    {
        return $this->nodeDefinition ?: ($this->nodeDefinition = NodeType::getDefinition($this));
    }


    // Enums
    private $imageSizeEnum;

    /**
     * @return EnumType
     */
    public function imageSizeEnum()
    {
        return $this->imageSizeEnum ?: ($this->imageSizeEnum = ImageSizeEnumType::getDefinition());
    }

    // Custom Scalar types:
    private $urlType;
    private $emailType;

    public function email()
    {
        return $this->emailType ?: ($this->emailType = EmailType::create());
    }

    /**
     * @return UrlType
     */
    public function url()
    {
        return $this->urlType ?: ($this->urlType = UrlType::create());
    }


    // Let's add internal types as well for consistent experience

    public function boolean()
    {
        return Type::boolean();
    }

    /**
     * @return \GraphQL\Type\Definition\FloatType
     */
    public function float()
    {
        return Type::float();
    }

    /**
     * @return \GraphQL\Type\Definition\IDType
     */
    public function id()
    {
        return Type::id();
    }

    /**
     * @return \GraphQL\Type\Definition\IntType
     */
    public function int()
    {
        return Type::int();
    }

    /**
     * @return \GraphQL\Type\Definition\StringType
     */
    public function string()
    {
        return Type::string();
    }

    /**
     * @param Type $type
     * @return ListOfType
     */
    public function listOf($type)
    {
        return new ListOfType($type);
    }

    /**
     * @param $type
     * @return NonNull
     */
    public function nonNull($type)
    {
        return new NonNull($type);
    }
}
