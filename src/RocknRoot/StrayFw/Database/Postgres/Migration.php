<?php

namespace RocknRoot\StrayFw\Database\Postgres;

use RocknRoot\StrayFw\Config;
use RocknRoot\StrayFw\Database\Database;
use RocknRoot\StrayFw\Database\Helper;
use RocknRoot\StrayFw\Database\Provider\Migration as ProviderMigration;
use RocknRoot\StrayFw\Database\Postgres\Query\Insert;
use RocknRoot\StrayFw\Database\Postgres\Query\Select;

/**
 * Representation parent class for PostgreSQL migrations.
 *
 * @abstract
 *
 * @author Nekith <nekith@errant-works.com>
 */
abstract class Migration extends ProviderMigration
{
    /**
     * Generate code for migration.
     *
     * @param array  $mapping     mapping definition
     * @param string $mappingName mapping name
     * @param string $name        migration name
     * @return array import, up and down code
     */
    public static function generate(array $mapping, string $mappingName, string $name)
    {
        $import = [];
        $up = [];
        $down = [];
        $oldSchema = Config::get(rtrim($mapping['config']['migrations']['path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'schema.yml');
        $schema = Config::get($mapping['config']['schema']);

        $newKeys = array_diff_key($schema, $oldSchema);
        foreach ($newKeys as $key => $table) {
            $tableName = null;
            if (isset($table['name']) === true) {
                $tableName = $table['name'];
            } else {
                $tableName = Helper::codifyName($mappingName) . '_' . Helper::codifyName($key);
            }
            if (isset($table['type']) === false || $table['type'] == 'model') {
                $import[] = 'AddTable';
                $import[] = 'RemoveTable';
                $up[] = '$this->execute(AddTable::statement($this->database, $this->schema, $this->mapping, \'' . $tableName . '\', \'' . $key . '\'));' . PHP_EOL;
                $down[] = '$this->execute(RemoveTable::statement($this->database, \'' . $tableName . '\'));' . PHP_EOL;
                echo 'AddTable: ' . $key . PHP_EOL;
            } else {
                echo 'AddEnum: ' . $key . PHP_EOL;
            }
        }

        $oldKeys = array_diff_key($oldSchema, $schema);
        foreach ($oldKeys as $key => $table) {
            $tableName = null;
            if (isset($table['name']) === true) {
                $tableName = $table['name'];
            } else {
                $tableName = Helper::codifyName($mappingName) . '_' . Helper::codifyName($key);
            }
            if (isset($table['type']) === false || $table['type'] == 'model') {
                $import[] = 'AddTable';
                $import[] = 'RemoveTable';
                $up[] = '$this->execute(RemoveTable::statement($this->database, \'' . $tableName . '\'));' . PHP_EOL;
                $down[] = '$this->execute(AddTable::statement($this->database, $this->oldSchema, $this->mapping, \'' . $tableName . '\', \'' . $key . '\'));' . PHP_EOL;
                echo 'RemoveTable: ' . $key . PHP_EOL;
            } else {
                echo 'RemoveEnum: ' . $key . PHP_EOL;
            }
        }

        $keys = array_intersect_key($oldSchema, $schema);
        foreach ($keys as $key => $table) {
        }

        return [
            'import' => array_unique($import),
            'up' => $up,
            'down' => $down,
        ];
    }

    /**
     * Ensure the migrations table exist for specified mapping.
     *
     * @param array $mapping mapping definition
     */
    public static function ensureTable(array $mapping)
    {
        $database = Database::get($mapping['config']['database']);
        $statement = 'CREATE TABLE IF NOT EXISTS _stray_migration (';
        $statement .= 'date TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL, ';
        $statement .= 'migration VARCHAR(255)';
        $statement .= ')';
        $statement = $database->getMasterLink()->prepare($statement);
        if ($statement->execute() === false) {
            echo 'Can\'t create _stray_migration (' . $statement->errorInfo()[2] . ')' . PHP_EOL;
        }
        $select = new Select($mapping['config']['database'], true);
        $select->select('COUNT(*) as count')
            ->from('_stray_migration');
        if ($select->execute() === false) {
            echo 'Can\'t fetch from _stray_migration (' . $select->getErrorMessage() . ')' . PHP_EOL;
        }
        if ($select->fetch()['count'] == 0) {
            $insert = new Insert($mapping['config']['database']);
            $insert->into('_stray_migration')
                ->values([ ]);
            if ($insert->execute() === false) {
                echo 'Can\'t insert into _stray_migration (' . $insert->getErrorMessage() . ')' . PHP_EOL;
            }
        }
    }
}