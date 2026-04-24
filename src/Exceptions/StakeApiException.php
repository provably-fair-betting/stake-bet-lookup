<?php

namespace Stake\BetLookup\Exceptions;

class StakeApiException extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 500, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
