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
    public $token;

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

        $this->getToken();
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
        $providerName = ucwords(strtolower($request->query->get('provider', '')));
        $config = $this->config['providers'];

        if (empty($providerName)) {
            return new Response('<pre>Provider not given</pre>', Response::HTTP_BAD_REQUEST);
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
        // Check for extisting token
        if ($this->doCheckLogin()) {
            $records = new ClientRecords($this->app);

            $this->getToken();
            $records->getUserProfileBySession($this->token);

            // Event dispatcher
            if ($this->app['dispatcher']->hasListeners('clientlogin.Login')) {
                $event = new ClientLoginEvent($records->user, $records->getTableNameProfiles());
                $this->app['dispatcher']->dispatch('clientlogin.Login', $event);
            }

            return new RedirectResponse($this->getRedirectUrl());
        }

        // Set up chosen provider
        $this->setProvider($providerName);

        // If we don't have an authorization code then get one
        if (empty($this->app['request']->get('code'))) {
            $this->clearToken();

            return new RedirectResponse($this->provider->getAuthorizationUrl());
        }

        // Given state must match previously stored one to mitigate CSRF attack
        $stateRequest = $this->app['request']->get('state');
        $stateSession = $this->app['session']->get(Session::TOKENNAME);
        if (empty($stateRequest) || $stateRequest !== $stateSession) {
            $this->clearToken();

            return new Response(null, Response::HTTP_FORBIDDEN);
        }

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

            // User has either just been created or has no token, set it
            $this->setToken();

            // Create the session if need be
            if (!$records->getUserProfileBySession($this->token)) {
                $records->doCreateUserSession($this->token);
            }

            // Add frontend role if set up
            if (!empty($this->config['role'])) {
                $this->setUserRole();
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
        $this->getToken();

        // Remove HA sessions

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
        if (empty($this->getToken())) {
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
    public function getToken()
    {
        return $this->token = $this->app['session']->get(self::TOKENNAME);
    }

    /**
     * Set the user's session
     */
    private function setToken()
    {
        $this->app['session']->set(self::TOKENNAME, $this->provider->state);
    }

    /**
     * Clean out the user's session
     */
    private function clearToken()
    {
        $this->app['session']->remove(self::TOKENNAME);
    }

    /**
     * Set configured frontend role.  Should match one from permissions.yml
     */
    private function setUserRole()
    {
        // Safe-guard against the 'root' role being applied
        if ($this->config['role'] == 'root') {
            return;
        }

        if (empty($this->app['users']->currentuser)) {
            $this->app['users']->currentuser = array('roles' => array(
                $this->config['role'],
                'everyone'));
        } else {
            if (!isset($this->app['users']->currentuser['roles'][$this->config['role']])) {
                array_push($this->app['users']->currentuser['roles'], $this->config['role']);
            }
        }
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
        $key = 'hauth.done';
        return $this->app['resources']->getUrl('rooturl') . $this->config['basepath'] . "/endpoint?$key=$providerName";
    }
}
