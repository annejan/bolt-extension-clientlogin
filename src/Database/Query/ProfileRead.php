<?php

namespace Bolt\Extension\Bolt\ClientLogin\Database\Query;

/**
 * Client profile table queries.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class ProfileRead extends QueryBase
{
    /**
     * Query to fetch a profile by GUID.
     *
     * @param string $id
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryFetchById($id)
    {
        return $this->getQueryBuilder()
            ->select('*')
            ->from($this->tableName)
            ->where('id = :id')
            ->orderBy('lastupdate', 'DESC')
            ->setParameter(':id', $id)
        ;
    }

    /**
     * Query to fetch a profile by provider and ID.
     *
     * @param string $provider
     * @param string $resourceOwnerId
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryFetchByResourceOwnerId($provider, $resourceOwnerId)
    {
        return $this->getQueryBuilder()
            ->select('*')
            ->from($this->tableName)
            ->where('provider = :provider')
            ->andWhere('resource_owner_id = :resource_owner_id')
            ->orderBy('lastupdate', 'DESC')
            ->setParameter(':provider', $provider)
            ->setParameter(':resource_owner_id', $resourceOwnerId)
        ;
    }
}
