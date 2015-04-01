<?php

namespace Bolt\Extension\Bolt\ClientLogin;

use Bolt\Events\CronEvent;
use Bolt\Events\CronEvents;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * Login with OAuth via HybridAuth
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 *
 * Based on the Bolt 1.5 extension 'Authenticate' by:
 * @author Lodewijk Evers
 * @author Tobias Dammers
 * @author Bob den Otter
 */
class Extension extends \Bolt\BaseExtension
{
    /** @var string Extension name */
    const NAME = 'ClientLogin';

    /** @var string Extension's container */
    const CONTAINER = 'extensions.ClientLogin';

    /** @var ClientLogin\Controller */
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
         * Register ourselves as a service
         */
        $this->app->register(new Provider\ClientLoginServiceProvider($this->app));

        /*
         * Backend
         */
        if ($this->app['config']->getWhichEnd() === 'backend') {
            // Check & create database tables if required
            $this->app['clientlogin.records']->dbCheck();
        }

        /*
         * Frontend
         */
        if ($this->app['config']->getWhichEnd() === 'frontend') {
            // Twig functions
            $this->app['twig']->addExtension(new ClientLoginTwigExtensions($this->app));
        }

        /*
         * Set up controller routes
         */
        $this->app->mount('/' . $this->config['basepath'], new Controller\ClientLoginController());

        /*
         * Scheduled cron listener
         */
        $this->app['dispatcher']->addListener(CronEvents::CRON_DAILY, array($this, 'cronDaily'));
    }

    /**
     * Cron jobs
     *
     * @param \Bolt\Events\CronEvent $event
     */
    public function cronDaily(CronEvent $event)
    {
        $event->output->writeln("<comment>ClientLogin: Clearing old sessions</comment>");
        $record = new ClientRecords($this->app);
        $record->doRemoveExpiredSessions();
    }

    /**
     * Set up config and defaults
     */
    private function setConfig()
    {
        /*
         * Set HybridAuth
         */

        // Pass the base endpoint URL to HybridAuth
        $this->config['auth']['hybridauth']['base_url'] = $this->app['resources']->getUrl('rooturl') . $this->config['basepath'] . '/endpoint';

        $this->config['auth']['hybridauth']['providers'] = $this->config['providers'];
        unset($this->config['auth']['hybridauth']['providers']['Password']);

        // Apparently "A set of identifiers that identify a setting in the listing". Ok, whatever, HybridAuth.
        $this->config['auth']['hybridauth']['identifier'] = "key";

        // If debug is set, also set the path for the debug log.
        if ($this->config['debug_mode']) {
            $this->config['auth']['hybridauth']['debug_file'] = $this->app['resources']->getPath('cache') . '/authenticate.log';

            $fs = new Filesystem();
            try {
                $fs->touch($this->config['auth']['hybridauth']['debug_file']);
            } catch (IOException $e) {
                $this->app['logger.system']->critical("Unable to create ClientLogin debug file.", array('event' => 'exception', 'exception' => $e));
                $this->config['debug_mode'] = false;
            }
        }

        /*
         * Password auth
         */
        $this->config['auth']['password'] = $this->config['providers']['Password'];
    }

    /**
     * Default config options
     *
     * @return array
     */
    protected function getDefaultConfig()
    {
        return array(
            'providers' => array(
                'Password' => array(
                    'enabled' => false
                ),
                'Google' => array(
                    'enabled' => false
                ),
                'Facebook' => array(
                    'enabled' => false
                ),
                'Twitter' => array(
                    'enabled' => false
                ),
                'GitHub' => array(
                    'enabled' => false
                )
            ),
            'basepath' => 'authenticate',
            'template' => array(
                'profile'  => '_profile.twig',
                'button'   => '_button.twig',
                'password' => '_password.twig'
            ),
            'zocial'       => false,
            'login_expiry' => 14,
            'debug_mode'   => false
        );
    }
}
