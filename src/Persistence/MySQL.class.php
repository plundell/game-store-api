<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Persistence\RSDB;

class MySQL extends RSDB
{
    public function __construct()
    {
        $db_host = $_ENV['DB_HOST'];
        $db_name = $_ENV['DB_NAME'];
        $db_user = $_ENV['DB_USER'];
        $db_pass = $_ENV['DB_PASS'];

        $db = new \mysqli($db_host, $db_user, $db_pass, $db_name);

        if ($db->connect_error) {
            throw new \Exception('Connect Error (' . $db->connect_errno . ') ' . $db->connect_error);
        }

        parent::__construct($db);
    }
}
