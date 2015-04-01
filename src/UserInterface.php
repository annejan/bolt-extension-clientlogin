<?php

namespace Bolt\Extension\Bolt\ClientLogin;

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

    /** @var array Extension config */
    private $config;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->config = $this->app[Extension::CONTAINER]->config;

        $this->app['twig.loader.filesystem']->addPath(dirname(__DIR__) . "/assets");
    }

    /**
     * Disply login/logout depending on auth status
     *
     * @param string $redirect
     *
     * @return Twig_Markup
     */
    public function doDisplayAuth($redirect)
    {
        if ($this->app['clientlogin.session']->doCheckLogin()) {
            return $this->doDisplayLogout($redirect);
        } else {
            return $this->doDisplayLogin($redirect);
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
    public function doDisplayLogin($redirect, $target = '')
    {
        $html = '';

        // Set redirect if passed
        if ($redirect) {
            $target = '&redirect=' . urlencode($this->app['resources']->getUrl('current'));
        }
        // Render
        if (isset($this->config['auth']['hybridauth'])) {
            $buttons = array();

            foreach ($this->config['auth']['hybridauth']['providers'] as $provider => $values) {
                if ($values['enabled'] == true) {
                    $label = !empty($values['label']) ? $values['label'] : $provider;
                    $class = isset($values['type']) && $values['type'] == 'OpenID' ? 'openid' : $provider;

                    $buttons[] = $this->doFormatButton(
                        $this->app['resources']->getUrl('root') . $this->config['basepath'] . '/login?provider=' . $provider . $target,
                        $class,
                        $label);
                }
            }

            $html .= join("\n", $buttons);
        }

        if (isset($this->config['auth']['password']) && $this->config['auth']['password']['enabled'] === true) {
            $html .= '';
        }

        return new \Twig_Markup($html, 'UTF-8');
    }

    /**
     * Returns logout button
     *
     * @param boolean $redirect
     *
     * @return \Twig_Markup
     */
    public function doDisplayLogout($redirect)
    {
        if ($redirect) {
            $target = '?redirect=' . urlencode($this->app['resources']->getUrl('current'));
        }

        if (empty($this->config['label']['logout'])) {
            $this->config['label']['logout'] = 'Logout';
        }

        $logoutlink = $this->doFormatButton(
            $this->app['resources']->getUrl('root') . $this->config['basepath'].'/logout' . $target,
            '',
            $this->config['label']['logout']);

        return new \Twig_Markup($logoutlink, 'UTF-8');
    }

    /**
     * Simple function to format the HTML for a button.
     *
     * @param string $link
     * @param string $provider
     * @param string $label
     *
     * @return \Twig_Markup
     */
    private function doFormatButton($link, $provider, $label)
    {
        $provider = strtolower(String::makeSafe($provider));

        if ($this->config['zocial']) {
            $class = "zocial {$provider}";
        } else {
            $class = "{$provider}";
        }

        $context = array(
            'link'  => $link,
            'label' => $label,
            'class' => $class
        );

        $markup = $this->app['render']->render($this->config['template']['button'], $context);

        return new \Twig_Markup($markup, 'UTF-8');
    }
}
