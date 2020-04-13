<?php


namespace ExCom\Exceptions;


class InvalidPayloadException extends \Exception
{
    const ERROR_CODE = 4;

    public function __construct(
        $message = "invalid payload",
        $code = self::ERROR_CODE,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
