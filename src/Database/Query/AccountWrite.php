<?php

namespace Bolt\Extension\Bolt\ClientLogin\Database\Query;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;

/**
 * Client account table write queries.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class AccountWrite extends QueryBase
{
    /**
     * Query to insert an account record.
     *
     * @param string  $resourceOwnerId
     * @param string  $passwordHash
     * @param boolean $enabled
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryInsert($resourceOwnerId, $passwordHash, $enabled = false)
    {
        return $this->getQueryBuilder()
            ->insert($this->tableNameAccount)
            ->values([
                'guid'              => ':guid',
                'resource_owner_id' => ':resource_owner_id',
                'password'          => ':password',
                'enabled'           => ':enabled',
            ])
            ->setParameters([
                'guid'              => $this->getGuidV4(),
                'resource_owner_id' => $resourceOwnerId,
                'password'          => $passwordHash,
                'enabled'           => $enabled,
            ])
        ;
    }

    /**
     * Query to set and account password.
     *
     * @param string  $guid
     * @param boolean $passwordHash
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function querySetPassword($guid, $passwordHash)
    {
        return $this->getQueryBuilder()
            ->update($this->tableNameAccount)
            ->set('lastupdate',     ':lastupdate')
            ->set('resource_owner', ':resource_owner')
            ->where('provider  = :provider')
            ->andWhere('resource_owner_id  = :resource_owner_id')
            ->setParameters([
                'provider'          => $guid,
                'resource_owner_id' => $passwordHash,
            ])
        ;
    }

    /**
     * Query to toggle the "enabled" value for an account record.
     *
     * @param string  $guid
     * @param boolean $enable
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function querySetEnable($guid, $enable)
    {
        return $this->getQueryBuilder()
            ->update($this->tableNameAccount)
            ->set('enabled', ':enabled')
            ->where('guid  = :gui')
            ->setParameters([
                'guid'    => $guid,
                'enabled' => $enable,
            ])
        ;
    }
}
