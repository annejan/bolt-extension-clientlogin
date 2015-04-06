<?php

namespace Bolt\Extension\Bolt\ClientLogin;

use Bolt\Application;
use Bolt\Extension\Bolt\ClientLogin\Event\ClientLoginEvent;
use Bolt\Extension\Bolt\ClientLogin\Exception\ProviderException;
use Ivory\HttpAdapter\GuzzleHttpHttpAdapter;
use League\OAuth2\Client\Exception\IDPException;
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
    /** @var string */
    const TOKEN_SESSION = 'bolt_clientlogin_session';

    /** @var string */
    const TOKEN_STATE = 'bolt_clientlogin_state';

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
            $this->app['clientlogin.records']->getUserProfileBySession($this->getToken(self::TOKEN_SESSION));

            // Event dispatcher
            if ($this->app['dispatcher']->hasListeners('clientlogin.Login')) {
                $event = new ClientLoginEvent($this->app['clientlogin.records']->user, $this->app['clientlogin.records']->getTableNameProfiles());
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

        // Save the current provider state token
        $token = $this->setToken(self::TOKEN_STATE);

        // Get the provider authorisation URL
        $url = $this->provider->getAuthorizationUrl(['state' => $token]);

        return new RedirectResponse($url);
    }

    /**
     * Check the OAuth callback
     *
     * @param Request $request
     * @param string  $redirectUrl
     *
     * @return Response
     */
    public function doCheckLoginOAuth(Request $request, $redirectUrl)
    {
        $providerName = $this->getProviderName($request);

        // Set up chosen provider
        $this->setProvider($providerName);

        try {
            // Try to get an access token (using the authorization code grant)
            $providerToken = $this->provider->getAccessToken('authorization_code', ['code' => $request->get('code')]);

            /** \League\OAuth2\Client\Entity\User */
            $userDetails = $this->provider->getUserDetails($providerToken);

            // Set/get a session token
            $sessionToken = $this->setToken(self::TOKEN_SESSION);

            $clientDetails = new ClientDetails();
            $clientDetails->addOAuth2Client($userDetails);

            // If user record doesn't exist, create it
            $profilerecord = $this->app['clientlogin.records']->getUserProfileByName($clientDetails->name, $providerName);
            if ($profilerecord) {
                $this->app['clientlogin.records']->doUpdateUserProfile($providerName, $clientDetails, json_encode($providerToken));
            } else {
                $this->app['clientlogin.records']->doCreateUserProfile($providerName, $clientDetails, json_encode($providerToken));
            }

            // Create the session if need be
            if (!$user = $this->app['clientlogin.records']->getUserProfileBySession($sessionToken)) {
                $this->app['clientlogin.records']->doCreateUserSession($clientDetails, $sessionToken, $providerToken);
            }

            // Event dispatcher
            if ($this->app['dispatcher']->hasListeners('clientlogin.Login')) {
                $event = new ClientLoginEvent($user, $this->app['clientlogin.records']->getTableNameProfiles());
                $this->app['dispatcher']->dispatch('clientlogin.Login', $event);
            }

            return new RedirectResponse($redirectUrl);
        } catch (IDPException $e) {
            $this->app['logger.system']->critical('ClientLogin OAuth error: ' . (string) $e, ['event' => 'exception', 'exception' => $e]);

            return new Response('There was a server error. Please contact the site administrator.', Response::HTTP_INTERNAL_SERVER_ERROR);
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
        $token = $this->getToken(self::TOKEN_SESSION);

        if ($token) {
            $this->app['clientlogin.records']->getUserProfileBySession($token);

            // Remove session from database
            $this->app['clientlogin.records']->doRemoveSession($token);

            // Remove token
            $this->removeToken(self::TOKEN_SESSION);

            // Event dispatcher
            if ($this->app['dispatcher']->hasListeners('clientlogin.Logout')) {
                $event = new ClientLoginEvent($this->app['clientlogin.records']->user, $this->app['clientlogin.records']->getTableNameProfiles());
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
        if (empty($this->getToken(self::TOKEN_SESSION))) {
            return false;
        }

        // See if there is matching record, i.e. valid, unrevoked, token
        if ($this->app['clientlogin.records']->getUserProfileBySession($this->getToken(self::TOKEN_SESSION))) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get $_SESSION[] token
     *
     * @param string $tokenName
     *
     * @return string
     */
    public function getToken($tokenName)
    {
        return $this->app['session']->get($tokenName);
    }

    /**
     * Set a $_SESSION[] token
     *
     * @param string $tokenName
     *
     * @return string
     */
    private function setToken($tokenName)
    {
        // Create a unique token
        $token = $this->app['randomgenerator']->generateString(32);

        $this->app['session']->set($tokenName, $token);

        return $token;
    }

    /**
     * Remove a $_SESSION[] token
     *
     * @param string $tokenName
     */
    public function removeToken($tokenName)
    {
        $this->app['session']->remove($tokenName);
    }

    /**
     * Check if a given state matches the saved one
     *
     * @param string $state
     *
     * @return boolean
     */
    public function checkStateToken($state)
    {
        $stateToken = $this->getToken(self::TOKEN_STATE);
        $this->removeToken(self::TOKEN_STATE);

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

        /*
         * Upcoming changes in League\OAuth2 have the following requirement for
         * creating the provider… Be aware that this is really broken currently
         * circa 2015-04-04
         *
         * $httpClient = new GuzzleHttpHttpAdapter($this->app['guzzle.client']);
         * $this->provider = new $providerClass($config, $httpClient);
         */

        $this->provider = new $providerClass($config);
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
