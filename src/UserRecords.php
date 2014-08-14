<?php

namespace SocialLogin;

use Silex;

/**
 * Authiticated user record maintenance
 */
class UserRecords
{
    /**
     * @var Silex\Application
     */
    private $app;

    /**
     * @var Extension config array
     */
    private $config;

    public function __construct(Silex\Application $app, $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    public function getUserRecord($username, $provider)
    {
        // Get the user record
        $query = "SELECT * FROM " . $this->getTableNameUser() .
                 " WHERE username = :username AND provider = :provider";
        $map = array(
            ':username' => $username,
            ':provider' => $provider
        );
        $user = $this->db->fetchAssoc($query, $map);

        if ($user['id']) {
            // Get the assocaited session
            $query = "SELECT * FROM " . $this->getTableNameSession() .
                     " WHERE userid = :userid ORDER BY lastseen DESC";
            $map = array(
                ':userid' => $user['id']
            );
            $sess = $this->db->fetchAssoc($query, $map);

            // Map relative parts to returnable array
            $user['sessiontoken'] = (isset($sess['sessiontoken']) ? $sess['sessiontoken'] : '');
            $user['lastseen'] = (isset($sess['lastseen']) ? $sess['lastseen'] : '');
        }

        return $user;
    }

    public function setUserRecord()
    {

    }

    /**
     * Get the name of the user record table
     *
     * @return string
     */
    private function getTableNameUser()
    {
        $this->prefix = $this->config->get('general/database/prefix', "bolt_");

        // Make sure prefix ends in '_'. Prefixes without '_' are lame..
        if ($this->prefix[ strlen($this->prefix)-1 ] != "_") {
            $this->prefix .= "_";
        }

        return $this->prefix . 'sociallogin_users';
    }

    /**
     * Get the name of the user session table
     *
     * @return string
     */
    private function getTableNameSession()
    {
        $this->prefix = $this->config->get('general/database/prefix', "bolt_");

        // Make sure prefix ends in '_'. Prefixes without '_' are lame..
        if ($this->prefix[ strlen($this->prefix)-1 ] != "_") {
            $this->prefix .= "_";
        }

        return $this->prefix . 'sociallogin_sessions';
    }
}