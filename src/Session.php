<?php

namespace SocialLogin;

/**
 * Authentication class
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Session
{
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

    public function __construct(Bolt\Application $app, $config)
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
        if ($this->doCheckLogin()) {
            return true;
        } else {

            // Attempt a HybridAuth login
            try {

                // get the type early - because we might need to enable it
                if (isset($this->config['providers'][$provider]['type'])) {
                    $providertype = $this->config['providers'][$provider]['type'];
                } else {
                    $providertype = $provider;
                }

                // enable OpenID
                if($providertype == 'OpenID' && $this->config['providers'][$provider]['enabled'] == true) {
                    $this->config['providers']['OpenID']['enabled'] = true;
                }

                // Initialize the authentication with the modified config
                $hybridauth = new \Hybrid_Auth($this->config);

                $provideroptions = array();
                if ($providertype=='OpenID' && !empty($this->config['providers'][$provider]['openid_identifier'])) {
                    // Try to authenticate with the selected OpenID provider
                    $providerurl = $this->config['providers'][$provider]['openid_identifier'];
                    $provideroptions["openid_identifier"] = $providerurl;
                }
                // Try to authenticate with the selected provider
                $adapter = $hybridauth->authenticate($providertype, $provideroptions);

                // then grab the user profile
                $user_profile = $adapter->getUserProfile();

                if($user_profile) {
                    $records = new UserRecords($this->app, $this->config);









                }
            } catch(Exception $e) {
                $html =  "<pre>Error: please try again!<pre><br>";
                $html .= "<pre>Original error message: " . $e->getMessage() . "<pre>";

                return array('result' => false, 'error' => $html);
            }

            // Success
            $this->isLoggedIn = true;

            return array('result' => true, 'error' => '');
        }
    }

    /**
     * Logout session
     */
    public function doLogout()
    {
        $this->isLoggedIn = false;
    }

    /**
     *
     */
    public function doCheckLogin()
    {
        //$this->isLoggedIn = true;
    }
}