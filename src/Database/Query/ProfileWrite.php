<?php

namespace Bolt\Extension\Bolt\ClientLogin\Database\Query;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;

/**
 * Client profile table queries.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class ProfileWrite extends QueryBase
{
    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryInsert($provider, AccessToken $accessToken, ResourceOwnerInterface $resourceOwner)
    {
        return $this->getQueryBuilder()
            ->insert($this->tableName)
            ->values([
                'provider'          => ':provider',
                'resource_owner_id' => ':resource_owner_id',
                'refresh_token'     => ':refresh_token',
                'expires'           => ':expires',
                'lastupdate'        => ':lastupdate',
                'resource_owner'    => ':resource_owner',
            ])
            ->setParameters([
                'provider'          => $provider,
                'resource_owner_id' => $resourceOwner->getId(),
                'refresh_token'     => $accessToken->getRefreshToken(),
                'expires'           => $accessToken->getExpires(),
                'lastupdate'        => date('Y-m-d H:i:s', time()),
                'resource_owner'    => json_encode($resourceOwner->toArray()),
            ])
        ;
    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryUpdate($provider, AccessToken $accessToken, ResourceOwnerInterface $resourceOwner)
    {
        return $this->getQueryBuilder()
            ->update($this->tableName)
            ->set('expires',        ':expires')
            ->set('lastupdate',     ':lastupdate')
            ->set('resource_owner', ':resource_owner')
            ->where('provider  = :provider')
            ->andWhere('resource_owner_id  = :resource_owner_id')
            ->queryUpdate()
            ->setParameters([
                'provider'          => $provider,
                'resource_owner_id' => $resourceOwner->getId(),
                'expires'           => $accessToken->getExpires(),
                'lastupdate'        => date('Y-m-d H:i:s', time()),
                'resource_owner'    => json_encode($resourceOwner->toArray()),
            ])
        ;
    }

    /**
     * @param boolean $enable
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function querySetEnable($enable)
    {
        return $this->getQueryBuilder()
            ->update($this->tableName)
            ->set('enabled', $enable)
            ->where('provider  = :provider')
            ->andWhere('resource_owner_id  = :resource_owner_id')
        ;
    }
}
