<?php

declare(strict_types=1);

namespace App\Exceptions;

class SedeNoEncontradaException extends \Exception
{
    protected $code = 404;
}
