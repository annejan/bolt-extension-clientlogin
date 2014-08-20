<?php

namespace Bolt\Extension\ClientLogin;

/**
 * User interface
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class UserInterface
{
    /**
     * @var \Bolt\Application
     */
    private $app;

    /**
     * @var array Extension config array
     */
    private $config;

    public function __construct(\Bolt\Application $app)
    {
        $this->app = $app;
        $this->config = $this->app['extensions.' . Extension::NAME]->config;

        $this->app['twig.loader.filesystem']->addPath(dirname(__DIR__) . "/assets");
    }

    public function doDisplayAuth($redirect)
    {
        $session = $this->app['extensions.' . Extension::NAME]->session;

        if ($session->doCheckLogin()) {
            return $this->doDisplayLogout($redirect);
        } else {
            return $this->doDisplayLogin($redirect);
        }
    }

    /**
     * Returns a list of links to all enabled login options
     *
     * @return \Twig_Markup
     */
    public function doDisplayLogin($redirect, $target = '')
    {
        $html = '';

        // Set redirect if passed
        if ($redirect) {
            $target = '&redirect=' . urlencode($this->app['paths']['current']);
        }
        // Render
        if (isset($this->config['openid']) && $this->config['openid'] == true) {
            $buttons = array();


            foreach($this->config['providers'] as $provider => $values) {
                if($values['enabled'] == true) {
                    $label = !empty($values['label']) ? $values['label'] : $provider;
                    $buttons[] = $this->doFormatButton(
                        $this->app['paths']['root'] . $this->config['basepath'] . '/login?provider=' . $provider . $target,
                        $provider,
                        $label);
                }
            }

            $html .= join("\n", $buttons);
        }

        if (isset($this->config['password']) && $this->config['password'] == true) {
            $html .= '';
        }

        return new \Twig_Markup($html, 'UTF-8');
    }

    /**
     * Returns logout button
     *
     * @return \Twig_Markup
     */
    public function doDisplayLogout($redirect)
    {
        if ($redirect) {
            $target = '?redirect=' . urlencode($this->app['paths']['current']);
        }

        if (empty($this->config['label']['logout'])) {
            $this->config['label']['logout'] = 'Logout';
        }

        $logoutlink = $this->doFormatButton(
            $this->app['paths']['root'] . $this->config['basepath'].'/logout' . $target,
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
     * @return \Twig_Markup
     */
    private function doFormatButton($link, $provider, $label)
    {
        $context = array(
            'link' => $link,
            'label' => $label,
            'class' => strtolower(safeString($provider))
        );

        $markup = $this->app['render']->render($this->config['template']['button'], $context);

        return new \Twig_Markup($markup, 'UTF-8');
    }
}
