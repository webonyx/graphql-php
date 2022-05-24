<?php declare(strict_types=1);

namespace GraphQL\Tests\Type;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\TypeReference;
use GraphQL\Type\Schema;

final class TypeReferenceTypeLoaderTest extends TypeLoaderTest
{
    private array $types;

    public function setUp(): void
    {
        parent::setUp();

        $this->types = [
            'Node' => new InterfaceType([
                'name' => 'Node',
                'fields' => [
                    'id' => Type::string(),
                ],
                'resolveType' => static fn (): ?ObjectType => null,
            ]),
            'Content' => new InterfaceType([
                'name' => 'Content',
                'fields' => [
                    'title' => Type::string(),
                    'body' => Type::string(),
                ],
                'resolveType' => static fn (): ?ObjectType => null,
            ]),
            'BlogStory' => new ObjectType([
                'name' => 'BlogStory',
                'interfaces' => [
                    new TypeReference('Node'),
                    new TypeReference('Content'),
                ],
                'fields' => [
                    'id' => Type::string(),
                    'title' => Type::string(),
                    'body' => Type::string(),
                ],
            ]),
            'Query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'latestContent' => new TypeReference('Content'),
                    'node' => new TypeReference('Node'),
                ],
            ]),
            'Mutation' => new ObjectType([
                'name' => 'Mutation',
                'fields' => [
                    'postStory' => [
                        'type' => new TypeReference('PostStoryMutation'),
                        'args' => [
                            'input' => Type::nonNull(new TypeReference('PostStoryMutationInput')),
                            'clientRequestId' => Type::string(),
                        ],
                    ],
                ],
            ]),
            'PostStoryMutation' => new ObjectType([
                'name' => 'PostStoryMutation',
                'fields' => [
                    'story' => new TypeReference('BlogStory'),
                ],
            ]),
            'PostStoryMutationInput' => new InputObjectType([
                'name' => 'PostStoryMutationInput',
                'fields' => [
                    'title' => Type::string(),
                    'body' => Type::string(),
                    'author' => Type::id(),
                    'category' => Type::id(),
                ],
            ]),
        ];
    }

    public function testWorksWithTypeLoader(): void
    {
        $schema = new Schema([
            'query' => $this->types['Query'],
            'mutation' => $this->types['Mutation'],
            'typeLoader' => fn (string $name) => $this->types[$name] ?? null,
        ]);

        $node = $schema->getType('Node');
        self::assertSame($this->types['Node'], $node);

        $content = $schema->getType('Content');
        self::assertSame($this->types['Content'], $content);

        $blogStory = $schema->getType('BlogStory');
        self::assertSame($this->types['BlogStory'], $blogStory);
        self::assertInstanceOf(ObjectType::class, $blogStory);
        self::assertCount(2, $blogStory->getInterfaces());

        $postStoryMutation = $schema->getType('PostStoryMutation');
        self::assertSame($this->types['PostStoryMutation'], $postStoryMutation);
        self::assertInstanceOf(ObjectType::class, $postStoryMutation);
        $storyField = $postStoryMutation->getField('story');
        self::assertSame($blogStory, $schema->resolveTypeReference($storyField->getType()));

        $postStoryMutationInput = $schema->getType('PostStoryMutationInput');
        self::assertSame($this->types['PostStoryMutationInput'], $postStoryMutationInput);
        self::assertInstanceOf(InputObjectType::class, $postStoryMutationInput);

        $input = $schema->getType('PostStoryMutationInput');
        self::assertSame($this->types['PostStoryMutationInput'], $input);

        $query = $schema->getType('Query');
        self::assertSame($this->types['Query'], $query);
        self::assertInstanceOf(ObjectType::class, $query);
        $latestContentField = $query->getField('latestContent');
        self::assertSame($content, $schema->resolveTypeReference($latestContentField->getType()));
        $nodeField = $query->getField('node');
        self::assertSame($node, $schema->resolveTypeReference($nodeField->getType()));

        $mutation = $schema->getType('Mutation');
        self::assertSame($this->types['Mutation'], $mutation);
        self::assertInstanceOf(ObjectType::class, $mutation);
        $postStoryField = $mutation->getField('postStory');
        self::assertSame($postStoryMutation, $schema->resolveTypeReference($postStoryField->getType()));
        $inputArg = $postStoryField->getArg('input');
        self::assertInstanceOf(NonNull::class, $inputArg->getType());
        self::assertSame($postStoryMutationInput, $schema->resolveTypeReference($inputArg->getType()->getWrappedType()));

        $result = $schema->isSubType($node, $schema->getType('BlogStory'));
        self::assertTrue($result);
    }
}
