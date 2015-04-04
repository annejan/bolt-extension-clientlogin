<?php

namespace Bolt\Extension\Bolt\ClientLogin;

use Bolt\Application;
use Bolt\Extension\Bolt\ClientLogin\Event\ClientLoginEvent;
use Bolt\Extension\Bolt\ClientLogin\Exception\ProviderException;
use Ivory\HttpAdapter\GuzzleHttpHttpAdapter;
use League\OAuth2\Client\Provider\ProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentication class
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Session
{
    /** @var string The name of our session */
    const TOKENNAME = 'bolt_session_client';

    /** @var string User cookie token */
    private $token;

    /** @var \Bolt\Application */
    private $app;

    /** @var array Extension config */
    private $config;

    /** @var boolean Is this a new authentication */
    private $isnewauth = false;

    /** @var \League\OAuth2\Client\Provider\ProviderInterface */
    private $provider;

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->config = $this->app[Extension::CONTAINER]->config;

        $this->getStateToken();
    }

    /**
     * Do OAuth login authentication
     *
     * @param Request $request
     *
     * @return Response
     */
    public function doLogin(Request $request)
    {
        $providerName = $this->getProviderName($request);
        $config = $this->config['providers'];

        if (empty($providerName)) {
            return new Response('<pre>Provider not given</pre>', Response::HTTP_BAD_REQUEST);
        }

        // Check for extisting token
        if ($this->doCheckLogin()) {
            $records = new ClientRecords($this->app);

            $this->getStateToken();
            $records->getUserProfileBySession($this->token);

            // Event dispatcher
            if ($this->app['dispatcher']->hasListeners('clientlogin.Login')) {
                $event = new ClientLoginEvent($records->user, $records->getTableNameProfiles());
                $this->app['dispatcher']->dispatch('clientlogin.Login', $event);
            }

            return new RedirectResponse($this->getRedirectUrl());
        }

        try {
            if ($providerName === 'Password' && $config['Password']['enabled']) {
                return $this->doLoginPassword();
            } elseif ($config[$providerName]['enabled']) {
                return $this->doLoginOAuth($providerName);
            } else {
                return new Response('<pre>Error: Invalid or disabled provider</pre>', Response::HTTP_FORBIDDEN);
            }
        } catch (\Exception $e) {
            $this->app['logger.system']->critical('ClientLogin had an error processing a login.', ['event' => 'exception', 'exception' => $e]);

            return new Response('', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new Response('', Response::HTTP_FORBIDDEN);
    }

    /**
     * Do OAuth login authentication
     *
     * @param string Provider name to authenticate with
     *
     * @return Response
     */
    public function doLoginOAuth($providerName)
    {
        // Set up chosen provider
        $this->setProvider($providerName);

        // Save the current provider state
        $this->setStateToken();

        // Get the provider authorisation URL
        $url = $this->provider->getAuthorizationUrl(['state' => $this->getStateToken()]);

        return new RedirectResponse($url);
    }

    /**
     * Check the OAuth callback
     *
     * @param Request $request
     * @param string  $url
     *
     * @return Response
     */
    public function doCheckLoginOAuth(Request $request, $url)
    {
        $providerName = $this->getProviderName($request);

        // Set up chosen provider
        $this->setProvider($providerName);

        // Try to get an access token (using the authorization code grant)
        $token = $this->provider->getAccessToken('authorization_code', ['code' => $this->app['request']->get('code')]);

        try {
            // We got an access token, let's now get the user's details
            $userDetails = $this->provider->getUserDetails($token);

            $records = new ClientRecords($this->app);

            // If user record doesn't exist, create it
            $profilerecord = $records->getUserProfileByName($userDetails->name, $providerName);
            if ($profilerecord) {
                $records->doUpdateUserProfile($providerName, $userDetails, $this->provider->state);
            } else {
                $records->doCreateUserProfile($providerName, $userDetails, $this->provider->state);
            }

            // Create the session if need be
            if (!$records->getUserProfileBySession($this->token)) {
                $records->doCreateUserSession($this->token);
            }

            // Event dispatcher
            if ($this->app['dispatcher']->hasListeners('clientlogin.Login')) {
                $event = new ClientLoginEvent($records->user, $records->getTableNameProfiles());
                $this->app['dispatcher']->dispatch('clientlogin.Login', $event);
            }

            return new RedirectResponse($this->getRedirectUrl());
        } catch (\Exception $e) {
            $this->app['logger.system']->critical('ClientLogin had an error processing the user profile.', ['event' => 'exception', 'exception' => $e]);

            return new Response('There was a server error. Please contact the site administrator.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Logout session
     */
    public function doLogout()
    {
        $this->getStateToken();

        if ($this->token) {
            $records = new ClientRecords($this->app);
            $records->getUserProfileBySession($this->token);

            // Remove session from database
            $records->doRemoveSession($this->token);

            // Remove cookies
            $this->app['session']->set(Session::TOKENNAME, null);

            // Event dispatcher
            if ($this->app['dispatcher']->hasListeners('clientlogin.Logout')) {
                $event = new ClientLoginEvent($records->user, $records->getTableNameProfiles());
                $this->app['dispatcher']->dispatch('clientlogin.Logout', $event);
            }
        }
    }

    /**
     * Check if a visitor is logged in by session token
     *
     * If session token doesn't exist we assume the user is not logged in.
     *
     * If session token does exist, we check for a valid database record, no
     * record means the token has been revoked by the site administrator
     *
     * @return bool True if user logged in, False is logged out
     */
    public function doCheckLogin()
    {
        // Get client token
        if (empty($this->getStateToken())) {
            return false;
        }

        // See if there is matching record, i.e. valid, unrevoked, token
        $records = new ClientRecords($this->app);
        if ($records->getUserProfileBySession($this->token)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get the user's session
     *
     * @return string
     */
    private function getStateToken()
    {
        return $this->token = $this->app['session']->get(self::TOKENNAME);
    }

    /**
     * Set the user's session
     */
    private function setStateToken()
    {
        // Create a unique token
        $this->token = $this->app['randomgenerator']->generateString(32);

        $this->app['session']->set(self::TOKENNAME, $this->token);
    }

    /**
     * Clean out the user's session
     */
    public function clearStateToken()
    {
        $this->app['session']->remove(self::TOKENNAME);
    }

    /**
     * Check if a given state matches the saved one
     *
     * @return boolean
     */
    public function checkStateToken($state)
    {
        $stateToken = $this->getStateToken();
        if (empty($state) || empty($stateToken) || $stateToken !== $state) {
            return false;
        }

        return true;
    }

    /**
     * Create the appropriate OAuth provider
     *
     * @param string $providerName
     */
    private function setProvider($providerName)
    {
        /** @var \League\OAuth2\Client\Provider\ProviderInterface */
        $providerClass = '\\League\\OAuth2\\Client\\Provider\\' . $providerName;

        if (!class_exists($providerClass)) {
            throw new ProviderException('Invalid provider.');
        }

        $config = $this->config['providers'][$providerName];
        $config['redirectUri'] = $this->getCallbackUrl($providerName);

        $httpClient = new GuzzleHttpHttpAdapter($this->app['guzzle.client']);
        $this->provider = new $providerClass($config, $httpClient);
    }

    /**
     * Construct the authorisation URL with query parameters
     *
     * @param string $providerName
     *
     * @return string
     */
    private function getCallbackUrl($providerName)
    {
        $key = $this->config['response_noun'];
        return $this->app['resources']->getUrl('rooturl') . $this->config['basepath'] . "/endpoint?$key=$providerName";
    }

    /**
     * Get a corrected provider name form a request
     *
     * @param Request $request
     *
     * @return string
     */
    private function getProviderName(Request $request)
    {
        $provider = $request->query->get('provider');

        // Handle BC for old library
        if (empty($provider)) {
            $provider = $request->query->get('hauth_done');
        }

        if (empty($provider)) {
            throw new ProviderException('Invalid provider.');
        }

        return ucwords(strtolower($provider));
    }
}
