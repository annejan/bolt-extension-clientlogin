<?php

namespace Bolt\Extension\Bolt\ClientLogin\Authorisation;

use League\OAuth2\Client\Token\AccessToken;

/**
 * Token class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Token implements \JsonSerializable
{
    /** @var string */
    protected $provider;
    /** @var string 'resource_owner_id' */
    protected $resourceOwnerId;
    /** @var string 'access_token' */
    protected $accessToken;
    /** @var integer Either 'expires_in' or 'expires' */
    protected $expires;
    /** @var string 'refresh_token' */
    protected $refreshToken;

    /**
     * Constructor.
     *
     * @param string  $provider
     * @param string  $resourceOwnerId
     * @param string  $accessToken
     * @param integer $expires
     * @param string  $refreshToken
     */
    public function __construct($provider, $resourceOwnerId, $accessToken, $expires, $refreshToken)
    {
        $this->provider = $provider;
        $this->resourceOwnerId = $resourceOwnerId;
        $this->accessToken = $accessToken;
        $this->expires = $expires;
        $this->refreshToken = $refreshToken;
    }

    /**
     * Return an OAuth access token object from the classes data.
     *
     * @return \League\OAuth2\Client\Token\AccessToken
     */
    public function createOAuthAccessToken()
    {
        $options = [
            'resource_owner_id' => $this->resourceOwnerId,
            'access_token'      => $this->accessToken,
            'expires'           => $this->expires,
            'refresh_token'     => $this->refreshToken,
        ];

        return new AccessToken($options);
    }

    /**
     * Check to see if the token is expired.
     *
     * @return boolean
     */
    public function hasExpired()
    {
        return $this->expires < time();
    }

    /**
     * Returns the provider of this instance.
     *
     * @return string
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * Returns the access token string of this instance.
     *
     * @return string
     */
    public function getToken()
    {
        return $this->accessToken;
    }

    /**
     * Returns the refresh token, if defined.
     *
     * @return string|null
     */
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    /**
     * Returns the expiration timestamp, if defined.
     *
     * @return integer|null
     */
    public function getExpires()
    {
        return $this->expires;
    }

    /**
     * Returns the resource owner identifier, if defined.
     *
     * @return string|null
     */
    public function getResourceOwnerId()
    {
        return $this->resourceOwnerId;
    }

    public function __toString()
    {
        return (string) $this->accessToken;
    }

    public function jsonSerialize()
    {
        return [
            'provider'        => $this->provider,
            'resourceOwnerId' => $this->resourceOwnerId,
            'accessToken'     => $this->accessToken,
            'expires'         => $this->expires,
            'refreshToken'    => $this->refreshToken,
        ];
    }
}
