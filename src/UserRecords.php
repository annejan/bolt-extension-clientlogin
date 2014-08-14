<?php

namespace SocialLogin;

use Silex;

/**
 * Authiticated user record maintenance
 */
class UserRecords
{
    /**
     * @var Silex\Application
     */
    private $app;

    /**
     * @var Extension config array
     */
    private $config;

    public function __construct(Silex\Application $app, $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    public function getUserRecord()
    {

    }

    public function setUserRecord()
    {

    }

}