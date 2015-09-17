<?php

namespace Bolt\Extension\Bolt\ClientLogin\Authorisation;

use Bolt\Extension\Bolt\ClientLogin\Database\RecordManager;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * The login session.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Session
{
    /** @var RecordManager */
    private $recordManager;
    /** @var SessionInterface */
    private $session;
    /** @var RequestStack */
    private $requestStack;
    /** @var LoggerInterface */
    private $logger;

    /**
     * Constructor.
     *
     * @param RecordManager $recordManager
     */
    public function __construct(RecordManager $recordManager, SessionInterface $session, RequestStack $requestStack, LoggerInterface $logger)
    {
        $this->recordManager = $recordManager;
        $this->session = $session;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
    }

    /**
     * Check if a visitor is logged in.
     *
     * @param Request $request
     *
     * @return boolean
     */
    public function isLoggedIn(Request $request = null)
    {
        if (!$this->checkRequest($request)) {
            return false;
        }

        return $this->checkSession($request);
    }

    /**
     * Check a request for a valid handling.
     *
     * @param Request $request
     *
     * @throws \RuntimeException
     *
     * @return boolean
     */
    protected function checkRequest($request)
    {
        if ($request === null) {
            $request = $this->requestStack->getCurrentRequest();
        }

        if ($request === null) {
            throw new \RuntimeException('ClientLogin session provider called outside of request cycle.');
        }

        // If we have a cookie, let's do checks.
        if ($cookie = $request->cookies->get('clientlogin_access_token')) {
            $this->setDebugMessage(sprintf('checkRequest() check found cookie: %s', $cookie));
            return true;
        }

        $this->setDebugMessage('checkRequest() check found no cookie.');
        return false;
    }

    /**
     * Check a session for a valid and not-expired AccessToken.
     *
     * @param Request $request
     *
     * @throws \RuntimeException
     *
     * @return boolean
     */
    protected function checkSession(Request $request)
    {
        // Get the session
        if (!$sessionToken = $this->session->get(TokenManager::TOKEN_ACCESS)) {
            $this->setDebugMessage('checkSession() check found no session key for SessionToken.');

            return false;
        }

        // Check the returned object is valid
        if (!$sessionToken['accessToken'] instanceof AccessToken) {
            throw new \RuntimeException('AccessToken not stored with SessionToken!');
        }

        $cookie = $request->cookies->get('clientlogin_access_token');
        if ($cookie !== $sessionToken->getAccessTokenId()) {
            $this->setDebugMessage('checkSession() cookie and session mismatch.');

            return false;
        }

        //
        if ($sessionToken && $sessionToken['accessToken']->getExpires() > time()) {
            $this->setDebugMessage('checkSession() returns TRUE.');

            return true;
        }

        return false;
    }

    /**
     * Get the RecordManager DI.
     *
     * @return RecordManager
     */
    protected function getRecordManager()
    {
        return $this->recordManager;
    }

    /**
     * Write a debug message to both the debug log and the feedback array.
     *
     * @param string $message
     */
    protected function setDebugMessage($message)
    {
        $this->logger->debug('[ClientLogin][Session]: ' . $message, ['event' => 'extensions']);
    }
}
