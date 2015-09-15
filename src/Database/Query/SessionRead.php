<?php

namespace Bolt\Extension\Bolt\ClientLogin\Database\Query;

/**
 * Client session read queries.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class SessionRead extends QueryBase
{
    /**
     * Query to fetch a session based on access token.
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryFetchByAccessToken($cookie)
    {
        return $this->getQueryBuilder()
            ->select('*')
            ->from($this->tableName)
            ->where('token  = :token')
            ->orderBy('expires', 'DESC')
            ->setParameter(':token', $cookie)
        ;
    }

    /**
     * Query to fetch session records by a GUID.
     *
     * @param string $guid
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryFetchByGuid($guid)
    {
        return $this->getQueryBuilder()
            ->select('*')
            ->from($this->tableName)
            ->where('guid  = :guid')
            ->orderBy('expires', 'DESC')
            ->setParameter(':guid', $guid)
        ;
    }
}
