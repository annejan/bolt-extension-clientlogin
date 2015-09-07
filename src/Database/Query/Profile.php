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
            ->where('userid = :userid')
            ->orderBy('lastseen', 'DESC')
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
                'provider'     => ':provider',
                'identifier'   => ':identifier',
                'username'     => ':username',
                'providerdata' => ':providerdata',
                'sessiondata'  => ':sessiondata',
                'lastseen'     => ':lastseen',
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
            ->set('sessiondata', ':sessiondata')
            ->set('lastseen', ':lastseen')
            ->where('provider  = :provider')
            ->andWhere('identifier  = :identifier')
        ;
    }
}
