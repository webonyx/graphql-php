<?php
namespace GraphQL\Examples\Blog;

use GraphQL\Examples\Blog\Type\CommentType;
use GraphQL\Examples\Blog\Type\Enum\ContentFormatEnum;
use GraphQL\Examples\Blog\Type\Enum\ImageSizeEnumType;
use GraphQL\Examples\Blog\Type\Field\HtmlField;
use GraphQL\Examples\Blog\Type\FieldDefinitions;
use GraphQL\Examples\Blog\Type\MentionType;
use GraphQL\Examples\Blog\Type\NodeType;
use GraphQL\Examples\Blog\Type\QueryType;
use GraphQL\Examples\Blog\Type\Scalar\EmailType;
use GraphQL\Examples\Blog\Type\StoryType;
use GraphQL\Examples\Blog\Type\Scalar\UrlTypeDefinition;
use GraphQL\Examples\Blog\Type\UserType;
use GraphQL\Examples\Blog\Type\ImageType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\DefinitionContainer;

/**
 * Class TypeSystem
 *
 * Acts as a registry and factory for your types.
 *
 * As simplistic as possible for the sake of clarity of this example.
 * Your own may be more dynamic (or even code-generated).
 *
 * @package GraphQL\Examples\Blog
 */
class TypeSystem
{
    // Object types:
    private $user;
    private $story;
    private $comment;
    private $image;
    private $query;

    /**
     * @return UserType
     */
    public function user()
    {
        return $this->user ?: ($this->user = new UserType($this));
    }

    /**
     * @return StoryType
     */
    public function story()
    {
        return $this->story ?: ($this->story = new StoryType($this));
    }

    /**
     * @return CommentType
     */
    public function comment()
    {
        return $this->comment ?: ($this->comment = new CommentType($this));
    }

    /**
     * @return ImageType
     */
    public function image()
    {
        return $this->image ?: ($this->image = new ImageType($this));
    }

    /**
     * @return QueryType
     */
    public function query()
    {
        return $this->query ?: ($this->query = new QueryType($this));
    }


    // Interface types
    private $node;

    /**
     * @return NodeType
     */
    public function node()
    {
        return $this->node ?: ($this->node = new NodeType($this));
    }


    // Unions types:
    private $mention;

    /**
     * @return MentionType
     */
    public function mention()
    {
        return $this->mention ?: ($this->mention = new MentionType($this));
    }


    // Enum types
    private $imageSizeEnum;
    private $contentFormatEnum;

    /**
     * @return ImageSizeEnumType
     */
    public function imageSizeEnum()
    {
        return $this->imageSizeEnum ?: ($this->imageSizeEnum = new ImageSizeEnumType());
    }

    /**
     * @return ContentFormatEnum
     */
    public function contentFormatEnum()
    {
        return $this->contentFormatEnum ?: ($this->contentFormatEnum = new ContentFormatEnum());
    }

    // Custom Scalar types:
    private $urlType;
    private $emailType;

    public function email()
    {
        return $this->emailType ?: ($this->emailType = new EmailType);
    }

    /**
     * @return UrlTypeDefinition
     */
    public function url()
    {
        return $this->urlType ?: ($this->urlType = new UrlTypeDefinition);
    }

    /**
     * @param $name
     * @param null $objectKey
     * @return array
     */
    public function htmlField($name, $objectKey = null)
    {
        return HtmlField::build($this, $name, $objectKey);
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
     * @param Type|DefinitionContainer $type
     * @return ListOfType
     */
    public function listOf($type)
    {
        return new ListOfType($type);
    }

    /**
     * @param Type|DefinitionContainer $type
     * @return NonNull
     */
    public function nonNull($type)
    {
        return new NonNull($type);
    }
}
