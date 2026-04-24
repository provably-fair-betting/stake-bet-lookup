<?php

namespace Stake\BetLookup\Exceptions;

class BetNotFoundException extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
