<?php

namespace Bolt\Extension\Bolt\ClientLogin\Database\Query;

use Doctrine\DBAL\Connection;

abstract class QueryBase
{
    /** @var \Doctrine\DBAL\Connection */
    protected $db;
    /** @var string */
    protected $tableName;
    /** @var string */
    protected $tableNameTokens;

    public function __construct(Connection $db, $tableName)
    {
        $this->db = $db;
        $this->tableName = $tableName;
        $this->tableNameTokens = $tableName . '_tokens';
    }

    /**
     * Get the database connection
     *
     * @return \Doctrine\DBAL\Connection
     */
    protected function getConnection()
    {
        return $this->db;
    }

    /**
     * Get the database connection
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    protected function getQueryBuilder()
    {
        return $this->db->createQueryBuilder();
    }
}
