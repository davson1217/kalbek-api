<?php

namespace App\Exceptions;

use RuntimeException;

class AudioGenerationInProgress extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Audio generation is already in progress. Please retry shortly.');
    }
}
