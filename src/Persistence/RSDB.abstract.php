<?php

declare(strict_types=1);

namespace App\Persistence;

use Rakit\Validation\Validator;
use \App\Common\Exceptions\CustomException;

abstract class RSDB implements Persistence
{

    /**
     * Is set first time ::getValidator() is called
     */
    static private ?Validator $validator;
    /**
     * Is set first time ::getValidatorRules() is called
     */
    static private ?array $validatorRules;

    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getRecord($table, $id): array
    {
        $sql = "SELECT * FROM {$table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    protected static function getValidator(): Validator
    {
        if (!isset(self::$validator)) {
            self::$validator = new Validator();
        }
        return self::$validator;
    }
    protected static function getValidatorRules(): array
    {
        if (!isset(self::$validatorRules)) {
            $alpha_num_underscore = 'regex:/^[a-zA-Z0-9_]+$/';
            $ref = 'regex:/^[a-zA-Z0-9_]+\([a-zA-Z0-9_]+\)$/';
            self::$validatorRules = [
                "tables" => "required|array",
                "tables.*.name" => "required|$alpha_num_underscore",
                "tables.*.columns" => "required|array",
                "tables.*.columns.*.name" => "required|$alpha_num_underscore",
                "tables.*.columns.*.type" => "required|alpha_num|in:INTEGER,REAL,TEXT,BLOB",
                "tables.*.columns.*.primary" => "boolean",
                "tables.*.columns.*.unique" => "boolean",
                "tables.*.columns.*.ref" => $ref,
                "tables.*.indexes" => "array",
                "tables.*.indexes.*.name" => "required|$alpha_num_underscore",
                "tables.*.indexes.*.columns" => "required|array|min:1",
                "tables.*.indexes.*.unique" => "boolean",

            ];
        }
        return self::$validatorRules;
    }

    /**
     * Validate a persistence definition against the expected schema rules.
     *
     * @param array &$schema The persistence definition to validate
     *
     * @throws CustomException If the definition is invalid
     */
    public static function validateJsonSchema(array &$schema): void
    {
        // Run validation
        $validation = self::getValidator()->make($schema, self::getValidatorRules());
        $validation->validate();

        if ($validation->fails()) {
            $errors = $validation->errors();
            $messages = [];
            foreach ($errors->toArray() as $field => $errorMessages) {
                foreach ($errorMessages as $errorMessage) {
                    $messages[] = "Field '{$field}': {$errorMessage}";
                }
            }
            throw new CustomException(
                "Invalid format for persistence definition:\n" .
                    implode("\n", $messages)
            );
        }
    }
}
