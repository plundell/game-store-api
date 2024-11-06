<?php

/**
 * This script will initiate the selected persistence provider by:
 *  - Checking the .env file for the selected provider and credentials
 *  - Scanning the SCR_DIR all persistence definitions
 *  - Calling the initiator for the selected provider
 */

declare(strict_types=1);

use App\Bootstrap\Bootstrap;
use App\Bootstrap\DynamicLoader;
use App\Persistence\Persistence;
use App\Persistence\RSDB;
use App\Common\Exceptions\CustomException;

require __DIR__ . '/../src/Bootstrap/Bootstrap.php';

(function () {


    //Load the bootstrap file and activate only dependecncy injection . This 
    //will also define path constants, load autoloaders, and load the .env file
    $di = Bootstrap::dependencyInjection();


    //Scan for schema files
    $files = DynamicLoader::load(['pattern' => '*.schema.json']);
    echo "Found " . count($files) . " schema files.\n";

    //Create an instance of our persistence provider (we'll need the static method)
    //
    // $db = $di->get(Persistence::class);

    //Convert file contents into assoc arrays, validate their format, 
    //and keep only the good ones. 
    $schemas = [];
    foreach ($files as $filepath => $contents) {
        try {
            //Decode the string into an array
            $schema = json_decode((string)$contents, true); //true = assoc
            if (!is_array($schema)) {
                throw new CustomException("JSON didn't contain an array, got:", $schema);
            }

            //Validate the schema (throws on failure)
            RSDB::validateJsonSchema($schema);

            //If we're still running, save it as a good schema
            $schemas[] = $schema;
        } catch (\Exception $e) {
            error_log("Failed to load schema file '$filepath': " . (string)$e);
        }
    }
    if (!count($schemas)) {
        throw new Exception("No valid schemas found.");
    } else {
        echo "Cleaned " . count($schemas) . " schemas.\n";
    }

    //combine the arrays in $schema without overwriting anything
    $combinedSchemas = array_merge_recursive(...$schemas);

    //Finally, use autowiring to get the persistence provider specified 
    //by the .env file and init the tables using that provider
    $di->get(Persistence::class)->initialize($combinedSchemas);
})();
