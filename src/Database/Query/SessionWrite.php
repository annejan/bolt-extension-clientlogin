<?php

namespace Bolt\Extension\Bolt\ClientLogin\Database\Query;

use League\OAuth2\Client\Token\AccessToken;

/**
 * Client session write queries.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class SessionWrite extends QueryBase
{
    /**
     * Insert a session record.
     *
     * @param string      $provider
     * @param string      $resourceOwnerId
     * @param AccessToken $accessToken
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryInsert($provider, $resourceOwnerId, AccessToken $accessToken)
    {
        return $this->getQueryBuilder()
            ->insert($this->tableName)
            ->values([
                'provider'          => ':provider',
                'resource_owner_id' => ':resource_owner_id',
                'access_token'      => ':access_token',
                'access_token_data' => ':access_token_data',
                'expires'           => ':expires',
            ])
            ->setParameters([
                'provider'          => $provider,
                'resource_owner_id' => $resourceOwnerId,
                'expires'           => $accessToken->getExpires(),
            ])
        ;
    }

    /**
     * Update a session record.
     *
     * @param string      $provider
     * @param string      $resourceOwnerId
     * @param AccessToken $accessToken
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryUpdate($provider, $resourceOwnerId, AccessToken $accessToken)
    {
        return $this->getQueryBuilder()
            ->update($this->tableName)
            ->set('access_token', ':access_token')
            ->set('access_token_data', ':access_token_data')
            ->set('expires', ':expires')
            ->where('provider  = :provider')
            ->andWhere('resource_owner_id  = :resource_owner_id')
            ->queryUpdate()
            ->setParameters([
                'provider'          => $provider,
                'resource_owner_id' => $resourceOwnerId,
                'access_token'      => (string) $accessToken,
                'access_token_data' => json_encode($accessToken),
                'expires'           => $accessToken->getExpires(),
            ])
        ;
    }
}
