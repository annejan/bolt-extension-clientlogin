<?php

namespace Bolt\Extension\Bolt\ClientLogin\Authorisation\Handler;

use Bolt\Application;
use Bolt\Extension\Bolt\ClientLogin\Authorisation\Manager;
use Bolt\Extension\Bolt\ClientLogin\Authorisation\SessionToken;
use Bolt\Extension\Bolt\ClientLogin\Config;
use Bolt\Extension\Bolt\ClientLogin\Database\RecordManager;
use Bolt\Extension\Bolt\ClientLogin\Event\ClientLoginEvent;
use Bolt\Extension\Bolt\ClientLogin\Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
    /** @var AbstractProvider */
    protected $provider;
    /** @var string */
    protected $providerName;
    /** @var \Symfony\Component\HttpFoundation\Request */
    protected $request;

    /** @var \Bolt\Extension\Bolt\ClientLogin\Config */
    private $config;
    /** @var \Symfony\Component\HttpFoundation\Response */
    private $response;
    /** @var Manager\Token */
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
        $this->tm     = new Manager\Token($app['session'], $app['randomgenerator'], $app['logger.system']);
    }

    /**
     * Check the login.
     *
     * @return boolean
     */
    protected function login($returnpage)
    {
        $provider = $this->getConfig()->getProvider($this->getProviderName());

        if ($provider['enabled'] !== true) {
            throw new Exception\DisabledProviderException();
        }

        if (!$this->app['clientlogin.session']->isLoggedIn($this->request)) {
            return false;
        }

        // Get the user object for the event
        $sessionToken = $this->getTokenManager()->getToken(Manager\Token::TOKEN_ACCESS);

        // Event dispatcher
        $this->dispatchEvent(ClientLoginEvent::LOGIN_POST, $sessionToken);

        // Set user feedback messages
        $this->app['clientlogin.feedback']->set('message', 'Login was successful.');

        return true;
    }

    /**
     * Logout a profile.
     *
     * @param string $returnpage
     *
     * @return Response
     */
    protected function logout($returnpage)
    {
        if ($this->app['clientlogin.session']->isLoggedIn($this->request)) {
            $this->getTokenManager()->removeToken(Manager\Token::TOKEN_ACCESS);
            $this->app['clientlogin.feedback']->set('message', 'Logout was successful.');
        }

        $cookiePaths = $this->getConfig()->getCookiePaths();
        $response = new RedirectResponse($returnpage);
        Manager\Cookie::clearResponseCookies($response, $cookiePaths);

        return $response;
    }

    /**
     * Proceess a profile login validation attempt.
     *
     * @param string $returnpage
     *
     * @return Response
     */
    protected function process($returnpage)
    {
        $accessToken = $this->getAccessToken($this->request);
        $resourceOwner = $this->getProvider()->getResourceOwner($accessToken);

        $profile = $this->getRecordManager()->getProfileByResourceOwnerId($this->getProviderName(), $resourceOwner->getId());
        if ($profile === false) {
            $this->setDebugMessage(sprintf('No profile found for %s ID %s', $this->getProviderName(), $resourceOwner->getId()));
            $this->getRecordManager()->writeProfile('insert', $this->getProviderName(), $accessToken, $resourceOwner);
        } else {
            $this->setDebugMessage(sprintf('Profile found for %s ID %s', $this->getProviderName(), $resourceOwner->getId()));
            $this->getRecordManager()->writeProfile($profile['guid'], $this->getProviderName(), $accessToken, $resourceOwner);
        }

        // Update the session record
        $profile = $this->getRecordManager()->getProfileByResourceOwnerId($this->getProviderName(), $resourceOwner->getId());
        $this->getRecordManager()->writeSession($profile['guid'], $this->getProviderName(), $accessToken);
        $this->getTokenManager()->setAuthToken($profile['guid'], $accessToken);

        $response = new RedirectResponse($returnpage);
        $cookiePaths = $this->getConfig()->getCookiePaths();
        Manager\Cookie::setResponseCookies($response, $accessToken, $cookiePaths);

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
     * Construct the authorisation URL with query parameters.
     *
     * @param string $providerName
     *
     * @return string
     */
    protected function getCallbackUrl($providerName)
    {
        $key = $this->config->get('response_noun');
        $url = $this->app['resources']->getUrl('rooturl') . $this->getConfig()->get('basepath') . "/endpoint?$key=$providerName";
        $this->setDebugMessage("Setting callback URL: $url");

        return $url;
    }

    /**
     * Get a provider class object for the request.
     *
     * @param string $providerName
     *
     * @throws Exception\InvalidProviderException
     *
     * @return AbstractProvider
     */
    protected function getProvider()
    {
        if ($this->provider !== null) {
            return $this->provider;
        }

        $this->setDebugMessage('Creating provider ' .$this->getProviderName());

        /** @var \League\OAuth2\Client\Provider\AbstractProvider $providerClass */
        $providerClass = '\\Bolt\\Extension\\Bolt\\ClientLogin\\OAuth2\\Provider\\' . $this->getProviderName();

        if (!class_exists($providerClass)) {
            throw new Exception\InvalidProviderException(Exception\InvalidProviderException::INVALID_PROVIDER);
        }

        $options = $this->getProviderOptions($this->getProviderName());
        $collaborators = ['httpClient' => $this->app['clientlogin.guzzle']];

        return $this->provider = new $providerClass($options, $collaborators);
    }

    /**
     * Get a corrected provider name form a request
     *
     * @throws Exception\InvalidProviderException
     *
     * @return string
     */
    protected function getProviderName()
    {
        if ($this->providerName !== null) {
            return $this->providerName;
        }

        $provider = $this->request->query->get('provider');

        // Handle BC for old library
        if (empty($provider)) {
            $provider = $this->request->query->get('hauth_done');
        }

        if (empty($provider)) {
            throw new Exception\InvalidProviderException(Exception\InvalidProviderException::INVALID_PROVIDER);
        }

        return $this->providerName = ucwords(strtolower($provider));
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
