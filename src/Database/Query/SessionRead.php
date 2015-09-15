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
            ->from($this->tableNameTokens, 's')
            ->innerJoin('s', $this->tableName, 'p', 's.guid = p.guid')
            ->where('p.guid  = :guid')
            ->orderBy('expires', 'DESC')
            ->setParameter(':guid', $guid)
        ;
    }

    /**
     * Query to fetch a session based on access token.
     *
     * @param string $cookie
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryFetchByAccessToken($cookie)
    {
        return $this->getQueryBuilder()
            ->select('*')
            ->from($this->tableNameTokens, 's')
            ->innerJoin('s', $this->tableName, 'p', 's.guid = p.guid')
            ->where('s.token  = :token')
            ->orderBy('expires', 'DESC')
            ->setParameter(':token', $cookie)
        ;
    }
}
