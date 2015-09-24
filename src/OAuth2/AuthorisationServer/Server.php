<?php

namespace Bolt\Extension\Bolt\ClientLogin\OAuth2\AuthorisationServer;

use Doctrine\DBAL\Driver\Connection;
use League\OAuth2\Server\ResourceServer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Local OAuth server manager.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Server
{
    /** @var \Doctrine\DBAL\Driver\Connection */
    protected $db;
    /** @var \Symfony\Component\HttpFoundation\Session\SessionInterface */
    protected $session;
    /** @var \League\OAuth2\Server\ResourceServer */
    protected $resourceServer;

    /**
     * Constructor.
     *
     * @param Connection       $db
     * @param SessionInterface $session
     */
    public function __construct(Connection $db, SessionInterface $session)
    {
        $this->db = $db;
        $this->session = $session;

        $this->setResourceServer();
    }

    /**
     * Set up an OAuth2 Resource Server.
     */
    protected function setResourceServer()
    {
        $sessionStorage = new Storage\SessionStorage();
        $accessTokenStorage = new Storage\AccessTokenStorage();
        $clientStorage = new Storage\ClientStorage();
        $scopeStorage = new Storage\ScopeStorage();

        $this->resourceServer = new ResourceServer(
            $sessionStorage,
            $accessTokenStorage,
            $clientStorage,
            $scopeStorage
        );
    }
}
