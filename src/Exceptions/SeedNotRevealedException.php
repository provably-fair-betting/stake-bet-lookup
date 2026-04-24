<?php

namespace Stake\BetLookup\Exceptions;

class SeedNotRevealedException extends \RuntimeException
{
    public function __construct(string $message = '', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
