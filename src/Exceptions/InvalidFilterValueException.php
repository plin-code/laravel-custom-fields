<?php

declare(strict_types=1);

namespace PlinCode\CustomFields\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Spatie\QueryBuilder\Exceptions\InvalidFilterValue;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class InvalidFilterValueException extends InvalidFilterValue implements HttpExceptionInterface, ShouldntReport
{
    public function getStatusCode(): int
    {
        return 400;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return [];
    }
}
