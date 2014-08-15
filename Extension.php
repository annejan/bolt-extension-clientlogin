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
     * @var ClientLogin\Controller
     */
    private $controller;

    public function getName()
    {
        return "ClientLogin";
    }

    public function initialize()
    {
        if (empty($this->config['basepath'])) {
            $this->config['basepath'] = "sociallogin";
        }

        if ($this->app['config']->getWhichEnd() == 'backend') {
            // Check & create database tables if required
            $records = new UserRecords($this->app, $this->app->config);
            $records->dbCheck();
        }

        if ($this->app['config']->getWhichEnd() == 'frontend') {
            // If debug is set, also set the path for the debug log.
            if ($this->config['debug_mode']) {
                $this->config['debug_file'] = $this->app['resources']->getPath('cache') . "/authenticate.log";
                @touch($this->config['debug_file']);
            }

            // Set up routes
            $this->setController();

            // Twig functions
            $this->app['twig']->addExtension(new ClientLoginTwigExtensions($this->app, $this->config));
        }
    }

    private function setController()
    {
        // Create controller object
        $this->controller = new Controller($this->app, $this->config);

        // Member login
        $this->app->match("{$this->config['basepath']}/login", array($this->controller, 'getAuthenticationLogin'))
                  ->bind('getAuthenticationLogin')
                  ->method('GET');

        // Member logout
        $this->app->match("{$this->config['basepath']}/logout", array($this->controller, 'getAuthenticationLogout'))
                  ->bind('getAuthenticationLogout')
                  ->method('GET');

        // OAuth callback URI
        $this->app->match("{$this->config['basepath']}/endpoint", array($this->controller, 'getAuthenticationEndpoint'))
                  ->bind('getAuthenticationEndpoint')
                  ->method('GET|POST');
    }
}
