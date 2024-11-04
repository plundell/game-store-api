<?php

declare(strict_types=1);

namespace App\Persistence;

interface Persistence
{
    function getRecord(string $table, string $id): array;
    //TODO: maybe $id should be a domain object
}
