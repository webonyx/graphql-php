<?php

namespace GraphQL\Tests\Executor;

use GraphQL\Server\StandardServer;
use GraphQL\Tests\Server\Psr7\PsrRequestStub;
use GraphQL\Tests\Server\Psr7\PsrUploadedFileStub;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

class UploadTest extends \PHPUnit_Framework_TestCase
{
    public function testCanUploadFileWithPsrRequest()
    {
        $request = $this->getRequest();

        $server = new StandardServer([
            'schema' => $this->getSchema(),
            'debug' => true,
        ]);

        $response = $server->executePsrRequest($request);

        $expected = ['testUpload' => 'Uploaded file was image.jpg (image/jpeg) with description: foo bar'];
        $this->assertSame($expected, $response->data);
    }

    /**
     * @return Schema
     */
    private function getSchema()
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
            ]),
            'mutation' => new ObjectType([
                'name' => 'Mutation',
                'fields' => [
                    'testUpload' => [
                        'type' => Type::string(),
                        'args' => [
                            'text' => Type::string(),
                            'file' => Type::file(),
                        ],
                        'resolve' => function ($root, array $args) {

                            /** @var UploadedFileInterface $file */
                            $file = $args['file'];
                            $this->assertInstanceOf(UploadedFileInterface::class, $file);

                            // Do something more interesting with the file
                            // $file->moveTo('some/folder/in/my/project');

                            return 'Uploaded file was ' . $file->getClientFilename() . ' (' . $file->getClientMediaType() . ') with description: ' . $args['text'];
                        },
                    ],

                ],
            ]),
        ]);

        return $schema;
    }

    /**
     * @return ServerRequestInterface
     */
    private function getRequest()
    {
        $request = new PsrRequestStub();
        $request->headers['content-type'] = ['multipart/form-data'];
        $request->method = 'POST';

        $request->uploadedFiles = [
            1 => new PsrUploadedFileStub('image.jpg', 'image/jpeg'),
        ];

        $request->parsedBody = [
            'operations' => json_encode([
                'query' => 'mutation TestUpload($text: String, $file: File) {
    testUpload(text: $text, file: $file)
}',
                'variables' => [
                    'text' => 'foo bar',
                    'file' => null,
                ],
                'operationName' => 'TestUpload',
            ]),
            'map' => json_encode([
                1 => ['variables.file'],
            ]),
        ];

        return $request;
    }
}

