<?php

namespace SocialLogin;

/**
 * Social Login with OAuth via HybridAuth
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Extension extends \Bolt\BaseExtension
{
    /**
     * @var SocialLogin\Controller
     */
    private $controller;

    public function getName()
    {
        return "SocialLogin";
    }

    public function initialize()
    {
        if (empty($this->config['basepath'])) {
            $this->config['basepath'] = "sociallogin";
        }

        if ($this->app['config']->getWhichEnd() == 'backend') {
            // Check & create database tables if required
            $this->dbCheck();
        }

        if ($this->app['config']->getWhichEnd() == 'frontend') {
            // Set up routes
            $this->setController();

            // Tig functions
            $this->app['twig']->addExtension(new SocialLoginTwigExtensions($this->app, $this->config));

            //$hybridauth = new \Hybrid_Auth(array());
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
                  ->method('POST');
    }

    /**
     *
     */
    private function dbCheck()
    {
        // Set up database schema
        $table_prefix = $this->app['config']->get('general/database/prefix', "bolt_");

        // CREATE TABLE 'bolt_visitors'
        $this->app['integritychecker']->registerExtensionTable(
            function ($schema) use ($table_prefix) {
                $table = $schema->createTable($table_prefix . "visitors");
                $table->addColumn("id", "integer", array('autoincrement' => true));
                $table->setPrimaryKey(array("id"));
                $table->addColumn("username", "string", array("length" => 64));
                $table->addColumn("provider", "string", array("length" => 64));
                $table->addColumn("providerdata", "text");
                $table->addColumn("apptoken", "string", array("length" => 64, 'notnull' => false));
                return $table;
            }
        );

        // CREATE TABLE 'bolt_visitors_sessions'
        $this->app['integritychecker']->registerExtensionTable(
            function ($schema) use ($table_prefix) {
                $table = $schema->createTable($table_prefix . "visitors_sessions");
                $table->addColumn("id", "integer", array('autoincrement' => true));
                $table->setPrimaryKey(array("id"));
                $table->addColumn("visitor_id", "integer");
                $table->addColumn("sessiontoken", "string", array('length' => 64));
                $table->addColumn("lastseen", "datetime");
                $table->addIndex(array("visitor_id"));
                $table->addIndex(array("sessiontoken"));
                return $table;
            }
        );
    }
}
