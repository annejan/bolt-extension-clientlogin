<?php

namespace Bolt\Extension\Bolt\ClientLogin;

use Bolt\BaseExtension;
use Bolt\Events\CronEvent;
use Bolt\Events\CronEvents;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Bolt\Extension\Bolt\ClientLogin\Database\Schema;

/**
 * Frontend client login with OAuth2 or passwords.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 *
 * Based on the Bolt 1.5 extension 'Authenticate' by:
 * @author Lodewijk Evers
 * @author Tobias Dammers
 * @author Bob den Otter
 */
class Extension extends BaseExtension
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
        // Configuration set up
        $this->setConfig();

        // Service providers
        $this->app->register(new Provider\ServiceProvider($this->config));
        $this->app['twig']->addExtension(new Twig\ClientLoginExtension($this->app));

        // Check & create database tables if required
        if ($this->app['config']->getWhichEnd() === 'backend' || $this->app['config']->getWhichEnd() === 'cli') {
            $this->app['clientlogin.db.schema']->build();
        }

        // Controller routes
        $this->app->mount('/' . $this->config['basepath'], new Controller\ClientLoginController());

        // Scheduled cron listener
        $this->app['dispatcher']->addListener(CronEvents::CRON_DAILY, [$this, 'cronDaily']);

        // Middleware
        $this->app->before([$this, 'before']);
    }

    /**
     * Before middleware
     */
    public function before()
    {
        // Debug logger
        if ($this->config['debug_mode']) {
            $debuglog = $this->app['resources']->getPath('cache') . '/authenticate.log';
            $this->app['logger.system']->pushHandler(new StreamHandler($debuglog, Logger::DEBUG));
        }
    }

    /**
     * Cron jobs
     *
     * @param \Bolt\Events\CronEvent $event
     */
    public function cronDaily(CronEvent $event)
    {
        $event->output->writeln("<comment>ClientLogin: Clearing old sessions</comment>");
        $this->app['clientlogin.db']->doRemoveExpiredSessions();
    }

    /**
     * Set up config and defaults
     *
     * This has evolved from HybridAuth configuration and we need to cope as such
     */
    private function setConfig()
    {
        // Handle old provider config
        $providersConfig = [];
        foreach ($this->config['providers'] as $provider => $values) {
            // This needs to match the provider class name for OAuth
            $name = ucwords(strtolower($provider));

            // On/off switch
            $providersConfig[$name]['enabled'] = $values['enabled'];

            // Keys
            $providersConfig[$name]['clientId']     = $values['clientId']     ? : $values['keys']['id'];
            $providersConfig[$name]['clientSecret'] = $values['clientSecret'] ? : $values['keys']['secret'];

            // Scopes
            if (isset($values['scopes'])) {
                $providersConfig[$name]['scopes'] = $values['scopes'];
            } elseif (isset($values['scope']) && !isset($values['scopes'])) {
                $providersConfig[$name]['scopes'] = explode(' ', $values['scope']);
            }
        }

        // Handle old debug parameter
        if (isset($this->config['debug_mode'])) {
            $this->config['debug']['enabled'] = (boolean) $this->config['debug_mode'];
        }

        // Write it all back
        $this->config['providers'] = $providersConfig;
    }

    /**
     * Default config options
     *
     * @return array
     */
    protected function getDefaultConfig()
    {
        return [
            'providers' => [
                'Password' => [
                    'enabled' => false
                ],
                'Google' => [
                    'enabled' => false
                ],
                'Facebook' => [
                    'enabled' => false
                ],
                'Twitter' => [
                    'enabled' => false
                ],
                'GitHub' => [
                    'enabled' => false
                ]
            ],
            'basepath' => 'authenticate',
            'template' => [
                'profile'         => '_profile.twig',
                'button'          => '_button.twig',
                'password'        => '_password.twig',
                'password_parent' => 'password.twig'
            ],
            'zocial'        => false,
            'login_expiry'  => 14,
            'debug'         => [
                'enabled' => false,
            ],
            'response_noun' => 'hauth.done'
        ];
    }
}
