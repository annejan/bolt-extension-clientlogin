<?php

namespace Bolt\Extension\Bolt\ClientLogin\OAuth2\AuthorisationServer;

use Doctrine\DBAL\Driver\Connection;
use League\Event\EventInterface;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use League\OAuth2\Server\Exception\OAuthException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;
use League\OAuth2\Server\Event\ClientAuthenticationFailedEvent;
use League\OAuth2\Server\Event\UserAuthenticationFailedEvent;
use League\OAuth2\Server\Event\SessionOwnerEvent;

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
    /** @var \League\OAuth2\Server\AuthorizationServer */
    protected $authorisationServer;

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

    public function grantAuthorisationCode(Request $request)
    {
        try {
        } catch (OAuthException $e) {
        }

        return new Response();
    }

    /**
     * Set up an OAuth2 Authorization Server.
     */
    protected function setAuthorizationServer()
    {
        $sessionStorage = new Storage\SessionStorage();
        $accessTokenStorage = new Storage\AccessTokenStorage();
        $clientStorage = new Storage\ClientStorage();
        $scopeStorage = new Storage\ScopeStorage();

        $this->authorisationServer = new AuthorizationServer(
            $sessionStorage,
            $accessTokenStorage,
            $clientStorage,
            $scopeStorage
        );

        $this->authorisationServer->addEventListener('error.auth.client', [$this, 'eventErrorAuthClient']);
        $this->authorisationServer->addEventListener('error.auth.user', [$this, 'eventErrorAuthUser']);
        $this->authorisationServer->addEventListener('session.owner', [$this, 'eventSessionOwner']);
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

    /**
     * Emitted when a client fails to authenticate.
     *
     * Listen to this event in order to ban clients that fail to authenticate
     * after 'n' number of attempts
     *
     * @param \League\OAuth2\Server\Event\ClientAuthenticationFailedEvent
     */
    public function eventErrorAuthClient(ClientAuthenticationFailedEvent $event)
    {
        $request = $event->getRequest();
    }

    /**
     * Emitted when a user fails to authenticate.
     *
     * Listen to this event in order to reset passwords or ban users that fail
     * to authenticate after 'n' number of attempts.
     *
     * @param \League\OAuth2\Server\Event\UserAuthenticationFailedEvent
     */
    public function eventErrorAuthUser(UserAuthenticationFailedEvent $event)
    {
        $request = $event->getRequest();
    }

    /**
     * Emitted when a session has been allocated an owner (for example a user or
     * a client).
     *
     * You might want to use this event to dynamically associate scopes to the
     * session depending on the users role or ACL permissions.
     *
     * @param \League\OAuth2\Server\Event\SessionOwnerEvent
     */
    public function eventSessionOwner(SessionOwnerEvent $event)
    {
        $session = $event->getSession();
    }
}
