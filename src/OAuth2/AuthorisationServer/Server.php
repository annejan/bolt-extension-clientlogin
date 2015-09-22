<?php

namespace Bolt\Extension\Bolt\ClientLogin\OAuth2\AuthorisationServer;

/**
 * Local OAuth server manager.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Server
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // Resource server
        $sessionStorage = new Storage\SessionStorage();
        $accessTokenStorage = new Storage\AccessTokenStorage();
        $clientStorage = new Storage\ClientStorage();
        $scopeStorage = new Storage\ScopeStorage();

        $server = new ResourceServer(
            $sessionStorage,
            $accessTokenStorage,
            $clientStorage,
            $scopeStorage
        );

        // Authorization server
        $server->setSessionStorage(new Storage\SessionStorage);
        $server->setAccessTokenStorage(new Storage\AccessTokenStorage);
        $server->setRefreshTokenStorage(new Storage\RefreshTokenStorage);
        $server->setClientStorage(new Storage\ClientStorage);
        $server->setScopeStorage(new Storage\ScopeStorage);
        $server->setAuthCodeStorage(new Storage\AuthCodeStorage);
    }
}
