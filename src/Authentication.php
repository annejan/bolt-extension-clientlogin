<?php

namespace SocialLogin;

/**
 * Authentication class
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Authentication
{
    /**
     * @var Boolean that is true if session is active
     */
    private $isLoggedIn = false;

    public function __construct()
    {
        //
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
     */
    public function doLogin()
    {
        $this->isLoggedIn = true;
    }

    /**
     * Logout session
     */
    public function doLogout()
    {
        $this->isLoggedIn = false;
    }
}