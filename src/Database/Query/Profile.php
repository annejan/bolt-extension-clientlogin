<?php

namespace Bolt\Extension\Bolt\ClientLogin\Database\Query;

/**
 * Client profile table queries.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Profile extends QueryBase
{
    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryFetchByUserId()
    {
        return $this->getQueryBuilder()
            ->select('*')
            ->from($this->tableName)
            ->where('provider = :provider')
            ->andWhere('identifier = :identifier')
            ->orderBy('lastupdate', 'DESC')
        ;
    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function insertProfile()
    {
        return $this->getQueryBuilder()
            ->insert($this->tableName)
            ->values([
                'provider'      => ':provider',
                'identifier'    => ':identifier',
                'username'      => ':username',
                'providerdata'  => ':providerdata',
                'providertoken' => ':providertoken',
                'lastupdate'    => ':lastupdate',
            ])
        ;
    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function updateProfile()
    {
        return $this->getQueryBuilder()
            ->update($this->tableName)
            ->set('providerdata', ':providerdata')
            ->set('providertoken', ':providertoken')
            ->set('lastupdate', ':lastupdate')
            ->where('provider  = :provider')
            ->andWhere('identifier  = :identifier')
        ;
    }
}
