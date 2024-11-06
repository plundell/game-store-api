<?php

declare(strict_types=1);

namespace App\Exceptions;

class CustomInvalidArgumentException extends CustomException
{

    public function __construct(string $expected, &$got)
    {
        parent::__construct(self::wrongtype($expected, $got));
    }
}
