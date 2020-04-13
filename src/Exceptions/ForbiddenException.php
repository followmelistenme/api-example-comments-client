<?php


namespace ExCom\Exceptions;

class ForbiddenException extends \Exception
{
    const ERROR_CODE = 2;

    public function __construct(
        $message = "forbidden",
        $code = self::ERROR_CODE,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
