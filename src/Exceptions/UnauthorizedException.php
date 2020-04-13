<?php


namespace ExCom\Exceptions;

class UnauthorizedException extends \Exception
{
    const ERROR_CODE = 1;

    public function __construct(
        $message = "unauthorized",
        $code = self::ERROR_CODE,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
