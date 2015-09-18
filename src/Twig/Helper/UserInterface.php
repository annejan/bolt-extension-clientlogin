<?php

namespace Bolt\Extension\Bolt\ClientLogin\Twig\Helper;

use Bolt\Application;
use Bolt\Helpers\String;

/**
 * User interface
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class UserInterface
{
    /** @var \Bolt\Application */
    private $app;
    /** @var \Bolt\Extension\Bolt\ClientLogin\Config */
    private $config;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->config = $app['clientlogin.config'];

        $this->app['twig.loader.filesystem']->addPath(dirname(dirname(dirname(__DIR__))) . '/assets');
    }

    /**
     * Disply login/logout depending on auth status
     *
     * @param string $redirect
     *
     * @return Twig_Markup
     */
    public function displayAuth($redirect)
    {
        if ($this->app['clientlogin.session']->isLoggedIn()) {
            return $this->displayLogout($redirect);
        } else {
            return $this->displayLogin($redirect);
        }
    }

    /**
     * Returns a list of links to all enabled login options
     *
     * @param boolean $redirect
     * @param string  $target
     *
     * @return \Twig_Markup
     */
    public function displayLogin($redirect, $target = '')
    {
        $html = '';

        // Set redirect if passed
        if ($redirect) {
            $target = '&redirect=' . urlencode($this->app['resources']->getUrl('current'));
        }

        // Render
        $link    = $this->app['resources']->getUrl('root') . $this->config->get('basepath') . '/login?provider=';
        $context = [];

        foreach ($this->config->get('providers') as $provider => $values) {
            if ($values['enabled'] !== true) {
                continue;
            }

            $context['providers'][$provider] = [
                'link'  => $link . $provider . $target,
                'label' => !empty($values['label']) ? $values['label'] : $provider,
                'class' => $this->getClass(strtolower($provider), $values)
            ];
        }

        $html = $this->app['render']->render($this->config->getTemplate('button'), $context);

        return new \Twig_Markup($html, 'UTF-8');
    }

    /**
     * Returns logout button
     *
     * @param boolean $redirect
     *
     * @return \Twig_Markup
     */
    public function displayLogout($redirect)
    {
        $target = ''; // empty target

        if ($redirect) {
            $target = '?redirect=' . urlencode($this->app['resources']->getUrl('current'));
        }

        $context = [
            'providers' => [
                'logout' => [
                    'link'  => $this->app['resources']->getUrl('root') . $this->config->get('basepath') . '/logout' . $target,
                    'label' => $this->config->getLabel('logout') ?: 'Logout',
                    'class' => 'logout'
                ]
            ]
        ];

        $html = $this->app['render']->render($this->config->getTemplate('button'), $context);

        return new \Twig_Markup($html, 'UTF-8');
    }

    /**
     * Get a button's class
     *
     * @param string $provider
     * @param array  $values
     *
     * @return string
     */
    private function getClass($provider, $values)
    {
        if (isset($values['type']) && $values['type'] == 'OpenID') {
            return $this->config->get('zocial') ? 'zocial openid' : 'openid';
        }

        return $this->config->get('zocial') ? "zocial $provider" : $provider;
    }
}
