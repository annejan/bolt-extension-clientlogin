<?php

namespace Bolt\Extension\Bolt\ClientLogin\Authorisation\Handler;

use Bolt\Application;
use Bolt\Extension\Bolt\ClientLogin\Authorisation\CookieManager;
use Bolt\Extension\Bolt\ClientLogin\Authorisation\SessionToken;
use Bolt\Extension\Bolt\ClientLogin\Authorisation\TokenManager;
use Bolt\Extension\Bolt\ClientLogin\Config;
use Bolt\Extension\Bolt\ClientLogin\Database\RecordManager;
use Bolt\Extension\Bolt\ClientLogin\Event\ClientLoginEvent;
use Bolt\Extension\Bolt\ClientLogin\Exception;
use Bolt\Extension\Bolt\ClientLogin\Response\SuccessRedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Authorisation control class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
abstract class HandlerBase
{
    /** @var \Bolt\Application */
    protected $app;
    /** @var \Symfony\Component\HttpFoundation\Request */
    protected $request;

    /** @var \Bolt\Extension\Bolt\ClientLogin\Config */
    private $config;
    /** @var \Symfony\Component\HttpFoundation\Response */
    private $response;
    /** @var TokenManager */
    private $tm;

    /**
     * @param Application $app
     */
    public function __construct(Application $app, RequestStack $requestStack)
    {
        if (!$this->request = $requestStack->getCurrentRequest()) {
            throw new Exception\ConfigurationException(sprintf('%s can not be instated outside of the request cycle.', get_class($this)));
        }

        $this->app    = $app;
        $this->config = $app['clientlogin.config'];
        $this->tm     = new TokenManager($app['session'], $app['randomgenerator'], $app['logger.system']);
    }

    /**
     * Check the login.
     *
     * @throws Exception\DisabledProviderException
     *
     * @return Response|null
     */
    protected function login()
    {
        $providerName = $this->app['clientlogin.provider.manager']->getProviderName();
        $provider = $this->getConfig()->getProvider($providerName);

        if ($provider['enabled'] !== true) {
            throw new Exception\DisabledProviderException('Invalid provider setting.');
        }

        if ($this->app['clientlogin.session']->isLoggedIn($this->request)) {
            return new SuccessRedirectResponse('/');;
        }

        // Get the user object for the event
        $sessionToken = $this->getTokenManager()->getToken(TokenManager::TOKEN_ACCESS);

        // Event dispatcher
//$this->dispatchEvent(ClientLoginEvent::LOGIN_POST, $sessionToken);

        // Set user feedback messages
        $this->app['clientlogin.feedback']->set('message', 'Login was route complete, redirecting for authentication.');
    }

    /**
     * Logout a profile.
     *
     * @return Response
     */
    protected function logout()
    {
        if ($this->app['clientlogin.session']->isLoggedIn($this->request)) {
            $this->getTokenManager()->removeToken(TokenManager::TOKEN_ACCESS);
            $this->app['clientlogin.feedback']->set('message', 'Logout was successful.');
        }

        $cookiePaths = $this->getConfig()->getCookiePaths();
        $response = new SuccessRedirectResponse('/');
        CookieManager::clearResponseCookies($response, $cookiePaths);

        return $response;
    }

    /**
     * Proceess a profile login validation attempt.
     *
     * @param string $returnpage
     *
     * @return Response
     */
    protected function process()
    {
        $providerName = $this->app['clientlogin.provider.manager']->getProviderName();
        $accessToken = $this->getAccessToken($this->request);
        $resourceOwner = $this->getProvider()->getResourceOwner($accessToken);

        $profile = $this->getRecordManager()->getAccountByResourceOwnerId($providerName, $resourceOwner->getId());
        if ($profile === false) {
            $this->setDebugMessage(sprintf('No profile found for %s ID %s', $providerName, $resourceOwner->getId()));
            $this->getRecordManager()->writeProfile('insert', $providerName, $accessToken, $resourceOwner);
        } else {
            $this->setDebugMessage(sprintf('Profile found for %s ID %s', $providerName, $resourceOwner->getId()));
            $this->getRecordManager()->writeProfile($profile['guid'], $providerName, $accessToken, $resourceOwner);
        }

        // Update the session record
        $profile = $this->getRecordManager()->getProfileByResourceOwnerId($providerName, $resourceOwner->getId());
        $this->getRecordManager()->writeSession($profile['guid'], $providerName, $accessToken);
        $this->getTokenManager()->setAuthToken($profile['guid'], $accessToken);

        $response = new SuccessRedirectResponse('/');
        $cookiePaths = $this->getConfig()->getCookiePaths();
        CookieManager::setResponseCookies($response, $accessToken, $cookiePaths);

        return $response;
    }

    /**
     * Get the config DI.
     *
     * @return Config
     */
    protected function getConfig()
    {
        return $this->config;
    }

    /**
     * Get the RecordManager DI.
     *
     * @return RecordManager
     */
    protected function getRecordManager()
    {
        return $this->app['clientlogin.records'];
    }

    /**
     * Get the token manager instance.
     *
     * @return Manager\Token
     */
    protected function getTokenManager()
    {
        return $this->tm;
    }

    /**
     * Get a provider class object for the request.
     *
     * @throws Exception\InvalidProviderException
     *
     * @return AbstractProvider
     */
    protected function getProvider()
    {
        return $this->app['clientlogin.provider'];
    }

    /**
     * Get an access token from the OAuth provider.
     *
     * @param Request $request
     *
     * @throws IdentityProviderException
     * @throws Exception\InvalidAuthorisationRequestException
     *
     * @return AccessToken
     */
    protected function getAccessToken(Request $request)
    {
        $code = $request->query->get('code');

        if ($code === null) {
            $this->setDebugMessage('Attempt to get an OAuth2 acess token with an empty code in the request.');

            throw new Exception\InvalidAuthorisationRequestException('No provider access code.');
        }
        $options = ['code' => $code];

        // Try to get an access token using the authorization code grant.
        $accessToken = $this->getProvider()->getAccessToken('authorization_code', $options);
        $this->setDebugMessage('OAuth token received', $accessToken->jsonSerialize());

        return $accessToken;
    }

    /**
     * Write a debug message to both the debug log and the feedback array.
     *
     * @param string $message
     */
    protected function setDebugMessage($message)
    {
        $this->app['logger.system']->debug('[ClientLogin][Handler]: ' . $message, ['event' => 'extensions']);
        $this->app['clientlogin.feedback']->set('debug', $message);
    }

    /**
     * Dispatch event to any listeners.
     *
     * @param string       $type         Either ClientLoginEvent::LOGIN_POST' or ClientLoginEvent::LOGOUT_POST
     * @param SessionToken $sessionToken
     */
    protected function dispatchEvent($type, SessionToken $sessionToken)
    {
        if ($this->app['dispatcher']->hasListeners($type)) {
            $event = new ClientLoginEvent($sessionToken);

            try {
                $this->app['dispatcher']->dispatch($type, $event);
            } catch (\Exception $e) {
                if ($this->config->get('debug_mode')) {
                    dump($e);
                }

                $this->app['logger.system']->critical('ClientLogin event dispatcher had an error', ['event' => 'exception', 'exception' => $e]);
            }
        }
    }
}
