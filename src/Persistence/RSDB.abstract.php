<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Persistence\Database;

abstract class RSDB extends Database implements Persistence
{
    public function __construct($db)
    {
        parent::__construct($db);
    }

    public function getRecord($table, $id): array
    {
        $sql = "SELECT * FROM {$table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
