<?php

namespace SocialLogin;

use Silex;

/**
 * Authiticated user record maintenance
 */
class UserRecords
{
    /**
     *
     * @var User's profile record
     */
    public $user = false;

    /**
     * @var User's session record
     */
    public $session = false;

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

    /**
     *
     * @param string $username
     * @param string $provider
     * @return boolean
     */
    public function getUserByName($username, $provider)
    {
        if ($this->user) {
            return true;
        } else {
            // Get the user record
            $query = "SELECT * FROM " . $this->getTableNameUser() .
                     " WHERE username = :username AND provider = :provider";
            $map = array(
                ':username' => $username,
                ':provider' => $provider
            );
            $this->user = $this->db->fetchAssoc($query, $map);

            if (!empty($this->user['id'])) {
                // Get the assocaited session
                $query = "SELECT * FROM " . $this->getTableNameSession() .
                         " WHERE userid = :userid ORDER BY lastseen DESC";
                $map = array(
                    ':userid' => $this->user['id']
                );
                $this->session = $this->db->fetchAssoc($query, $map);
            }

            return true;
        }
    }

    /**
     * Get the passed member session token.
     *
     * If we have a token record matching the users cookie, retrieve the
     * matching user record and store in obeject
     *
     * @param string $token The PHP session token to query
     * @return boolean
     */
    public function getUserBySession($token)
    {
        $query = "SELECT * from " . $this->getTableNameSession() .
                 " WHERE sessiontoken = :sessiontoken";
        $map = array(':sessiontoken' => $token);

        $this->session = $this->db->fetchAssoc($query, $map);

        if (!empty($this->session['userid'])) {
            // Get the user record
            $query = "SELECT * FROM " . $this->getTableNameUser() .
                     " WHERE id = :id";
            $map = array(
                ':id' => $this->session['userid']
            );
            $this->user = $this->db->fetchAssoc($query, $map);

            // Check we've got a valid record
            if (empty($this->user['id']))
            {
                // No user profile associtated with this token, remove it
                $this->doRemoveSession(array('id' => $this->session['id']));
            } else {
                // User records are all good
                return true;
            }
        }
        return false;
    }

    public function doCreateuser($provider, $profile)
    {
        $json = json_encode($profile);

        $content = array(
            'username' => $profile->displayName,
            'provider' => $provider,
            'providerdata' => $json
        );

        $result = $this->db->insert($this->getTableNameUser(), $content);
// XXX remove when tested
return $this->db->lastInsertId();

        if ($result) {
            return $this->db->lastInsertId();
        } else {
            return false;
        }
    }

    /**
     * Remove a user from the database
     *
     * @param array $match A parameter/value array representing column/value
     */
    public function doRemoveUser($match)
    {
        if (empty($match)) {
            return;
        }
        $this->db->delete($this->getTableNameUser(), $match);
    }

    /**
     * Remove a session from the database
     *
     * @param array $match A parameter/value array representing column/value
     */
    public function doRemoveSession($match)
    {
        if (empty($match)) {
            return;
        }
        $this->db->delete($this->getTableNameSession(), $match);
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