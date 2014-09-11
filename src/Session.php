<?php

namespace Bolt\Extension\Bolt\ClientLogin;

/**
 * Authentication class
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Session
{
    /**
     * @var string The name of our session cookie
     */
    const TOKENNAME = 'bolt_session_client';

    /**
     * @var string User cookie token
     */
    public $token;

    /**
     * @var Bolt\Application
     */
    private $app;

    /**
     * @var array Extension config array
     */
    private $config;

    /**
     * @var \Hybrid_Auth
     */
    private $hybridauth = false;

    /**
     * Is this a new authentication
     * @var bool
     */
    private $isnewauth = false;

    /**
     * @var \Hybrid_Provider_Adapter
     */
    private $hybridadapter = false;

    /**
     * User profile returned from HybridAuth
     * @var array
     */
    private $hybridprofile;

    /**
     * HybridAuth PHP session information for active logins
     *
     * @var array
     */
    private $hybridsession = false;

    public function __construct(\Bolt\Application $app)
    {
        $this->app = $app;
        $this->config = $this->app['extensions.' . Extension::NAME]->config;

        $this->getToken();
    }

    /**
     * Do OAuth login authentication
     *
     * @param string Provider name to authenticate with
     */
    public function doLoginOAuth($provider)
    {
        // Check for extisting token
        if ($this->doCheckLogin()) {
            return array('result' => true, 'error' => '');
        } else {

            // Attempt a HybridAuth login
            try {

                // Do the HybridAuth dance.
                $this->doHybridAuth($provider);

                if($this->hybridprofile) {
                    $records = new ClientRecords($this->app);

                    // If user record doesn't exist, create it
                    $profilerecord = $records->getUserProfileByName($this->hybridprofile->displayName, $provider);
                    if ($profilerecord) {
                        $records->doUpdateUserProfile($provider, $this->hybridprofile, $this->hybridsession);
                    } else {
                        $records->doCreateUserProfile($provider, $this->hybridprofile, $this->hybridsession);
                    }

                    // User has either just been created or has no token, set it
                    $this->setToken($records->user['id']);

                    // Create the session if need be
                    if (!$records->getUserSessionByToken($records->user['id'])) {
                        $records->doCreateUserSession($this->token);
                    }

                    // Add frontend role if set up
                    if (!empty($this->config['role'])) {
                        $this->setUserRole();
                    }

                    // Success
                    return array('result' => true, 'error' => '');
                } else {
                    return array('result' => false, 'error' => '<pre>ClientLogin Authentication Problem: Please try again!</pre>');
                }
            } catch(Exception $e) {
                $html =  "<pre>Error: please try again!</pre><br>";
                $html .= "<pre>Original error message: " . $e->getMessage() . "</pre>";

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

        // Remove HA sessions
        $this->doHybridAuth(false, 'logout');

        if ($this->token) {
            $records = new ClientRecords($this->app);

            // Remove session from database
            $records->doRemoveSession($this->token);

            // Remove cookies
            $this->app['session']->set(Session::TOKENNAME, null);
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
        $this->getToken();
        if (empty($this->token)) {
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
     * Get the users session cookie
     */
    public function getToken()
    {
        $this->token = $this->app['session']->get(Session::TOKENNAME);
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
        $this->app['session']->set(Session::TOKENNAME, $this->token);
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
     * Login or logout of service provider via HybridAuth
     * @param string $provider
     * @param string $process
     */
    private function doHybridAuth($provider, $process = 'login')
    {
        $hybridconfig = $this->config['auth']['hybridauth'];

        // Get the type early - because we might need to enable it
        if (isset($hybridconfig['providers'][$provider]['type'])) {
            $providertype = $hybridconfig['providers'][$provider]['type'];
        } else {
            $providertype = $provider;
        }

        // Enable OpenID
        if($providertype == 'OpenID' && $hybridconfig['providers'][$provider]['enabled'] == true) {
            $hybridconfig['providers']['OpenID']['enabled'] = true;
        }

        $provideroptions = array();
        if ($providertype == 'OpenID' && !empty($hybridconfig['providers'][$provider]['openid_identifier'])) {
            // Try to authenticate with the selected OpenID provider
            $providerurl = $hybridconfig['providers'][$provider]['openid_identifier'];
            $provideroptions["openid_identifier"] = $providerurl;
        }

        // Initialize the authentication with the modified config
        $this->hybridauth = new \Hybrid_Auth($hybridconfig);

        if ($process == 'login') {
            // See if the user already has valid provider authentication data
            if (! $this->hybridauth->isConnectedWith(strtolower($provider))) {
                // Try to authenticate with the selected provider
                $this->hybridadapter = $this->hybridauth->authenticate($providertype, $provideroptions);
                $this->isnewauth = true;
            } else {
                // Get the provider's adapter
                $this->hybridadapter = $this->hybridauth->getAdapter(strtolower($provider));
            }

            // Get HybridAuth's session data
            $this->hybridsession = unserialize($this->hybridauth->getSessionData());

            // Grab the user profile from HybridAuth
            $this->hybridprofile = $this->hybridadapter->getUserProfile();
        } else {
            $records = new ClientRecords($this->app);
            $records->getUserProfileBySession($this->token);
            $provider = $records->user['provider'];

            // Cancel our session
            if ($this->hybridauth->isConnectedWith($provider)) {
                // Get the provider's adapter
                $this->hybridadapter = $this->hybridauth->getAdapter($provider);

                $this->hybridadapter->logout();
            }
        }
    }
}
