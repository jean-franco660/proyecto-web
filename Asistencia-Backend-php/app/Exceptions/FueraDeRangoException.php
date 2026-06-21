<?php

declare(strict_types=1);

namespace App\Exceptions;

class FueraDeRangoException extends \Exception
{
    protected $code = 403;
    private array $details;

    public function __construct(string $message = "", int $code = 403, array $details = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->details = $details;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
