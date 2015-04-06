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

    /** @var \Bolt\Application */
    private $app;

    /** @var array Extension config */
    private $config;

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
     * @param string  $returnpage
     *
     * @return Response
     */
    public function doLogin(Request $request, $returnpage)
    {
        $providerName = $this->getProviderName($request);
        $config = $this->config['providers'];

        if (empty($providerName)) {
            return new Response('<pre>Provider not given</pre>', Response::HTTP_BAD_REQUEST);
        }

        // Check for extisting token
        if ($user = $this->doCheckLogin()) {
            // Event dispatcher
            $this->dispatchEvent('clientlogin.Login', $user);

            return new RedirectResponse($returnpage);
        }

        if ($providerName === 'Password' && $config['Password']['enabled']) {
            return $this->doLoginPassword();
        } elseif ($config[$providerName]['enabled']) {
            return $this->doLoginOAuth($providerName);
        } else {
            return new Response('<pre>Error: Invalid or disabled provider</pre>', Response::HTTP_FORBIDDEN);
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

            $clientDetails = new ClientDetails();
            $clientDetails->addOAuth2Client($userDetails);
        } catch (IDPException $e) {
            if ($this->config['debug_mode']) { dump($e); }

            $this->app['logger.system']->critical('ClientLogin OAuth error: ' . (string) $e, ['event' => 'exception', 'exception' => $e]);

            return new Response("The provider $providerName returned an error. Please contact this site's administrator.", Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->doCompleteLogin($providerName, $clientDetails, $redirectUrl, json_encode($providerToken));
    }

    /**
     * Complete the login process, set the session token and update teh database
     * records.
     *
     * @param string        $providerName
     * @param ClientDetails $clientDetails
     * @param string        $redirectUrl
     * @param string        $providerToken
     *
     * @return Response
     */
    private function doCompleteLogin($providerName, ClientDetails $clientDetails, $redirectUrl, $providerToken = null)
    {
        // Set and get a session token
        $sessionToken = $this->setToken(self::TOKEN_SESSION);

        // If user record doesn't exist, create it
        $profilerecord = $this->app['clientlogin.records']->getUserProfileByName($clientDetails->name, $providerName);

        if ($profilerecord) {
            $this->app['clientlogin.records']->doUpdateUserProfile($providerName, $clientDetails, $providerToken);
        } else {
            $profilerecord = $this->app['clientlogin.records']->doCreateUserProfile($providerName, $clientDetails, $providerToken);
        }

        // Create the session if need be
        if (!$this->app['clientlogin.records']->getUserProfileBySession($sessionToken)) {
            $this->app['clientlogin.records']->doCreateUserSession($profilerecord, $sessionToken, $providerToken);
        }

        // Event dispatcher
        $this->dispatchEvent('clientlogin.Login', $profilerecord);

        return new RedirectResponse($redirectUrl);
    }

    /**
     * Logout session
     *
     * @param string  $redirectUrl
     *
     * @return RedirectResponse
     */
    public function doLogout($returnpage)
    {
        $token = $this->getToken(self::TOKEN_SESSION);

        if (!$token) {
            return new RedirectResponse($returnpage);
        }

        // Get user record
        $profilerecord = $this->app['clientlogin.records']->getUserProfileBySession($token);

        // Remove session from database
        $this->app['clientlogin.records']->doRemoveSession($token);

        // Remove token
        $this->removeToken(self::TOKEN_SESSION);

        // Event dispatcher
        if ($profilerecord) {
            $this->dispatchEvent('clientlogin.Logout', $profilerecord);
        }

        return new RedirectResponse($returnpage);
    }

    /**
     * Check if a visitor is logged in by session token.
     *
     * If session token doesn't exist we assume the user is not logged in.
     *
     * If session token does exist, we check for a valid database record, no
     * record means the token has been revoked by the site administrator.
     *
     * @return array|boolean The user profile or FALSE
     */
    public function doCheckLogin()
    {
        // Get client token
        $token = $this->getToken(self::TOKEN_SESSION);
        if (empty($token)) {
            return false;
        }

        // See if there is matching record, i.e. valid, unrevoked, token
        if ($profile = $this->app['clientlogin.records']->getUserProfileBySession($token)) {
            return $profile;
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

    /**
     * Dispatch event to any listeners.
     *
     * @param string $type Either 'clientlogin.Login' or 'clientlogin.Logout'
     * @param array  $user
     */
    private function dispatchEvent($type, array $user)
    {
        if ($this->app['dispatcher']->hasListeners($type)) {
            $tablename = $this->app['clientlogin.records']->getTableNameProfiles();
            $event     = new ClientLoginEvent($user, $tablename);

            try {
                $this->app['dispatcher']->dispatch($type, $event);
            } catch (\Exception $e) {
                if ($this->config['debug_mode']) { dump($e); }
                $this->app['logger.system']->critical('ClientLogin event dispatcher had an error', ['event' => 'exception', 'exception' => $e]);
            }
        }
    }
}
