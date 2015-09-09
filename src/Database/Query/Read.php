<?php

namespace Bolt\Extension\Bolt\ClientLogin\Database\Query;

/**
 * Client profile table queries.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Read extends QueryBase
{
    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryFetchById()
    {
        return $this->getQueryBuilder()
            ->select('*')
            ->from($this->tableName)
            ->where('id = :id')
            ->orderBy('lastupdate', 'DESC')
        ;
    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryFetchByResource()
    {
        return $this->getQueryBuilder()
            ->select('*')
            ->from($this->tableName)
            ->where('provider  = :provider')
            ->andWhere('resource_owner_id  = :resource_owner_id')
            ->orderBy('lastupdate', 'DESC')
        ;
    }
}
