<?php

namespace Phpmig\Adapter\PDO;

use Phpmig\Migration\Migration,
    Phpmig\Adapter\AdapterInterface,
    PDO;

/**
 * Simple PDO adapter to work with SQL database
 *
 * @author Samuel Laulhau https://github.com/lalop
 */

class Sql implements AdapterInterface
{

    /**
     * @var \PDO
     */
    protected $connection    = null;

    /**
     * @var string
     */
    protected $tableName     = null;

    /**
     * @var string
     */
    protected $pdoDriverName = null;

    /**
     * Constructor
     *
     * @param \PDO $connection
     * @param string $tableName
     */
    public function __construct(\PDO $connection, $tableName)
    {
        $this->connection    = $connection;
        $this->tableName     = $tableName;
        $this->pdoDriverName = $connection->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    private function quotedTableName()
    {
        return "`{$this->tableName}`";
    }

    /**
     * Fetch all
     *
     * @return array
     */
    public function fetchAll()
    {
        // get the appropriate query
        //
        $sql = $this->getQuery('fetchAll');

        // return the results of the query
        //
        return $this->connection->query($sql, PDO::FETCH_COLUMN, 0)->fetchAll();
    }

    /**
     * Up
     *
     * @param Migration $migration
     * @return self
     */
    public function up(Migration $migration)
    {
        // get the appropriate query
        //
        $sql = $this->getQuery('up');

        // prepare and execute the query
        //
        $this->connection->prepare($sql)
                ->execute(array(':version' => $migration->getVersion()));

        return $this;
    }

    /**
     * Down
     *
     * @param Migration $migration
     * @return self
     */
    public function down(Migration $migration)
    {
        // get the appropriate query
        //
        $sql = $this->getQuery('down');

        // prepare and execute the query
        //
        $this->connection->prepare($sql)
                ->execute(array(':version' => $migration->getVersion()));

        return $this;
    }


    /**
     * Is the schema ready?
     *
     * @return bool
     */
    public function hasSchema()
    {
        // get the appropriate query
        //
        $sql = $this->getQuery('hasSchema');

        // get the list of tables
        //
        $tables = $this->connection->query($sql);

        // loop through the list of tables
        //
        while($table = $tables->fetchColumn()) {
            // did we find the table we're looking for? if so, return true
            //
            if ($table == $this->tableName) {
                return true;
            }
        }

        // we made it all the way through the list of tables without finding the
        // one we're looking for. Return false.
        //
        return false;
    }


    /**
     * Create Schema
     *
     * @return DBAL
     */
    public function createSchema()
    {
        // get the appropriate query
        //
        $sql = $this->getQuery('createSchema');

        // execute the query
        //
        $this->connection->exec($sql);

        return $this;
    }

    /**
     * Get the appropriate query for the PDO driver
     *
     * At present, only queries for sqlite, mysql, & pgsql are specified; if a
     * different PDO driver is used, the mysql/pgsql queries will be returned,
     * which may or may not work for the given database.
     *
     * @param string $type
     * The type of the query to retrieve
     *
     * @return string
     */
    protected function getQuery($type)
    {
        // the list of queries
        //
        $queries = array();

        switch($this->pdoDriverName)
        {
            case 'sqlite':
                $queries = array(

                        'fetchAll'     => "SELECT `version` FROM {$this->quotedTableName()} ORDER BY `version` ASC",

                        'up'           => "INSERT INTO {$this->quotedTableName()} VALUES (:version);",

                        'down'         => "DELETE FROM {$this->quotedTableName()} WHERE version = :version",

                        'hasSchema'    => "SELECT `name` FROM `sqlite_master` WHERE `type`='table';",

                        'createSchema' => "CREATE table {$this->quotedTableName()} (`version` NOT NULL);",

                    );
                break;

            case 'mysql':
            case 'pgsql':
            default:
                $queries = array(

                        'fetchAll'     => "SELECT `version` FROM {$this->quotedTableName()} ORDER BY `version` ASC",

                        'up'           => "INSERT into {$this->quotedTableName()} set version = :version",

                        'down'         => "DELETE from {$this->quotedTableName()} where version = :version",

                        'hasSchema'    => "SHOW TABLES;",

                        'createSchema' => "CREATE TABLE {$this->quotedTableName()} (`version` VARCHAR(255) NOT NULL);",

                    );
                break;
        }

        // is the type listed in the queries array? if not, thrown an exception
        //
        if(!array_key_exists($type, $queries))
            throw new \InvalidArgumentException("Query type not found: '{$type}'");

        // it's a request for something else. Let the parent class handle it
        //
        return $queries[$type];
    }
}

