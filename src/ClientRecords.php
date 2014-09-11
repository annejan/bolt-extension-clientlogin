<?php

namespace Bolt\Extension\Bolt\ClientLogin;

use Silex;

/**
 * Authiticated user record maintenance
 */
class ClientRecords
{
    /**
     *
     * @var array User's profile record
     */
    public $user = false;

    /**
     * @var array User's session record
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

    public function __construct(Silex\Application $app)
    {
        $this->app = $app;
        $this->config = $this->app['extensions.' . Extension::NAME]->config;
    }

    /**
     * Look up a users database profile
     *
     * @param string $username
     * @param string $provider
     * @return boolean True if user record found
     */
    public function getUserProfileByName($username, $provider)
    {
        if ($this->user) {
            return true;
        } else {
            // Get the user record
            $query = "SELECT * FROM " . $this->getTableNameProfiles() .
                     " WHERE username = :username AND provider = :provider";
            $map = array(
                ':username' => $username,
                ':provider' => $provider
            );
            $this->user = $this->app['db']->fetchAssoc($query, $map);

            if (empty($this->user['id'])) {
                return false;
            } else {
                return true;
            }
        }
    }

    /**
     * Look up a users database profile
     *
     * @param string $username
     * @param string $provider
     * @return boolean True if user record found
     */
    public function getUserProfileByID($id)
    {
        if ($this->user) {
            return true;
        } else {
            // Get the user record
            $query = "SELECT * FROM " . $this->getTableNameProfiles() .
                     " WHERE id = :id";
            $map = array(
                ':id' => $id
            );
            $this->user = $this->app['db']->fetchAssoc($query, $map);

            if (empty($this->user['id'])) {
                return false;
            } else {
                return true;
            }
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
    public function getUserProfileBySession($token)
    {
        $query = "SELECT * from " . $this->getTableNameSessions() .
                 " WHERE token = :token";
        $map = array(':token' => $token);

        $this->session = $this->app['db']->fetchAssoc($query, $map);

        if (!empty($this->session['userid'])) {
            // Get the user record
            $query = "SELECT * FROM " . $this->getTableNameProfiles() .
                     " WHERE id = :id";
            $map = array(
                ':id' => $this->session['userid']
            );
            $this->user = $this->app['db']->fetchAssoc($query, $map);

            // Check we've got a valid record
            if (empty($this->user['id'])) {
                // No user profile associtated with this token, remove it
                $this->doRemoveSession(array('id' => $this->session['id']));
            } else {
                // User records are all good
                return true;
            }
        }
        return false;
    }

    /**
     * Lookup user session by user ID
     *
     * @param integer $id
     * @return boolean
     */
    public function getUserSessionByID($id)
    {
        // Get the assocaited session
        $query = "SELECT * FROM " . $this->getTableNameSessions() .
                 " WHERE userid = :userid ORDER BY lastseen DESC";
        $map = array(
            ':userid' => $id
        );
        $this->session = $this->app['db']->fetchAssoc($query, $map);

        if (empty($this->session['id'])) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Lookup user session by user ID
     *
     * @param integer $id
     * @return boolean
     */
    public function getUserSessionByToken($token)
    {
        // Get the assocaited session
        $query = "SELECT * FROM " . $this->getTableNameSessions() .
                 " WHERE token = :token ORDER BY lastseen DESC";
        $map = array(
            ':token' => $token
        );
        $this->session = $this->app['db']->fetchAssoc($query, $map);

        if (empty($this->session['id'])) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Create a user profile record
     *
     * @param string $provider
     * @param array $profile
     * @return boolean
     */
    public function doCreateUserProfile($provider, $profile)
    {
        $json = json_encode($profile);

        $content = array(
            'username' => $profile->displayName,
            'provider' => $provider,
            'providerdata' => $json
        );

        $result = $this->app['db']->insert($this->getTableNameProfiles(), $content);

        if ($result) {
            $this->user['id'] = $this->app['db']->lastInsertId();
            return true;
        } else {
            return false;
        }
    }

    public function doCreateUserSession($token)
    {
        $content = array(
            'userid' =>  $this->user['id'],
            'lastseen' => date('Y-m-d H:i:s', $_SERVER["REQUEST_TIME"]),
            'token' => $token
        );

        $result = $this->app['db']->insert($this->getTableNameSessions(), $content);

        if ($result) {
            return true;
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
        $this->app['db']->delete($this->getTableNameProfiles(), $match);
    }

    /**
     * Remove a session from the database
     *
     * @param array $match A parameter/value array representing column/value
     */
    public function doRemoveSession($token)
    {
        if (empty($token)) {
            return;
        }
        $this->app['db']->delete($this->getTableNameSessions(), array('token' => $token));
    }

    public function doRemoveSessionsOld()
    {
        if (empty($this->config['login_expiry'])) {
            $maxage = 14;
        } else {
            $maxage = $this->config['login_expiry'];
        }

        $query = "DELETE FROM " . $this->getTableNameSessions() . " WHERE lastseen <= :maxage";
        $map = array(
            'maxage' => date('Y-m-d H:i:s', (time() - (60*60*24*$maxage)))
        );

        $result = $this->app['db']->executeQuery($query, $map);

        if ($result) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Create/update database tables
     */
    public function dbCheck()
    {
        // User/client provider table
        $table_name = $this->getTableNameProfiles();
        $this->app['integritychecker']->registerExtensionTable(
            function ($schema) use ($table_name) {
                $table = $schema->createTable($table_name);
                $table->addColumn("id",           "integer", array('autoincrement' => true));
                $table->addColumn("username",     "string",  array("length" => 64));
                $table->addColumn("provider",     "string",  array("length" => 64));
                $table->addColumn("providerdata", "text");
                $table->addColumn("sessiondata",  "text");
                $table->addColumn("lastupdate",   "datetime");
                $table->setPrimaryKey(array("id"));
                return $table;
            }
        );

        // User/client session table
        $table_name = $this->getTableNameSessions();
        $this->app['integritychecker']->registerExtensionTable(
            function ($schema) use ($table_name) {
                $table = $schema->createTable($table_name);
                $table->addColumn("id",      "integer", array('autoincrement' => true));
                $table->addColumn("userid",   "integer");
                $table->addColumn("token",    "string", array('length' => 64));
                $table->addColumn("lastseen", "datetime");
                $table->setPrimaryKey(array("id"));
                $table->addIndex(array("userid"));
                $table->addIndex(array("token"));
                return $table;
            }
        );
    }

    /**
     * Get the name of the user record table
     *
     * @return string
     */
    private function getTableNameProfiles()
    {
        $this->prefix = $this->app['config']->get('general/database/prefix', "bolt_");

        // Make sure prefix ends in '_'. Prefixes without '_' are lame..
        if ($this->prefix[ strlen($this->prefix)-1 ] != "_") {
            $this->prefix .= "_";
        }

        return $this->prefix . 'client_profiles';
    }

    /**
     * Get the name of the user session table
     *
     * @return string
     */
    private function getTableNameSessions()
    {
        $this->prefix = $this->app['config']->get('general/database/prefix', "bolt_");

        // Make sure prefix ends in '_'. Prefixes without '_' are lame..
        if ($this->prefix[ strlen($this->prefix)-1 ] != "_") {
            $this->prefix .= "_";
        }

        return $this->prefix . 'client_sessions';
    }
}
