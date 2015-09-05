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
    public function queryByUserId()
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
    public function queryBySessionId()
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
    public function queryRemoveSession()
    {
        return $this->getQueryBuilder()
            ->delete($this->tableName)
            ->where('session = :session')
        ;
    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryRemoveExpiredSessions()
    {
        return $this->getQueryBuilder()
            ->delete($this->tableName)
            ->where('lastseen <= :maxage')
        ;
    }
}
