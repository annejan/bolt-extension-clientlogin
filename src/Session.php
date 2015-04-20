<?php

namespace Bolt\Extension\Bolt\ClientLogin;

use Bolt\Application;
use Bolt\Extension\Bolt\ClientLogin\Event\ClientLoginEvent;
use Bolt\Extension\Bolt\ClientLogin\Exception\ProviderException;
use Hautelook\Phpass\PasswordHash;
use Ivory\HttpAdapter\GuzzleHttpHttpAdapter;
use League\OAuth2\Client\Exception\IDPException;
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

    /** @var \Symfony\Component\HttpFoundation\Response */
    private $response;

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app     = $app;
        $this->config  = $this->app[Extension::CONTAINER]->config;
    }

    /**
     * Get the final response object that will be returned to the request
     *
     * @return Response
     */
    public function getResponse()
    {
        if ($this->response instanceof Response) {
            return $this->response;
        }

        throw new \UnexpectedValueException('Invalid reponse object set for ClientLogin session.');
    }

    /**
     * Set a reponse object
     *
     * @param Response
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;
    }

    /**
     * Do OAuth login authentication
     *
     * @param Request $request
     * @param string  $returnpage
     */
    public function doLogin(Request $request, $returnpage)
    {
        $providerName = $this->getProviderName($request);
        $config = $this->config['providers'];

        if (empty($providerName)) {
            $this->setResponse(new Response('<pre>Provider not given</pre>', Response::HTTP_BAD_REQUEST));
        } elseif ($user = $this->isLoggedIn()) {
            // Check for extisting token
            $this->setResponse(new RedirectResponse($returnpage));

            // Event dispatcher
            $this->dispatchEvent('clientlogin.Login', $user);
        } elseif ($providerName === 'Password' && $config['Password']['enabled']) {
            $this->loginPassword($returnpage);
        } elseif ($config[$providerName]['enabled']) {
            $this->loginOAuth($providerName);
        } else {
            $this->setResponse(new Response('<pre>Error: Invalid or disabled provider</pre>', Response::HTTP_FORBIDDEN));
        }
    }

    /**
     * Do password login authentication
     *
     * @param string $returnpage
     */
    private function loginPassword($returnpage)
    {
        $formFields = FormFields::Password();
        $this->app['boltforms']->makeForm('password', 'form', [], []);
        $this->app['boltforms']->addFieldArray('password', $formFields['fields']);
        $message = '';

        if ($this->app['request']->getMethod() === 'POST') {
            // Validate the form data
            $formdata = $this->app['boltforms']->handleRequest('password');

            // Validate password data
            if ($formdata) {
                return $this->loginCheckPassword($formdata, $returnpage);
            }
        }

        $fields = $this->app['boltforms']->getForm('password')->all();
        $twigvalues = [
            'parent'  => $this->config['template']['password_parent'],
            'fields'  => $fields,
            'message' => $message
        ];

        // Render the Twig_Markup
        $html = $this->app['boltforms']->renderForm('password', $this->config['template']['password'], $twigvalues);

        $this->setResponse(new Response($html, Response::HTTP_OK));
    }

    /**
     * Check the password login data
     *
     * @param array $formdata
     */
    private function loginCheckPassword($formdata, $redirectUrl)
    {
        if (empty($formdata['username']) || empty($formdata['password'])) {
            return $this->setResponse(new Response('No password data given', Response::HTTP_FORBIDDEN));
        }

        // @TODO
        // As with OAuth sessions we need to handle registration, for now we
        // just create a profile
        if (!$clientDetails = $this->app['clientlogin.db']->getUserProfileByIdentifier($formdata['username'], 'Password')) {
            $clientDetails = Client::createPasswordAuth($formdata['username'], $formdata['password']);
        }

        $hasher = new PasswordHash(12, true);
        if (!$hasher->CheckPassword($formdata['password'], $clientDetails->password)) {
            return $this->setResponse(new Response('Password invalid.', Response::HTTP_FORBIDDEN));
        }

        $this->loginComplete('Password', $clientDetails, $redirectUrl, '{}');
    }

    /**
     * Do OAuth login authentication
     *
     * @param string Provider name to authenticate with
     */
    private function loginOAuth($providerName)
    {
        // Set up chosen provider
        $this->setProvider($providerName);

        // Save the current provider state token
        $token = $this->setToken(self::TOKEN_STATE);

        // Get the provider authorisation URL
        $url = $this->provider->getAuthorizationUrl(['state' => $token]);

        $this->setResponse(new RedirectResponse($url));
    }

    /**
     * Check the OAuth callback
     *
     * @param Request $request
     * @param string  $redirectUrl
     */
    public function loginCheckOAuth(Request $request, $redirectUrl)
    {
        $providerName = $this->getProviderName($request);

        // Set up chosen provider
        $this->setProvider($providerName);

        try {
            // Try to get an access token (using the authorization code grant)
            $providerToken = $this->provider->getAccessToken('authorization_code', ['code' => $request->get('code')]);

            /** \League\OAuth2\Client\Entity\User */
            $userDetails = $this->provider->getUserDetails($providerToken);

            $clientDetails = new Client();
            $clientDetails->addOAuth2Client($userDetails);

            $this->app['logger.system']->debug('Response from provider received', $userDetails->getArrayCopy());
        } catch (IDPException $e) {
            if ($this->config['debug_mode']) {
                dump($e);
            }

            $this->app['logger.system']->critical('ClientLogin OAuth error: ' . (string) $e, ['event' => 'exception', 'exception' => $e]);

            $response = new Response("The provider $providerName returned an error. Please contact this site's administrator.", Response::HTTP_INTERNAL_SERVER_ERROR);

            return $this->setResponse($response);
        }

        $this->loginComplete($providerName, $clientDetails, $redirectUrl, json_encode($providerToken));
    }

    /**
     * Complete the login process, set the session token and update the database
     * records.
     *
     * @param string $providerName
     * @param Client $clientDetails
     * @param string $redirectUrl
     * @param string $providerToken
     */
    private function loginComplete($providerName, Client $clientDetails, $redirectUrl, $providerToken = null)
    {
        // Set and get a session token
        $sessionToken = $this->setToken(self::TOKEN_SESSION);

        // If user record doesn't exist, create it
        $profilerecord = $this->app['clientlogin.db']->getUserProfileByIdentifier($clientDetails->uid, $providerName);

        if ($profilerecord) {
            $this->app['clientlogin.db']->doUpdateUserProfile($providerName, $clientDetails, $providerToken);
        } else {
            $profilerecord = $this->app['clientlogin.db']->doCreateUserProfile($providerName, $clientDetails, $providerToken);
        }

        // Create the session if need be
        if (!$this->app['clientlogin.db']->getUserProfileBySession($sessionToken)) {
            $this->app['clientlogin.db']->doCreateUserSession($profilerecord, $sessionToken, $providerToken);
        }

        // Set the response now, it might get changed in the event
        $this->setResponse(new RedirectResponse($redirectUrl));

        // Event dispatcher
        $this->dispatchEvent('clientlogin.Login', $profilerecord);
    }

    /**
     * Logout session
     *
     * @param string $redirectUrl
     */
    public function logout($returnpage)
    {
        $token = $this->getToken(self::TOKEN_SESSION);

        if (!$token) {
            return $this->setResponse(new RedirectResponse($returnpage));
        }

        // Get user record
        $profilerecord = $this->app['clientlogin.db']->getUserProfileBySession($token);

        // Remove session from database
        $this->app['clientlogin.db']->doRemoveSession($token);

        // Remove token
        $this->removeToken(self::TOKEN_SESSION);

        // Set the response now, it might get changed in the event
        $this->setResponse(new RedirectResponse($returnpage));

        // Event dispatcher
        if ($profilerecord) {
            $this->dispatchEvent('clientlogin.Logout', $profilerecord);
        }
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
    public function isLoggedIn()
    {
        // Get client token
        $token = $this->getToken(self::TOKEN_SESSION);
        if (empty($token)) {
            $this->app['logger.system']->debug('No token for ' .  self::TOKEN_SESSION);

            return false;
        }

        // See if there is matching record, i.e. valid, unrevoked, token
        if ($profile = $this->app['clientlogin.db']->getUserProfileBySession($token)) {
            $this->app['logger.system']->debug("Validiated profile token '$token'");

            return $profile;
        } else {
            $this->app['logger.system']->debug("No valid profile found for token '$token'");

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
        $token = $this->app['clientlogin.session.handler']->get($tokenName);;

        $this->app['logger.system']->debug("Getting '$tokenName' token. Value: '$token'.");

        return $token;
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

        $this->app['logger.system']->debug("Setting '$tokenName' token. Value: '$token'");

        $this->app['clientlogin.session.handler']->set($tokenName, $token);

        if (empty($this->getToken($tokenName))) {
            throw new \Exception('[ClientLogin] Unable to create a Symfony session token!');
        }

        return $token;
    }

    /**
     * Remove a $_SESSION[] token
     *
     * @param string $tokenName
     */
    public function removeToken($tokenName)
    {
        $this->app['logger.system']->debug("Clearing '$tokenName' token.");

        $this->app['clientlogin.session.handler']->remove($tokenName);
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
            $this->app['logger.system']->error("Mismatch of state token '$state' against saved '$stateToken'");

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
        $this->app['logger.system']->debug("Creating provider $providerName");

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
     * @param Client $user
     */
    private function dispatchEvent($type, Client $user)
    {
        if ($this->app['dispatcher']->hasListeners($type)) {
            $tablename = $this->app['clientlogin.db']->getTableNameProfiles();
            $event     = new ClientLoginEvent($user, $tablename);

            try {
                $this->app['dispatcher']->dispatch($type, $event);
            } catch (\Exception $e) {
                if ($this->config['debug_mode']) {
                    dump($e);
                }

                $this->app['logger.system']->critical('ClientLogin event dispatcher had an error', ['event' => 'exception', 'exception' => $e]);
            }
        }
    }
}
