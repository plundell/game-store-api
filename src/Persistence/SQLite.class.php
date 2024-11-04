<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Persistence\RSDB;
use \SQLite3;

class SQLite extends RSDB
{
    public function __construct()
    {
        // DB_FILEPATH can be either a relative path to the project root,
        // or an absolute path. 
        $db_filepath = $_ENV['DB_FILEPATH'];

        // If it's a relative path, we prepend ROOT_DIR
        if (strpos($db_filepath, '/') === 0) {
            $db_filepath = realpath($db_filepath);
        } else {
            $db_filepath = ROOT_DIR . '/' . $db_filepath;
        }

        // Now create a SQLite3 object...
        $db = new SQLite3($db_filepath);
        //...and pass it to the parent constructor
        parent::__construct($db);
    }
}
