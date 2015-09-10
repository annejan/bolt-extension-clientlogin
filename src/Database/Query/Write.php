<?php

namespace Bolt\Extension\Bolt\ClientLogin\Database\Query;

/**
 * Client profile table queries.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Write extends QueryBase
{
    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryInsert()
    {
        return $this->getQueryBuilder()
            ->insert($this->tableName)
            ->values([
                'provider'          => ':provider',
                'resource_owner_id' => ':resource_owner_id',
                'access_token'      => ':access_token',
                'refresh_token'     => ':refresh_token',
                'expires'           => ':expires',
                'lastupdate'        => ':lastupdate',
                'resource_owner'    => ':resource_owner',
            ])
        ;
    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryUpdate()
    {
        return $this->getQueryBuilder()
            ->update($this->tableName)
            ->set('access_token',   ':access_token')
            ->set('expires',        ':expires')
            ->set('lastupdate',     ':lastupdate')
            ->set('resource_owner', ':resource_owner')
            ->where('provider  = :provider')
            ->andWhere('resource_owner_id  = :resource_owner_id')
        ;
    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryUpdateAccessToken()
    {
        return $this->getQueryBuilder()
            ->update($this->tableName)
            ->set('access_token',   ':access_token')
            ->set('expires',        ':expires')
            ->set('lastupdate',     ':lastupdate')
            ->where('provider = :provider')
            ->andWhere('resource_owner_id = :resource_owner_id')
        ;
    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryUpdateRefreshToken()
    {
        return $this->getQueryBuilder()
            ->update($this->tableName)
            ->set('refresh_token',  ':refresh_token')
            ->set('expires',        ':expires')
            ->set('lastupdate',     ':lastupdate')
            ->where('provider = :provider')
            ->andWhere('resource_owner_id = :resource_owner_id')
        ;
    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryEnable()
    {
        return $this->getQueryBuilder()
            ->update($this->tableName)
            ->set('enabled', true)
            ->where('provider  = :provider')
            ->andWhere('resource_owner_id  = :resource_owner_id')
        ;
    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryDisable()
    {
        return $this->getQueryBuilder()
            ->update($this->tableName)
            ->set('enabled', false)
            ->where('provider  = :provider')
            ->andWhere('resource_owner_id  = :resource_owner_id')
        ;
    }
}
