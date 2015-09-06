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
    const TOKEN_ACCESS = 'bolt.clientlogin.token.access';
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
     * @return array|string|null
     */
    public function getToken($tokenName)
    {
        if ($token = $this->session->get($tokenName)) {
            $this->log->debug("Retrieved '$tokenName' token. Value: '$token'.");
        } else {
            $this->log->debug("Token '$tokenName' does not exist.");
        }

        return $token;
    }

    /**
     * Save an password authentication token to the session.
     *
     * @param array $tokenValue
     *
     * @return array
     */
    public function setAuthPasswordToken(array $tokenValue)
    {
        $this->log->debug('Setting seesion password token.');
        $tokenData = [
            'type' => Types::AUTH_PASSWORD,
            'data' => $tokenValue,
        ];

        return $this->setAuthToken($tokenData);
    }

    /**
     * Save an OAuth authentication token to the session.
     *
     * @param AccessToken $tokenValue
     *
     * @return array
     */
    public function setAuthOAuthToken(AccessToken $tokenValue)
    {
        $this->log->debug('Setting seesion OAuth token.');
        $tokenData = [
            'type' => Types::AUTH_OAUTH2,
            'data' => $tokenValue,
        ];

        return $this->setAuthToken($tokenData);
    }

    /**
     * Save an authentication token to the session.
     *
     * @param string $tokenName
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    protected function setAuthToken($tokenData)
    {
        $this->session->set(self::TOKEN_ACCESS, $tokenData);

        // Retrive the saved token to make sure that the Session is working properly
        $token = $this->getToken(self::TOKEN_ACCESS);

        if (empty($token)) {
            throw new \RuntimeException('[ClientLogin] Unable to create a Symfony session token!');
        }

        return $token;
    }

    /**
     * Save a state token to the session.
     *
     * @return string
     */
    public function setStateToken()
    {
        // Create a unique token
        $token = $this->random->generateString(32);

        $this->log->debug(sprintf("Setting '%s' token. Value: '%s'", self::TOKEN_STATE, $token));
        $this->session->set(self::TOKEN_STATE, $token);

        // Retrive the saved token to make sure that the Session is working properly
        $token = $this->getToken(self::TOKEN_STATE);

        if (empty($token)) {
            throw new \RuntimeException('[ClientLogin] Unable to create a Symfony session token!');
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
            $this->log->error('Authorisation request was missing state token.');
            throw new InvalidAuthorisationRequestException('Invalid authorisation request!');
        }

        // Get the stored token
        $stateToken = $this->getToken(self::TOKEN_STATE);

        // Clear the stored token from the session
        $this->removeToken(self::TOKEN_STATE);

        if (empty($stateToken) || $stateToken !== $state) {
            $this->log->error("Mismatch of state token '$state' against saved '$stateToken'");

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
        $this->log->debug("Clearing '$tokenName' token.");
        $this->session->remove($tokenName);
    }
}
