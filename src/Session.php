<?php

namespace ClientLogin;

/**
 * Authentication class
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Session
{
    /**
     * @var The current authenticated site member
     */
    public $member = false;

    /**
     * @var User cookie token
     */
    public $token;

    /**
     * @var Boolean that is true if session is active
     */
    private $isLoggedIn = false;

    /**
     * @var Silex\Application
     */
    private $app;

    /**
     * @var Extension config array
     */
    private $config;

    public function __construct(\Bolt\Application $app, $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * Test if the session is authenticated
     *
     * @return boolean
     */
    public function isLoggedIn()
    {
        return $this->isLoggedIn;
    }

    /**
     * Do login authentication
     *
     * @param string Provider name to authenticate with
     */
    public function doLogin($provider)
    {
        // Check for extisting token
        if ($this->doCheckLogin()) {
            $this->isLoggedIn = true;
            return array('result' => true, 'error' => '');
        } else {

            // Attempt a HybridAuth login
            try {

                // Pass the base endpoint URL to HybridAuth
                $this->config['base_url'] = $this->app['paths']['rooturl'] . $this->config['basepath'] . '/endpoint';

                // Get the type early - because we might need to enable it
                if (isset($this->config['providers'][$provider]['type'])) {
                    $providertype = $this->config['providers'][$provider]['type'];
                } else {
                    $providertype = $provider;
                }

                // Enable OpenID
                if($providertype == 'OpenID' && $this->config['providers'][$provider]['enabled'] == true) {
                    $this->config['providers']['OpenID']['enabled'] = true;
                }

                $provideroptions = array();
                if ($providertype == 'OpenID' && !empty($this->config['providers'][$provider]['openid_identifier'])) {
                    // Try to authenticate with the selected OpenID provider
                    $providerurl = $this->config['providers'][$provider]['openid_identifier'];
                    $provideroptions["openid_identifier"] = $providerurl;
                }

                // Initialize the authentication with the modified config
                $hybridauth = new \Hybrid_Auth($this->config);

                // Try to authenticate with the selected provider
                $adapter = $hybridauth->authenticate($providertype, $provideroptions);

                // Grab the user profile from HybridAuth
                $profile = $adapter->getUserProfile();

                if($profile) {
                    $records = new UserRecords($this->app, $this->config);

                    // If user record doesn't exist, create it
                    if (!$records->getUserProfileByName($profile->displayName, $provider)) {
                        $records->doCreateUserProfile($provider, $profile);
                    }

                    // User has either just been created or has no token, set it
                    $this->setToken($records->user['id']);

                    // Create the session if need be
                    if (!$records->getUserSessionByID($records->user['id'])) {
                        $records->doCreateUserSession($this->token);
                    }

                    // Success
                    $this->isLoggedIn = true;
                    return array('result' => true, 'error' => '');
                } else {
                    $this->isLoggedIn = false;
                    return array('result' => false, 'error' => '<pre>OAuth Error: please try again!<pre>');
                }
            } catch(Exception $e) {
                $html =  "<pre>Error: please try again!<pre><br>";
                $html .= "<pre>Original error message: " . $e->getMessage() . "<pre>";

                return array('result' => false, 'error' => $html);
            }
        }
    }

    /**
     * Logout session
     */
    public function doLogout()
    {
        $this->getToken();

        if ($this->token) {
            $records = new UserRecords($this->app, $this->config);

            $records->doRemoveSession(array('sessiontoken' => $this->token));

            $this->member = false;
            $this->isLoggedIn = false;
        }
    }

    /**
     *
     */
    public function doCheckLogin()
    {
        // If $member is set, we've been here, done this
        if ($this->member) {
            return true;
        }
        // Get client 'sessiontoken' if exists
        $token = $this->app['session']->get('sessiontoken');

        $records = new UserRecords($this->app, $this->config);
        if ($records->getUserProfileBySession($token)) {
            $this->isLoggedIn = true;
            return true;
        } else {
            $this->isLoggedIn = false;
            return false;
        }
    }

    /**
     * Get the users session cookie
     */
    private function getToken()
    {
        $this->token = $this->app['session']->set('sessiontoken', $this->token);
    }

    /**
     * Set the users session cookie
     *
     * @param integer $id
     */
    private function setToken($id)
    {
        // Create a unique token
        $this->token = $this->doCreateToken() . $this->doCreateToken($id);

        // Set session cookie
        $this->app['session']->set('sessiontoken', $this->token);
    }

    /**
     * Create new session token. Should be reasonably unique
     *
     * @param string $key Optional salt for the returned token
     * @return string
     */
    private function doCreateToken($key = null)
    {
        if(!$key) {
            $seed = $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT'] . $_SERVER["REQUEST_TIME"];
        } else {
            $seed = $_SERVER['REMOTE_ADDR'] . $key . $_SERVER["REQUEST_TIME"];
        }
        return md5($seed);
    }
}