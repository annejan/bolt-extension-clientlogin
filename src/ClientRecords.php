<?php

namespace Bolt\Extension\Bolt\ClientLogin;

use Doctrine\DBAL\Query\QueryBuilder;
use Silex\Application;

/**
 * Authiticated user record maintenance
 */
class ClientRecords
{
    /** @var array User's profile record */
    public $user = false;

    /** @var array User's session record */
    public $session = false;

    /** @var \Silex\Application */
    private $app;

    /** @var Extension config array */
    private $config;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->config = $this->app[Extension::CONTAINER]->config;
    }

    /**
     * Look up a users database profile
     *
     * @param string $username
     * @param string $provider
     *
     * @return boolean True if user record found
     */
    public function getUserProfileByName($username, $provider)
    {
        try {
            $this->user = $this->app['db']
                ->createQueryBuilder()
                ->select('*')
                ->from($this->getTableNameProfiles())
                ->where('username = :username', 'provider = :provider')
                ->setParameter(':username', $username)
                ->setParameter(':provider', $provider)
                ->execute()
                ->fetch(\PDO::FETCH_ASSOC)
            ;

            if (empty($this->user['id'])) {
                return false;
            } else {
                return true;
            }
        } catch (\Exception $e) {
            $msg = sprintf("ClientLogin had an error getting %s profile for %s from the database.", $username, $provider);
            $this->app['logger.system']->critical($msg, array('event' => 'exception', 'exception' => $e));

            return false;
        }
    }

    /**
     * Look up a users database profile
     *
     * @param string $username
     * @param string $provider
     *
     * @return boolean True if user record found
     */
    public function getUserProfileByID($id)
    {
        if ($this->user) {
            return true;
        }

        try {
            $this->user = $this->app['db']
                ->createQueryBuilder()
                ->select('*')
                ->from($this->getTableNameProfiles())
                ->where('id = :id')
                ->setParameter(':id', $id)
                ->execute()
                ->fetch(\PDO::FETCH_ASSOC)
            ;

            if (empty($this->user['id'])) {
                return false;
            } else {
                return true;
            }
        } catch (\Exception $e) {
            $this->app['logger.system']->critical("ClientLogin had an error getting profile with ID '$id' from the database.", array('event' => 'exception', 'exception' => $e));

            return false;
        }
    }

    /**
     * Get the passed member session token.
     *
     * If we have a token record matching the users cookie, retrieve the
     * matching user record and store in obeject
     *
     * @param string $token The PHP session token to query
     *
     * @return boolean
     */
    public function getUserProfileBySession($token)
    {
        try {
            $this->session = $this->app['db']
                ->createQueryBuilder()
                ->select('*')
                ->from($this->getTableNameSessions())
                ->where('token = :token')
                ->setParameter(':token', $token)
                ->execute()
                ->fetch(\PDO::FETCH_ASSOC)
            ;

            if (!empty($this->session['userid'])) {
                unset($this->user);

                // Check we've got a valid record
                if ($this->getUserProfileByID($this->session['userid'])) {
                    // User records are all good
                    return true;
                }

                // No user profile associtated with this token, remove it
                $this->doRemoveSession(array('id' => $this->session['id']));
            }

            return false;
        } catch (\Exception $e) {
            $this->app['logger.system']->critical("ClientLogin had an error getting profile with token '$token' from the database.", array('event' => 'exception', 'exception' => $e));

            return false;
        }
    }

    /**
     * Lookup user session by user ID
     *
     * @param integer $id
     *
     * @return boolean
     */
    public function getUserSessionByID($id)
    {
        // Get the assocaited session
        try {
            $this->session = $this->app['db']
                ->createQueryBuilder()
                ->select('*')
                ->from($this->getTableNameSessions())
                ->where('userid = :userid')
                ->orderBy('lastseen', 'DESC')
                ->setParameter(':userid', $id)
                ->execute()
                ->fetch(\PDO::FETCH_ASSOC)
            ;

            if (empty($this->session['id'])) {
                return false;
            } else {
                return true;
            }
        } catch (\Exception $e) {
            $this->app['logger.system']->critical("ClientLogin had an error getting session with ID '$id' from the database.", array('event' => 'exception', 'exception' => $e));

            return false;
        }
    }

    /**
     * Lookup user session by user ID
     *
     * @param integer $id
     *
     * @return boolean
     */
    public function getUserSessionByToken($token)
    {
        // Get the assocaited session
        try {
            $this->session = $this->app['db']
                ->createQueryBuilder()
                ->select('*')
                ->from($this->getTableNameSessions())
                ->where('token = :token')
                ->orderBy('lastseen', 'DESC')
                ->setParameter(':token', $token)
                ->execute()
                ->fetch(\PDO::FETCH_ASSOC)
            ;

            if (empty($this->session['id'])) {
                return false;
            } else {
                return true;
            }
        } catch (\Exception $e) {
            $this->app['logger.system']->critical("ClientLogin had an error getting session with token '$token' from the database.", array('event' => 'exception', 'exception' => $e));

            return false;
        }
    }

    /**
     * Create a user profile record
     *
     * @param string $provider
     * @param array  $profile
     * @param array  $sessiondata
     *
     * @return boolean
     */
    public function doCreateUserProfile($provider, array $profile, array $sessiondata)
    {
        try {
            $count = $this->app['db']
                ->createQueryBuilder()
                ->insert($this->getTableNameProfiles())
                ->values(array(
                    'identifier'   => ':identifier',
                    'username'     => ':username',
                    'provider'     => ':provider',
                    'providerdata' => ':providerdata',
                    'sessiondata'  => ':sessiondata',
                    'lastupdate'   => ':lastupdate',
                ))
                ->setParameters(array(
                    ':identifier'   => $profile->identifier,
                    ':username'     => $profile->displayName,
                    ':provider'     => $provider,
                    ':providerdata' => json_encode($profile),
                    ':sessiondata'  => json_encode($sessiondata),
                    ':lastupdate'   => date('Y-m-d H:i:s', $this->app['request']->server->get('REQUEST_TIME', time()))
                ))
                ->execute()
                ->fetch()
            ;

            if ($count) {
                $this->user = null;
                $this->getUserProfileByName($profile->displayName, $provider);

                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            $msg = sprintf("ClientLogin had an error creating %s profile for '%s' with identifier '%s'.",
                $provider,
                $profile->displayName,
                $profile->identifier
                );
            $this->app['logger.system']->critical($msg, array('event' => 'exception', 'exception' => $e));

            return false;
        }

    }

    /**
     * Update a user profile record
     *
     * @param string $provider
     * @param array  $profile
     * @param array  $sessiondata
     */
    public function doUpdateUserProfile($provider, \Hybrid_User_Profile $profile, array $sessiondata)
    {
        try {
            $this->app['db']
                ->createQueryBuilder()
                ->update($this->getTableNameProfiles())
                ->set('providerdata', ':providerdata')
                ->set('sessiondata',  ':sessiondata')
                ->set('lastupdate',   ':lastupdate')
                ->where('identifier  = :identifier', 'provider = :provider')
                ->setParameters(array(
                    ':providerdata' => json_encode($profile),
                    ':sessiondata'  => json_encode($sessiondata),
                    ':lastupdate'   => date('Y-m-d H:i:s', $this->app['request']->server->get('REQUEST_TIME', time())),
                    ':identifier'   => $profile->identifier,
                    ':provider'     => $provider,
                ))
                ->execute()
            ;

            return true;
        } catch (\Exception $e) {
            $msg = sprintf("ClientLogin had an error updating profile '%s' with identifier '%s'.", $provider, $profile->identifier);
            $this->app['logger.system']->critical($msg, array('event' => 'exception', 'exception' => $e));

            return false;
        }
    }

    public function doCreateUserSession($token)
    {
        try {
            $this->app['db']
                ->createQueryBuilder()
                ->insert($this->getTableNameSessions())
                ->values(array(
                    'userid'   => ':userid',
                    'lastseen' => ':lastseen',
                    'token'    => ':token'
                ))
                ->setParameters(array(
                    ':userid'   => $this->user['id'],
                    ':lastseen' => date('Y-m-d H:i:s', $this->app['request']->server->get('REQUEST_TIME', time())),
                    ':token'    => $token
                ))
                ->execute()
            ;

            return true;
        } catch (\Exception $e) {
            $msg = sprintf("ClientLogin had an error adding user ID '%s' token '%s' to the database.", $this->user['id'], $token);
            $this->app['logger.system']->critical($msg, array('event' => 'exception', 'exception' => $e));

            return false;
        }
    }

    /**
     * Remove a user from the database
     *
     * @param array $match A parameter/value array representing column/value
     */
    public function doRemoveUser(array $match)
    {
        if (empty($match)) {
            return;
        }
        $this->app['db']->delete($this->getTableNameProfiles(), $match);
    }

    /**
     * Remove a session from the database
     *
     * @param string $token A parameter/value array representing column/value
     */
    public function doRemoveSession($token)
    {
        if (empty($token)) {
            return;
        }

        try {
            $this->app['db']
                ->createQueryBuilder()
                ->delete($this->getTableNameSessions())
                ->where('token <= :token')
                ->setParameter(':token', $token)
                ->execute()
            ;

            return true;
        } catch (\Exception $e) {
            $this->app['logger.system']->critical("ClientLogin had an error removing token '$token' from the database.", array('event' => 'exception', 'exception' => $e));

            return false;
        }
    }

    /**
     * Remove expired session from the database
     *
     * @return boolean
     */
    public function doRemoveExpiredSessions()
    {
        if (empty($this->config['login_expiry'])) {
            $maxage = 14;
        } else {
            $maxage = $this->config['login_expiry'];
        }

        try {
            $this->app['db']
                ->createQueryBuilder()
                ->delete($this->getTableNameSessions())
                ->where('lastseen <= :maxage')
                ->setParameter(':maxage', date('Y-m-d H:i:s', strtotime("-$maxage days")))
                ->execute()
            ;

            return true;
        } catch (\Exception $e) {
            $this->app['logger.system']->critical('ClientLogin had an error removing expired sessions from the database.', array('event' => 'exception', 'exception' => $e));

            return false;
        }
    }

    /**
     * Get the name of the user record table
     *
     * @return string
     */
    public function getTableNameProfiles()
    {
        return $this->getPrefix() . 'client_profiles';
    }

    /**
     * Get the name of the user session table
     *
     * @return string
     */
    private function getTableNameSessions()
    {
        return $this->getPrefix() . 'client_sessions';
    }

    /**
     * Get a valid database prefix
     *
     * @return string
     */
    private function getPrefix()
    {
        $prefix = $this->app['config']->get('general/database/prefix', 'bolt_');

        // Make sure prefix ends in '_'
        if ($prefix[ strlen($prefix)-1 ] != '_') {
            $prefix .= '_';
        }

        return $prefix;
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
                $table->addColumn('id',           'integer', array('autoincrement' => true));
                $table->addColumn('provider',     'string',  array('length' => 64));
                $table->addColumn('identifier',   'string',  array('length' => 128));
                $table->addColumn('username',     'string',  array('length' => 64));
                $table->addColumn('providerdata', 'text');
                $table->addColumn('sessiondata',  'text');
                $table->addColumn('lastupdate',   'datetime');
                $table->setPrimaryKey(array('id'));

                return $table;
            }
        );

        // User/client session table
        $table_name = $this->getTableNameSessions();
        $this->app['integritychecker']->registerExtensionTable(
            function ($schema) use ($table_name) {
                $table = $schema->createTable($table_name);
                $table->addColumn('id',       'integer', array('autoincrement' => true));
                $table->addColumn('userid',   'integer');
                $table->addColumn('token',    'string', array('length' => 64));
                $table->addColumn('lastseen', 'datetime');
                $table->setPrimaryKey(array('id'));
                $table->addIndex(array('userid'));
                $table->addIndex(array('token'));

                return $table;
            }
        );
    }
}
