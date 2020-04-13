<?php


namespace ExCom\Exceptions;


class ClientException extends \Exception
{
    const ERROR_CODE = 3;

    public function __construct(
        $message = "internal client error",
        $code = self::ERROR_CODE,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
