<?php

namespace ExCom\Tests;

use ExCom\Client;
use ExCom\Domain\Comments\DTOs\CommentCreateDTO;
use ExCom\Domain\Comments\DTOs\CommentUpdateDTO;
use ExCom\Exceptions\ForbiddenException;
use ExCom\Exceptions\InvalidPayloadException;
use ExCom\Exceptions\UnauthorizedException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use ExCom\Responses\Response as ExComResponse;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    /** @var Client */
    private $client;


    public function setUp()
    {
        $this->client = new Client('testApp');
    }

    public function provideGetCommentsPayload(): array
    {
        return [
            'one comment' => [
                [
                    'comments' => [
                        [
                            'id' => 1,
                            'name' => 'name',
                            'text' => 'text',
                        ],
                    ],
                ],
            ],
            'some comments' => [
                [
                    'comments' => [
                        [
                            'id' => 1,
                            'name' => 'name',
                            'text' => 'text',
                        ],
                        [
                            'id' => 2,
                            'name' => 'name2',
                            'text' => 'text2',
                        ],
                        [
                            'id' => 3,
                            'name' => 'name3',
                            'text' => 'text3',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideGetCommentsPayload
     * @param array $responseBody
     * @throws \ExCom\Exceptions\ClientException
     * @throws \ExCom\Exceptions\ForbiddenException
     * @throws \ExCom\Exceptions\InvalidPayloadException
     * @throws \ExCom\Exceptions\UnauthorizedException
     */
    public function testGetComments(array $responseBody)
    {
        //arrange
        $response = new Response(200, [], \json_encode($responseBody));
        $httpClientMock = $this->createMock(\GuzzleHttp\Client::class);
        $httpClientMock->expects($this->once())->method('request')->with(
            'GET',
            'comments'
        )->willReturn($response);
        $this->setHttpClient($httpClientMock);

        //act
        $comments = $this->client->getComments();

        //assert
        foreach ($comments as $i => $comment) {
            $this->assertEquals($responseBody['comments'][$i]['id'], $comment->getID());
            $this->assertEquals($responseBody['comments'][$i]['name'], $comment->getName());
            $this->assertEquals($responseBody['comments'][$i]['text'], $comment->getText());
        }
    }

    public function testCreateComment()
    {
        //arrange
        $id = 1;
        $name = 'name';
        $text = 'text';
        $DTO = new CommentCreateDTO($name, $text);

        $responseBody = [
            'comments' => [
                [
                    'id' => $id,
                    'name' => $name,
                    'text' => $text,
                ],
            ],
        ];

        $response = new Response(201, [], \json_encode($responseBody));
        $httpClientMock = $this->createMock(\GuzzleHttp\Client::class);
        $httpClientMock->expects($this->once())->method('request')->with(
            'POST',
            'comment',
            [
                RequestOptions::BODY => \GuzzleHttp\json_encode($DTO),
            ]
        )->willReturn($response);
        $this->setHttpClient($httpClientMock);

        //act
        $comment = $this->client->createComment($DTO);

        //assert
        $this->assertEquals($id, $comment->getID());
        $this->assertEquals($name, $comment->getName());
        $this->assertEquals($text, $comment->getText());
    }

    public function providePayloadForUpdate(): array
    {
        return [
            'name' => [
                new CommentUpdateDTO(1, 'name', null),
                'name',
                'some text',
            ],
            'text' => [
                new CommentUpdateDTO(1, null, 'text'),
                'some name',
                'text',
            ],
            'both' => [
                new CommentUpdateDTO(1, 'name', 'text'),
                'name',
                'text',
            ],
        ];
    }

    /**
     * @dataProvider providePayloadForUpdate
     * @param CommentUpdateDTO $DTO
     * @param string $expectedName
     * @param string $expectedText
     * @throws \ExCom\Exceptions\ClientException
     * @throws \ExCom\Exceptions\ForbiddenException
     * @throws \ExCom\Exceptions\InvalidPayloadException
     * @throws \ExCom\Exceptions\UnauthorizedException
     */
    public function testUpdateComment(CommentUpdateDTO $DTO, ?string $expectedName, ?string $expectedText)
    {
        //arrange
        $responseBody = [
            'comments' => [
                [
                    'id' => $DTO->getID(),
                    'name' => $expectedName,
                    'text' => $expectedText,
                ],
            ],
        ];

        $response = new Response(200, [], \json_encode($responseBody));
        $httpClientMock = $this->createMock(\GuzzleHttp\Client::class);
        $httpClientMock->expects($this->once())->method('request')->with(
            'PUT',
            sprintf('comment/%d', $DTO->getID()),
            [
                RequestOptions::BODY => \GuzzleHttp\json_encode($DTO),
            ]
        )->willReturn($response);
        $this->setHttpClient($httpClientMock);

        //act
        $comment = $this->client->updateComment($DTO);

        //assert
        $this->assertEquals($DTO->getID(), $comment->getID());
        $this->assertEquals($expectedText, $comment->getText());
        $this->assertEquals($expectedName, $comment->getName());
    }

    public function provideExceptions(): array
    {
        $errMessage = 'some err message';
        $responseBody = [
            'errors' => ['message' => $errMessage],
            'comments' => [],
        ];

        return [
            UnauthorizedException::class => [
                new Response(ExComResponse::HTTP_UNAUTHORIZED, [], \json_encode($responseBody)),
                UnauthorizedException::class,
                $errMessage,
            ],
            ForbiddenException::class => [
                new Response(ExComResponse::HTTP_FORBIDDEN, [], \json_encode($responseBody)),
                ForbiddenException::class,
                $errMessage,
            ],
            \ExCom\Exceptions\ClientException::class => [
                new Response(ExComResponse::HTTP_BAD_REQUEST, [], \json_encode($responseBody)),
                \ExCom\Exceptions\ClientException::class,
                'unable to perform comments request',
            ],
        ];
    }

    /**
     * @dataProvider provideExceptions
     * @param Response $response
     * @param string $expectedExceptionClass
     * @param string $expectedExceptionMessage
     * @throws UnauthorizedException
     * @throws \ExCom\Exceptions\ClientException
     * @throws \ExCom\Exceptions\ForbiddenException
     * @throws \ExCom\Exceptions\InvalidPayloadException
     */
    public function testClientExceptionOnCreate(
        Response $response,
        string $expectedExceptionClass,
        string $expectedExceptionMessage
    ) {
        //arrange
        $name = 'name';
        $text = 'text';
        $DTO = new CommentCreateDTO($name, $text);

        $httpClientMock = $this->createMock(\GuzzleHttp\Client::class);
        $httpClientMock->expects($this->once())->method('request')->with(
            'POST',
            'comment',
            [
                RequestOptions::BODY => \GuzzleHttp\json_encode($DTO),
            ]
        )->willThrowException(new ClientException(
            'test client exception',
            new Request('POST', 'uri'),
            $response
        ));

        $DTO = new CommentCreateDTO($name, $text);
        $this->setHttpClient($httpClientMock);

        //assert
        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessage($expectedExceptionMessage);

        //act
        $this->client->createComment($DTO);
    }

    /**
     * @dataProvider provideExceptions
     * @param Response $response
     * @param string $expectedExceptionClass
     * @param string $expectedExceptionMessage
     * @throws UnauthorizedException
     * @throws \ExCom\Exceptions\ClientException
     * @throws \ExCom\Exceptions\ForbiddenException
     * @throws \ExCom\Exceptions\InvalidPayloadException
     */
    public function testClientExceptionOnGet(
        Response $response,
        string $expectedExceptionClass,
        string $expectedExceptionMessage
    ) {
        //arrange
        $httpClientMock = $this->createMock(\GuzzleHttp\Client::class);
        $httpClientMock->expects($this->once())->method('request')->with(
            'GET',
            'comments',
            )->willThrowException(new ClientException(
            'test client exception',
            new Request('GET', 'uri'),
            $response
        ));

        $this->setHttpClient($httpClientMock);

        //assert
        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessage($expectedExceptionMessage);

        //act
        $this->client->getComments();
    }

    /**
     * @dataProvider provideExceptions
     * @param Response $response
     * @param string $expectedExceptionClass
     * @param string $expectedExceptionMessage
     * @throws UnauthorizedException
     * @throws \ExCom\Exceptions\ClientException
     * @throws \ExCom\Exceptions\ForbiddenException
     * @throws \ExCom\Exceptions\InvalidPayloadException
     */
    public function testClientExceptionOnUpdate(
        Response $response,
        string $expectedExceptionClass,
        string $expectedExceptionMessage
    ) {
        //arrange
        $name = 'name';
        $text = 'text';
        $DTO = new CommentCreateDTO($name, $text);

        $httpClientMock = $this->createMock(\GuzzleHttp\Client::class);
        $httpClientMock->expects($this->once())->method('request')->with(
            'PUT',
            'comment/1',
            [
                RequestOptions::BODY => \GuzzleHttp\json_encode($DTO),
            ]
        )->willThrowException(new ClientException(
            'test client exception',
            new Request('PUT', 'uri'),
            $response
        ));

        $DTO = new CommentUpdateDTO(1, $name, $text);
        $this->setHttpClient($httpClientMock);

        //assert
        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessage($expectedExceptionMessage);

        //act
        $this->client->updateComment($DTO);
    }

    public function testHandleRequestExceptionOnGet()
    {
        //arrange
        $exception = new RequestException('some err', new Request('GET', 'uri'));
        $httpClientMock = $this->createMock(\GuzzleHttp\Client::class);
        $httpClientMock->expects($this->once())->method('request')->with(
            'GET',
            'comments',
            )->willThrowException($exception);

        $this->setHttpClient($httpClientMock);

        //assert
        $this->expectException(\ExCom\Exceptions\ClientException::class);
        $this->expectExceptionMessage('unable to perform get comments request');

        //act
        $this->client->getComments();
    }

    public function testHandleRequestExceptionOnCreate()
    {
        //arrange
        $DTO = new CommentCreateDTO('', '');
        $exception = new RequestException('some err', new Request('POST', 'uri'));
        $httpClientMock = $this->createMock(\GuzzleHttp\Client::class);
        $httpClientMock->expects($this->once())->method('request')->with(
            'POST',
            'comment',
            [
                RequestOptions::BODY => \GuzzleHttp\json_encode($DTO),
            ]
        )->willThrowException($exception);

        $this->setHttpClient($httpClientMock);

        //assert
        $this->expectException(\ExCom\Exceptions\ClientException::class);
        $this->expectExceptionMessage('unable to perform create comment request');

        //act
        $this->client->createComment($DTO);
    }

    public function testHandleRequestExceptionOnUpdate()
    {
        //arrange
        $DTO = new CommentUpdateDTO(1, '', '');
        $exception = new RequestException('some err', new Request('PUT', 'uri'));
        $httpClientMock = $this->createMock(\GuzzleHttp\Client::class);
        $httpClientMock->expects($this->once())->method('request')->with(
            'PUT',
            'comment/1',
            [
                RequestOptions::BODY => \GuzzleHttp\json_encode($DTO),
            ]
        )->willThrowException($exception);

        $this->setHttpClient($httpClientMock);

        //assert
        $this->expectException(\ExCom\Exceptions\ClientException::class);
        $this->expectExceptionMessage('unable to perform update comment request');

        //act
        $this->client->updateComment($DTO);
    }

    public function providePayloadForValidation(): array
    {
        return [
            'empty' => [
                [],
                'no comments in response',
            ],
            'empty_comments' => [
                ['comments' => []],
                'no comments in response',
            ],
            'bad_comments' => [
                ['comments' =>  [['foo' => 'bar']]],
                'payload field id is empty; payload field name is empty; payload field text is empty',
            ],
        ];
    }

    /**
     * @dataProvider providePayloadForValidation
     * @param array $responseBody
     * @param string $expectedMessage
     * @throws ForbiddenException
     * @throws InvalidPayloadException
     * @throws UnauthorizedException
     * @throws \ExCom\Exceptions\ClientException
     */
    public function testResponseValidation(array $responseBody, string $expectedMessage)
    {
        //arrange
        $response = new Response(200, [], \json_encode($responseBody));
        $httpClientMock = $this->createMock(\GuzzleHttp\Client::class);
        $httpClientMock->expects($this->once())->method('request')->with(
            'GET',
            'comments',
            )->willReturn($response);

        $this->setHttpClient($httpClientMock);

        //assert
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage($expectedMessage);

        //act
        $this->client->getComments();
    }

    private function setHttpClient(MockObject $httpClientMock)
    {
        $reflectionClient = new \ReflectionObject($this->client);
        $reflectionProperty = $reflectionClient->getProperty('httpClient');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->client, $httpClientMock);
    }
}
