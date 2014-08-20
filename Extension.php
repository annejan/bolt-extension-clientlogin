<?php

namespace Bolt\Extension\ClientLogin;

use Bolt\CronEvents;

/**
 * Login with OAuth via HybridAuth
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Extension extends \Bolt\BaseExtension
{
    /**
     * @var string Extension name
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
         * Config
         */
        $this->setConfig();

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

            // Create and store session
            $this->app['extensions.' . Extension::NAME]->session = new Session($this->app);

            // Set up routes
            $this->setController();

            // Twig functions
            $this->app['twig']->addExtension(new ClientLoginTwigExtensions($this->app));
        }

        /*
         * Scheduled cron listener
         */
        $this->app['dispatcher']->addListener(CronEvents::CRON_DAILY, array($this, 'cronDaily'));
    }

    public function cronDaily()
    {
        $record = new ClientRecords($this->app);
        $record->doRemoveSessionsOld();
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

    private function setConfig()
    {
        // Sane defaults
        if (empty($this->config['basepath'])) {
            $this->config['basepath'] = "authenticate";
        }
        if (empty($this->config['template']['profile'])) {
            $this->config['template']['profile'] = "_profile.twig";
        }
        if (empty($this->config['template']['button'])) {
            $this->config['template']['button'] = "_button.twig";
        }

        // Set HybridAuth
        $this->config['auth']['hybridauth'] = $this->config['providers'];
        unset($this->config['auth']['hybridauth']['Password']);

        // Password auth
        $this->config['auth']['password'] = $this->config['providers']['Password'];
    }
}
