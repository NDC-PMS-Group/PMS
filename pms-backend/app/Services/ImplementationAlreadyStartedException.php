<?php

namespace App\Services;

class ImplementationAlreadyStartedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Implementation has already started for this project.');
    }
}
