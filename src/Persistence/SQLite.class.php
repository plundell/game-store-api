<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Common\Exceptions\CustomException;
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

        // Now create a SQLite3 object and activate foreign key support
        $db = new SQLite3($db_filepath);
        $db->exec('PRAGMA foreign_keys = ON;'); // Enable foreign key support

        //Finally pass it to the parent constructor
        parent::__construct($db);
    }

    public function initialize(array $definitions): self
    {
        //The definitions will come in a standardized format to work with
        //other persistence implementations, but we need to turn them into
        //SQLite queries...
        $queries = self::convertDefinitionsToQueries($definitions);
        echo "Converted definitions to " . count($queries) . " queries, example:\n{$queries[0]}\n";

        //...then simply execute them them all inside a transaction
        $this->db->exec('BEGIN TRANSACTION');
        try {
            foreach ($queries as $query) {
                echo $query . "\n";
                try {
                    $this->db->exec($query);
                } catch (\Exception $e) {
                    throw new CustomException("Failed to execute query:\n\t{$query}\n", $e);
                }
            }
            $this->db->exec('COMMIT');
        } catch (\Exception $e) {
            $this->db->exec('ROLLBACK');
            throw new CustomException('Failed to initialize database', $e);
        }
        echo "Database initialized.\n";
        //Now we should have a working database
        return $this;
    }

    /**
     * Convert the standardized persistence definitions to SQLite queries.
     * 
     * The definitions contain all the information needed to create the
     * tables, indexes, and relationships in the database.
     * 
     * @param array $dbDefinitions The standardized persistence definitions
     * 
     * @return array An array of SQLite queries
     */
    static public function convertDefinitionsToQueries(array $definitions): array
    {
        if (!isset($definitions['tables'])) {
            throw new CustomException("Invalid persistence definition: missing top level prop 'tables'");
        }
        $queries = [];
        $queriesLast = [];
        foreach ($definitions['tables'] as $table) {

            // Create table definition
            $createTableQuery = "CREATE TABLE IF NOT EXISTS {$table['name']} (";
            $columns = [];

            foreach ($table['columns'] as $column) {
                $columnDef = "{$column['name']} {$column['type']}";
                if (isset($column['primary']) && $column['primary']) {
                    $columnDef .= " PRIMARY KEY";
                    if (isset($column['autoincrement']) && $column['autoincrement']) {
                        $columnDef .= " AUTOINCREMENT";
                    }
                }
                if (isset($column['notnull']) && $column['notnull']) {
                    $columnDef .= " NOT NULL";
                }
                if (isset($column['unique']) && $column['unique']) {
                    $columnDef .= " UNIQUE";
                }
                if (isset($column['ref'])) {
                    $columnDef .= " REFERENCES {$column['ref']}";
                }
                $columns[] = $columnDef;
            }

            $createTableQuery .= implode(", ", $columns) . ");";
            $queries[] = $createTableQuery;

            // Process indexes
            if (isset($table['indexes'])) {
                foreach ($table['indexes'] as $index) {
                    $indexColumns = implode(", ", $index['columns']);
                    $queries[] = "CREATE INDEX IF NOT EXISTS idx_{$table['name']}_{$indexColumns} ON {$table['name']}($indexColumns);";
                }
            }
        }

        //Return the combine the queries
        return array_merge($queries, $queriesLast);
    }
}
