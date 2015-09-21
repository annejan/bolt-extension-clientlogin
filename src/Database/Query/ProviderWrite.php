<?php

namespace Bolt\Extension\Bolt\ClientLogin\Database\Query;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;

/**
 * Client profile table queries.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class ProviderWrite extends QueryBase
{
    /**
     * Query to insert a profile record.
     *
     * @param string                 $provider
     * @param string                 $resourceOwnerId
     * @param AccessToken            $accessToken
     * @param ResourceOwnerInterface $resourceOwner
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryInsert($provider, $resourceOwnerId, AccessToken $accessToken, ResourceOwnerInterface $resourceOwner)
    {
        return $this->getQueryBuilder()
            ->insert($this->tableNameProvider)
            ->values([
                'guid'              => ':guid',
                'provider'          => ':provider',
                'resource_owner_id' => ':resource_owner_id',
                'refresh_token'     => ':refresh_token',
                'lastupdate'        => ':lastupdate',
                'resource_owner'    => ':resource_owner',
            ])
            ->setParameters([
                'guid'              => $this->getGuidV4(),
                'provider'          => $provider,
                'resource_owner_id' => $resourceOwnerId,
                'refresh_token'     => $accessToken->getRefreshToken(),
                'lastupdate'        => date('Y-m-d H:i:s', time()),
                'resource_owner'    => json_encode($resourceOwner->toArray()),
            ])
        ;
    }

    /**
     * Query to update a user profile.
     *
     * @param string                 $provider
     * @param string                 $resourceOwnerId
     * @param ResourceOwnerInterface $resourceOwner
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryUpdate($provider, $resourceOwnerId, ResourceOwnerInterface $resourceOwner)
    {
        return $this->getQueryBuilder()
            ->update($this->tableNameProvider)
            ->set('lastupdate',     ':lastupdate')
            ->set('resource_owner', ':resource_owner')
            ->where('provider  = :provider')
            ->andWhere('resource_owner_id  = :resource_owner_id')
            ->setParameters([
                'provider'          => $provider,
                'resource_owner_id' => $resourceOwnerId,
                'lastupdate'        => date('Y-m-d H:i:s', time()),
                'resource_owner'    => json_encode($resourceOwner->toArray()),
            ])
        ;
    }
}
