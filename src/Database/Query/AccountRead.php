<?php

namespace Bolt\Extension\Bolt\ClientLogin\Database\Query;

/**
 * Client account table read queries.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class AccountRead extends QueryBase
{
    /**
     * Query to fetch aa account by GUID.
     *
     * @param string $guid
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryFetchByGuid($guid)
    {
        return $this->getQueryBuilder()
            ->select('*')
            ->from($this->tableNameAccount)
            ->where('guid = :guid')
            ->setParameter(':guid', $guid)
        ;
    }

    /**
     * Query to fetch aa account by resource owner.
     *
     * @param string $provider
     * @param string $resourceOwnerId
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryFetchByResourceOwnerId($resourceOwnerId)
    {
        return $this->getQueryBuilder()
            ->select('*')
            ->from($this->tableNameProvider)
            ->Where('resource_owner_id = :resource_owner_id')
            ->setParameter(':resource_owner_id', $resourceOwnerId)
        ;
    }
}
