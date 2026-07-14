<?php

namespace App\Services;

class ImplementationNotReadyException extends \RuntimeException
{
    public function __construct(public readonly array $blockers)
    {
        parent::__construct('The project is not ready to start implementation.');
    }
}
