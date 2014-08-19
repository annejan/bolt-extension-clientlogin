<?php

namespace ClientLogin;

/**
 * Social Login with OAuth via HybridAuth
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Extension extends \Bolt\BaseExtension
{
    /**
     * @var unknown
     */
    const NAME = 'ClientLogin';

    /**
     * @var ClientLogin\Controller
     */
    private $controller;

    public function getName()
    {
        return Extension::NAME;
    }

    public function initialize()
    {
        /*
         * Config - sane defaults
         */
        if (empty($this->config['basepath'])) {
            $this->config['basepath'] = "oauth";
        }
        if (empty($this->config['template']['profile'])) {
            $this->config['template']['profile'] = "_profile.twig";
        }
        if (empty($this->config['template']['button'])) {
            $this->config['template']['button'] = "_button.twig";
        }

        /*
         * Backend
         */
        if ($this->app['config']->getWhichEnd() == 'backend') {
            // Check & create database tables if required
            $records = new ClientRecords($this->app);
            $records->dbCheck();
        }

        /*
         * Frontend
         */
        if ($this->app['config']->getWhichEnd() == 'frontend') {
            // If debug is set, also set the path for the debug log.
            if ($this->config['debug_mode']) {
                $this->config['debug_file'] = $this->app['resources']->getPath('cache') . "/authenticate.log";
                @touch($this->config['debug_file']);
            }

            // Set up routes
            $this->setController();

            // Twig functions
            $this->app['twig']->addExtension(new ClientLoginTwigExtensions($this->app));
        }
    }

    private function setController()
    {
        // Create controller object
        $this->controller = new Controller($this->app);

        // Member login
        $this->app->match("{$this->config['basepath']}/login", array($this->controller, 'getAuthenticationLogin'))
                  ->bind('getAuthenticationLogin')
                  ->method('GET');

        // Member logout
        $this->app->match("{$this->config['basepath']}/logout", array($this->controller, 'getAuthenticationLogout'))
                  ->bind('getAuthenticationLogout')
                  ->method('GET');

        // OAuth callback URI
        if (isset($this->config['openid']) && $this->config['openid'] == true) {
            $this->app->match("{$this->config['basepath']}/endpoint", array($this->controller, 'getAuthenticationEndpoint'))
                      ->bind('getAuthenticationEndpoint')
                      ->method('GET|POST');
        }
    }
}
