<?php

namespace Bolt\Extension\Bolt\ClientLogin\Database\Query;

/**
 * Client session table queries.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Session extends QueryBase
{
    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryDeleteSession()
    {
        return $this->getQueryBuilder()
            ->delete($this->tableName)
            ->where('session = :session')
        ;
    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryDeleteExpiredSessions()
    {
        return $this->getQueryBuilder()
            ->delete($this->tableName)
            ->where('lastseen <= :maxage')
        ;
    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryFetchByUserId()
    {
        return $this->getQueryBuilder()
            ->select('*')
            ->from($this->tableName)
            ->where('userid = :userid')
            ->orderBy('lastseen', 'DESC')
        ;
    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryFetchBySessionId()
    {
        return $this->getQueryBuilder()
            ->select('*')
            ->from($this->tableName)
            ->where('session = :session')
            ->orderBy('lastseen', 'DESC')
        ;
    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function insertSession()
    {
        return $this->getQueryBuilder()
            ->insert($this->tableName)
            ->values([
                'userid'   => ':userid',
                'session'  => ':session',
                'token'    => ':token',
                'lastseen' => ':lastseen',
            ])
        ;
    }
}
