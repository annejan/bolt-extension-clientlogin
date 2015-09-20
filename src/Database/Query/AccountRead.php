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
            ->from($this->tableNameAccount, 'a')
            ->leftJoin('a', $this->tableNameProvider, 'p', 'a.guid = p.guid')
            ->where('p.resource_owner_id = :resource_owner_id')
            ->andWhere('guid = :guid')
            ->setParameter(':guid', $guid)
        ;
    }

    /**
     * Query to fetch a account by resource owner.
     *
     * @param string $providerName
     * @param string $resourceOwnerId
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryFetchByResourceOwnerId($providerName, $resourceOwnerId)
    {
        return $this->getQueryBuilder()
            ->select('*')
            ->from($this->tableNameAccount, 'a')
            ->leftJoin('a', $this->tableNameProvider, 'p', 'a.guid = p.guid')
            ->where('p.provider = :provider')
            ->andWhere('p.resource_owner_id = :resource_owner_id')
            ->setParameters([
                ':provider'          => $providerName,
                ':resource_owner_id' => $resourceOwnerId,
            ])
        ;
    }
}
