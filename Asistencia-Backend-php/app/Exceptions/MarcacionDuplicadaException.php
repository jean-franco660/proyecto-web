<?php
declare(strict_types=1);

namespace App\Exceptions;

class MarcacionDuplicadaException extends \Exception
{
    protected $code = 400;
}
