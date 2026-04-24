<?php

namespace Stake\BetLookup\Exceptions;

class AuthenticationException extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 401, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
