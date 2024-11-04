<?php

declare(strict_types=1);

namespace App\Bootstrap;

use \App\Persistence\Persistence;

class Banan
{
    protected Persistence $db;

    public function __construct(Persistence $db)
    {
        $this->db = $db;
        var_dump($this->db);
    }
}
