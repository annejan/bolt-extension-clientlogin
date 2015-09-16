<?php

namespace Bolt\Extension\Bolt\ClientLogin\Authorisation;

use Bolt\Application;
use Bolt\Extension\Bolt\ClientLogin\Config;
use Bolt\Extension\Bolt\ClientLogin\Exception\InvalidAuthorisationRequestException;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;
use RandomLib\Generator;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Token management class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class TokenManager
{
    /** Session key name of the access token ID */
    const TOKEN_ACCESS = 'bolt.clientlogin.token.access';
    /** Session key name of the state value used on authentication request to upstream */
    const TOKEN_STATE  = 'bolt.clientlogin.token.state';

    /** \Symfony\Component\HttpFoundation\Session\SessionInterface */
    protected $session;
    /** @var \RandomLib\Generator */
    protected $random;
    /** @var \Psr\Log\LoggerInterface */
    protected $log;

    /**
     * Constructor.
     *
     * @param SessionInterface $session
     * @param Generator $random
     * @param LoggerInterface $log
     */
    public function __construct(SessionInterface $session, Generator $random, LoggerInterface $log)
    {
        $this->session = $session;
        $this->random = $random;
        $this->log = $log;
    }

    /**
     * Get a session token.
     *
     * @param string $tokenName
     *
     * @return SessionToken|string|null
     */
    public function getToken($tokenName)
    {
        if ($token = $this->session->get($tokenName)) {
            $this->log->debug("Retrieved '$tokenName' token. Value: '$token'.", ['event' => 'extensions']);
        } else {
            $this->log->debug("Token '$tokenName' does not exist.", ['event' => 'extensions']);
        }

        return $token;
    }

    /**
     * Save an SessionToken to the session.
     *
     * @param Token $tokenName
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public function setAuthToken($guid, AccessToken $accessToken)
    {
        $sessionToken = new SessionToken($guid, $accessToken);
        $this->session->set(self::TOKEN_ACCESS, $sessionToken);
        $this->log->debug(sprintf("Setting '%s' token. Value: '%s'", self::TOKEN_ACCESS, (string) $sessionToken), ['event' => 'extensions']);

        // Retrive the saved token to make sure that the Session is working properly
        $accessToken = $this->getToken(self::TOKEN_ACCESS);

        if ($accessToken instanceof Token) {
            return $accessToken;
        }

        throw new \RuntimeException('[ClientLogin] Unable to create a Symfony session token!');
    }

    /**
     * Save a state token to the session.
     *
     * @param $string
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    public function setStateToken($state)
    {
        if (empty($state)) {
            $this->log->debug('[ClientLogin] Trying to set empty state token!', ['event' => 'extensions']);
            throw new \RuntimeException('Trying to set empty state token!');
        }

        $this->log->debug(sprintf("Setting '%s' token. Value: '%s'", self::TOKEN_STATE, $state), ['event' => 'extensions']);
        $this->session->set(self::TOKEN_STATE, $state);

        // Retrive the saved token to make sure that the Session is working properly
        $token = $this->getToken(self::TOKEN_STATE);

        if (empty($token)) {
            throw new \RuntimeException('Unable to create a Symfony session token!');
        }

        return $token;
    }

    /**
     * Check if a request's state token matches the session one.
     *
     * @param Request $request
     *
     * @throws InvalidAuthorisationRequestException
     *
     * @return boolean
     */
    public function checkStateToken(Request $request)
    {
        $state = $request->get('state');
        if ($state === null) {
            $this->log->error('Authorisation request was missing state token.', ['event' => 'extensions']);
            throw new InvalidAuthorisationRequestException('Invalid authorisation request!');
        }

        // Get the stored token
        $stateToken = $this->getToken(self::TOKEN_STATE);

        // Clear the stored token from the session
        $this->removeToken(self::TOKEN_STATE);

        if (empty($stateToken) || $stateToken !== $state) {
            $this->log->error("Mismatch of state token '$state' against saved '$stateToken'", ['event' => 'extensions']);

            return false;
        }

        return true;
    }

    /**
     * Remove a session token.
     *
     * @param string $tokenName
     */
    public function removeToken($tokenName)
    {
        $this->log->debug("Clearing '$tokenName' token.", ['event' => 'extensions']);
        $this->session->remove($tokenName);
    }
}
