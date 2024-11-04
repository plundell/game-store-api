<?php

namespace App\Persistence;

abstract class Database
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }
}
