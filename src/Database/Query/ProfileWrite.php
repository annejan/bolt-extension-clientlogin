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
            ->insert($this->tableName)
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
     * Generate a v4 UUID.
     *
     * @return string
     */
    protected function getGuidV4()
    {
        $data = openssl_random_pseudo_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Query to update a user profile.
     *
     * @param string                 $provider
     * @param string                 $resourceOwnerId
     * @param AccessToken            $accessToken
     * @param ResourceOwnerInterface $resourceOwner
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryUpdate($provider, $resourceOwnerId, AccessToken $accessToken, ResourceOwnerInterface $resourceOwner)
    {
        return $this->getQueryBuilder()
            ->update($this->tableName)
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

    /**
     * Query to update a profile record's resource ower data.
     *
     * @param string                 $provider
     * @param string                 $resourceOwnerId
     * @param ResourceOwnerInterface $resourceOwner
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryUpdateResourceOwner($provider, $resourceOwnerId, ResourceOwnerInterface $resourceOwner)
    {
        return $this->getQueryBuilder()
            ->update($this->tableName)
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

    /**
     * Query to toggle the "enabled" value for a profile record.
     *
     * @param string  $provider
     * @param string  $resourceOwnerId
     * @param boolean $enable
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function querySetEnable($provider, $resourceOwnerId, $enable)
    {
        return $this->getQueryBuilder()
            ->update($this->tableName)
            ->set('enabled', $enable)
            ->where('provider  = :provider')
            ->andWhere('resource_owner_id  = :resource_owner_id')
        ;
    }
}
