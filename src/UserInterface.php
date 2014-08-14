<?php

namespace SocialLogin;

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
     * @var Extension config array
     */
    private $config;

    public function __construct(\Bolt\Application $app, $config)
    {
        $this->app = $app;
        $this->config = $config;

        $this->app['twig.loader.filesystem']->addPath(dirname(__DIR__) . "/assets");
    }

    /**
     * Returns a list of links to all enabled login options
     *
     * @return \Twig_Markup
     */
    public function displayLogin()
    {
        $buttons = array();

        foreach($this->config['providers'] as $provider => $values) {
            if($values['enabled']==true) {
                $label = !empty($values['label'])?$values['label']:$provider;
                $buttons[] = $this->formatButton(
                    $this->app['paths']['root'] . $this->config['basepath']. '/login?provider='. $provider,
                    $label);
            }
        }

        $markup = join("\n", $buttons);

        return new \Twig_Markup($markup, 'UTF-8');
    }

    /**
     * Simple function to format the HTML for a button.
     *
     * @param string $link
     * @param string $label
     * @return \Twig_Markup
     */
    private function formatButton($link, $label)
    {
        $template = $this->config['template']['button'];
        $context = array(
            'link' => $link,
            'label' => $label,
            'class' => strtolower(safeString($label))
        );

        $markup = $this->app['render']->render($template, $context);

        return new \Twig_Markup($markup, 'UTF-8');
    }
}