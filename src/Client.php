<?php


namespace ExCom;


use ExCom\Domain\Comments\Comment;
use ExCom\Domain\Comments\DTOs\CommentCreateDTO;
use ExCom\Domain\Comments\DTOs\CommentUpdateDTO;
use ExCom\Exceptions\ClientException as ExComClientException;
use ExCom\Exceptions\ForbiddenException;
use ExCom\Exceptions\InvalidPayloadException;
use ExCom\Exceptions\UnauthorizedException;
use ExCom\Responses\Response;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;


class Client
{
    private const USER_AGENT = 'api-example-comments-client';
    private const BASE_URI = 'http://example.com';
    private const DEFAULT_TIMEOUT = 1000;

    /** @var \GuzzleHttp\Client */
    private $httpClient;

    public function __construct(string $appName)
    {
        $this->httpClient = new \GuzzleHttp\Client([
            \GuzzleHttp\RequestOptions::HEADERS => [
                'User-Agent' => sprintf('%s/%s', static::USER_AGENT, $appName),
                'Content-Type' => 'application/json',
                'Authorization' => $this->createAuthorizationHeader(),
            ],
            'base_uri' => static::BASE_URI,
            \GuzzleHttp\RequestOptions::TIMEOUT => static::DEFAULT_TIMEOUT,
        ]);
    }

    /**
     * @return Comment[]
     * @throws Exceptions\ClientException
     * @throws ForbiddenException
     * @throws UnauthorizedException
     * @throws InvalidPayloadException
     */
    public function getComments(): array
    {
        try {
            $response = $this->httpClient->request('GET', 'comments');
        } catch (ClientException $e) {
            $this->handleClientException($e);
        } catch (RequestException $e) {
            throw new ExComClientException(
                'unable to perform get comments request',
                ExComClientException::ERROR_CODE,
                $e
            );
        } catch (\Throwable $e) {
            throw new ExComClientException(
                'unexpected error while get comments',
                ExComClientException::ERROR_CODE,
                $e
            );
        }

        $responsePayload = \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
        $this->ensureResponseIsValid($responsePayload);

        $result = [];
        foreach ($responsePayload['comments'] as $comment) {
            $result[] = Comment::createFromResponse($comment);
        }

        return $result;
    }

    /**
     * @param CommentCreateDTO $DTO
     * @return Comment
     * @throws ExComClientException
     * @throws ForbiddenException
     * @throws InvalidPayloadException
     * @throws UnauthorizedException
     */
    public function createComment(CommentCreateDTO $DTO): Comment
    {
        try {
            $response = $this->httpClient->request(
                'POST',
                'comment',
                [
                    RequestOptions::BODY => \GuzzleHttp\json_encode($DTO),
                ]
            );
        } catch (ClientException $e) {
            $this->handleClientException($e);
        } catch (RequestException $e) {
            throw new ExComClientException(
                'unable to perform create comment request',
                ExComClientException::ERROR_CODE,
                $e
            );
        } catch (\Throwable $e) {
            throw new ExComClientException(
                'unexpected error while create comment',
                ExComClientException::ERROR_CODE,
                $e
            );
        }

        $responsePayload = \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
        $this->ensureResponseIsValid($responsePayload);
        $createdComment = reset($responsePayload['comments']);
        $comment = Comment::createFromResponse($createdComment);

        return $comment;
    }

    /**
     * @param CommentUpdateDTO $DTO
     * @return Comment|null
     * @throws ExComClientException
     * @throws ForbiddenException
     * @throws UnauthorizedException
     * @throws InvalidPayloadException
     */
    public function updateComment(CommentUpdateDTO $DTO): ?Comment
    {
        try {
            $response = $this->httpClient->request(
                'PUT',
                sprintf('comment/%d', $DTO->getID()),
                [
                    RequestOptions::BODY => \GuzzleHttp\json_encode($DTO),
                ]
            );
        } catch (ClientException $e) {
            $this->handleClientException($e);
        } catch (RequestException $e) {
            throw new ExComClientException(
                'unable to perform update comment request',
                ExComClientException::ERROR_CODE,
                $e
            );
        } catch (\Throwable $e) {
            throw new ExComClientException(
                'unexpected error while update comment',
                ExComClientException::ERROR_CODE,
                $e
            );
        }

        $responsePayload = \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
        $this->ensureResponseIsValid($responsePayload);
        $updatedComment = reset($responsePayload['comments']);
        $comment = Comment::createFromResponse($updatedComment);

        return $comment;
    }

    private function createAuthorizationHeader(): string
    {
        return '';
    }

    /**
     * @param array $responsePayload
     * @throws InvalidPayloadException
     */
    private function ensureResponseIsValid(array $responsePayload): void
    {

        $validationErrors = [];
        $comments = $responsePayload['comments'] ?? null;

        if (!$comments) {
            throw new InvalidPayloadException('no comments in response');
        }

        array_map(function (array $comment) use (&$validationErrors) {
            foreach (Comment::VALIDATION_MAP as $requiredField => $validationFn) {
                if (!isset($comment[$requiredField])) {
                    $validationErrors[] = sprintf('payload field %s is empty', $requiredField);
                    continue;
                }

                if (!call_user_func($validationFn, $comment[$requiredField])) {
                    $validationErrors[] = sprintf(
                        'payload field %s has unexpected type, rule: %s',
                        $requiredField,
                        $validationFn
                    );
                }
            }
        }, $comments);

        if ($validationErrors !== []) {
            throw new InvalidPayloadException(implode('; ', $validationErrors));
        }
    }

    /**
     * @param ClientException $e
     * @throws ExComClientException
     * @throws ForbiddenException
     * @throws UnauthorizedException
     */
    private function handleClientException(ClientException $e): void
    {
        $response = $e->getResponse();
        $decodedResponse = \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
        $message = isset($decodedResponse['errors']['message']) ? $decodedResponse['errors']['message'] : null;

        switch (true) {
            case $response->getStatusCode() === Response::HTTP_UNAUTHORIZED && $message:
                throw new UnauthorizedException($message);
            case $response->getStatusCode() === Response::HTTP_FORBIDDEN && $message:
                throw new ForbiddenException($message);
            default:
                throw new ExComClientException(
                    'unable to perform comments request',
                    ExComClientException::ERROR_CODE,
                    $e
                );
        }
    }
}
