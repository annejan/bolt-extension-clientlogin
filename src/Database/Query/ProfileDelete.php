<?php

namespace Bolt\Extension\Bolt\ClientLogin\Database\Query;

/**
 * Client profile table queries.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class ProfileDelete extends QueryBase
{
    /**
     * @param string $provider
     * @param string $resourceOwnerId
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryDelete($provider, $resourceOwnerId)
    {
        return $this->getQueryBuilder()
            ->delete($this->tableName)
            ->where('provider  = :provider')
            ->andWhere('resource_owner_id  = :resource_owner_id')
            ->setParameter(':provider', $provider)
            ->setParameter(':resource_owner_id', $resourceOwnerId)
        ;
    }
}
