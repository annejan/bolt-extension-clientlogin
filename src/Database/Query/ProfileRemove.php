<?php

namespace Bolt\Extension\Bolt\ClientLogin\Database\Query;

/**
 * Client profile table queries.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class ProfileRemove extends QueryBase
{
    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryDelete()
    {
        return $this->getQueryBuilder()
            ->delete($this->tableName)
            ->where('provider  = :provider')
            ->andWhere('resource_owner_id  = :resource_owner_id')
        ;
    }
}
