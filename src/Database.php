<?php

namespace Bolt\Extension\Bolt\ClientLogin;

use League\OAuth2\Client\Token\AccessToken;
use Silex\Application;

/**
 * Authenticated user record maintenance
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Database
{
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
     * Look up a users database profile by user name
     *
     * @param string $username
     * @param string $provider
     *
     * @return \Bolt\Extension\Bolt\ClientLogin\Client|boolean
     */
    public function getUserProfileByName($username, $provider)
    {
        try {
            $user = $this->app['db']
                ->createQueryBuilder()
                ->select('*')
                ->from($this->getTableNameProfiles())
                ->where('username = :username', 'provider = :provider')
                ->setParameter(':username', $username)
                ->setParameter(':provider', $provider)
                ->execute()
                ->fetch(\PDO::FETCH_ASSOC)
            ;

            return Client::createFromDbRecord($user);
        } catch (\Exception $e) {
            $msg = sprintf("ClientLogin had an error getting %s profile for %s from the database.", $username, $provider);
            $this->app['logger.system']->critical($msg, ['event' => 'exception', 'exception' => $e]);

            return false;
        }
    }

    /**
     * Look up a users database profile by provider identifier
     *
     * @param string $identifier
     * @param string $provider
     *
     * @return array|boolean
     */
    public function getUserProfileByIdentifier($identifier, $provider)
    {
        try {
            $user = $this->app['db']
                ->createQueryBuilder()
                ->select('*')
                ->from($this->getTableNameProfiles())
                ->where('identifier = :identifier', 'provider = :provider')
                ->setParameter(':identifier', $identifier)
                ->setParameter(':provider', $provider)
                ->execute()
                ->fetch(\PDO::FETCH_ASSOC)
            ;

            return Client::createFromDbRecord($user);
        } catch (\Exception $e) {
            $msg = sprintf("ClientLogin had an error getting %s profile for %s from the database.", $identifier, $provider);
            $this->app['logger.system']->critical($msg, ['event' => 'exception', 'exception' => $e]);

            return false;
        }
    }

    /**
     * Look up a users database profile
     *
     * @param string $username
     * @param string $provider
     *
     * @return \Bolt\Extension\Bolt\ClientLogin\Client|boolean
     */
    public function getUserProfileByID($id)
    {
        try {
            $user = $this->app['db']
                ->createQueryBuilder()
                ->select('*')
                ->from($this->getTableNameProfiles())
                ->where('id = :id')
                ->setParameter(':id', $id)
                ->execute()
                ->fetch(\PDO::FETCH_ASSOC)
            ;

            return Client::createFromDbRecord($user);
        } catch (\Exception $e) {
            $this->app['logger.system']->critical("ClientLogin had an error getting profile with ID '$id' from the database.", ['event' => 'exception', 'exception' => $e]);

            return false;
        }
    }

    /**
     * Get the passed client by either session or provider token.
     *
     * If we have a token record matching the users token, retrieve the
     * matching user record and store in obeject
     *
     * @param string $token The PHP session or provider token to query
     *
     * @return \Bolt\Extension\Bolt\ClientLogin\Client|boolean
     */
    public function getUserProfileBySession($token)
    {
        try {
            $session = $this->app['db']
                ->createQueryBuilder()
                ->select('*')
                ->from($this->getTableNameSessions())
                ->where('token = :token')
                ->orWhere('session = :token')
                ->setParameter(':token', $token)
                ->execute()
                ->fetch(\PDO::FETCH_ASSOC)
            ;

            if (!empty($session['userid'])) {
                // Check we've got a valid record
                if ($user = $this->getUserProfileByID($session['userid'])) {
                    // User records are all good
                    return $user;
                }

                // No user profile associtated with this token, remove it
                $this->doRemoveSession(['id' => $this->session['id']]);
            }

            return false;
        } catch (\Exception $e) {
            $this->app['logger.system']->critical("ClientLogin had an error getting profile from the database.", ['event' => 'exception', 'exception' => $e]);

            return false;
        }
    }

    /**
     * Lookup user session by user ID
     *
     * @param integer $id
     *
     * @return array|boolean
     */
    public function getUserSessionByID($id)
    {
        // Get the assocaited session
        try {
            return $this->app['db']
                ->createQueryBuilder()
                ->select('*')
                ->from($this->getTableNameSessions())
                ->where('userid = :userid')
                ->orderBy('lastseen', 'DESC')
                ->setParameter(':userid', $id)
                ->execute()
                ->fetch(\PDO::FETCH_ASSOC)
            ;
        } catch (\Exception $e) {
            $this->app['logger.system']->critical("ClientLogin had an error getting session with ID '$id' from the database.", ['event' => 'exception', 'exception' => $e]);

            return false;
        }
    }

    /**
     * Lookup user session by PHP session ID
     *
     * @param string $token
     *
     * @return array|boolean
     */
    public function getSessionBySessionToken($token)
    {
        // Get the assocaited session
        try {
            return $this->app['db']
                ->createQueryBuilder()
                ->select('*')
                ->from($this->getTableNameSessions())
                ->where('session = :session')
                ->orderBy('lastseen', 'DESC')
                ->setParameter(':session', $token)
                ->execute()
                ->fetch(\PDO::FETCH_ASSOC)
            ;
        } catch (\Exception $e) {
            $this->app['logger.system']->critical("ClientLogin had an error getting session from the database.", ['event' => 'exception', 'exception' => $e]);

            return false;
        }
    }

    /**
     * Lookup user session by provider token ID
     *
     * @param \League\OAuth2\Client\Token\AccessToken $token
     *
     * @return array|boolean
     */
    public function getSessionByProviderToken(AccessToken $token)
    {
        // Get the assocaited session
        try {
            return $this->app['db']
                ->createQueryBuilder()
                ->select('*')
                ->from($this->getTableNameSessions())
                ->where('token = :token')
                ->orderBy('lastseen', 'DESC')
                ->setParameter(':token', $token)
                ->execute()
                ->fetch(\PDO::FETCH_ASSOC)
            ;
        } catch (\Exception $e) {
            $this->app['logger.system']->critical("ClientLogin had an error getting session from the database.", ['event' => 'exception', 'exception' => $e]);

            return false;
        }
    }

    /**
     * Create a user profile record
     *
     * @param string $provider
     * @param Client $profile
     * @param string $sessiondata
     *
     * @return array|boolean
     */
    public function doCreateUserProfile($provider, Client $profile, $sessiondata)
    {
        try {
            $count = $this->app['db']
                ->createQueryBuilder()
                ->insert($this->getTableNameProfiles())
                ->values([
                    'identifier'   => ':identifier',
                    'username'     => ':username',
                    'provider'     => ':provider',
                    'providerdata' => ':providerdata',
                    'sessiondata'  => ':sessiondata',
                    'lastupdate'   => ':lastupdate',
                ])
                ->setParameters([
                    ':identifier'   => $profile->uid,
                    ':username'     => $profile->name,
                    ':provider'     => $provider,
                    ':providerdata' => json_encode($profile),
                    ':sessiondata'  => $sessiondata,
                    ':lastupdate'   => date('Y-m-d H:i:s', $this->app['request']->server->get('REQUEST_TIME', time()))
                ])
                ->execute()
            ;

            if ($count) {
                return $this->getUserProfileByName($profile->name, $provider);
            } else {
                return false;
            }
        } catch (\Exception $e) {
            $msg = sprintf("ClientLogin had an error creating %s profile for '%s' with identifier '%s'.",
                $provider,
                $profile->name,
                $profile->uid
                );
            $this->app['logger.system']->critical($msg, ['event' => 'exception', 'exception' => $e]);

            return false;
        }
    }

    /**
     * Update a user profile record
     *
     * @param string $provider
     * @param Client $profile
     * @param string $sessiondata
     */
    public function doUpdateUserProfile($provider, Client $profile, $sessiondata)
    {
        try {
            $this->app['db']
                ->createQueryBuilder()
                ->update($this->getTableNameProfiles())
                ->set('providerdata', ':providerdata')
                ->set('sessiondata',  ':sessiondata')
                ->set('lastupdate',   ':lastupdate')
                ->where('identifier  = :identifier', 'provider = :provider')
                ->setParameters([
                    ':providerdata' => json_encode($profile),
                    ':sessiondata'  => $sessiondata,
                    ':lastupdate'   => date('Y-m-d H:i:s', $this->app['request']->server->get('REQUEST_TIME', time())),
                    ':identifier'   => $profile->uid,
                    ':provider'     => $provider,
                ])
                ->execute()
            ;

            return true;
        } catch (\Exception $e) {
            $msg = sprintf("ClientLogin had an error updating profile '%s' with identifier '%s'.", $provider, $profile->identifier);
            $this->app['logger.system']->critical($msg, ['event' => 'exception', 'exception' => $e]);

            return false;
        }
    }

    /**
     * Create a user's session record
     *
     * @param Client $user
     * @param string $token
     *
     * @return boolean
     */
    public function doCreateUserSession(Client $user, $session, $token)
    {
        try {
            $this->app['db']
                ->createQueryBuilder()
                ->insert($this->getTableNameSessions())
                ->values([
                    'userid'   => ':userid',
                    'lastseen' => ':lastseen',
                    'session'  => ':session',
                    'token'    => ':token'
                ])
                ->setParameters([
                    ':userid'   => $user->id,
                    ':lastseen' => date('Y-m-d H:i:s', $this->app['request']->server->get('REQUEST_TIME', time())),
                    ':session'  => $session,
                    ':token'    => $token
                ])
                ->execute()
            ;

            return true;
        } catch (\Exception $e) {
            $msg = sprintf("ClientLogin had an error adding user ID '%s' token to the database.", $user->id);
            $this->app['logger.system']->critical($msg, ['event' => 'exception', 'exception' => $e]);

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
            $this->app['logger.system']->critical("ClientLogin had an error removing token from the database.", ['event' => 'exception', 'exception' => $e]);

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
            $this->app['logger.system']->critical('ClientLogin had an error removing expired sessions from the database.', ['event' => 'exception', 'exception' => $e]);

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
                $table->addColumn('id',           'integer', ['autoincrement' => true]);
                $table->addColumn('provider',     'string',  ['length' => 64]);
                $table->addColumn('identifier',   'string',  ['length' => 128]);
                $table->addColumn('username',     'string',  ['length' => 64]);
                $table->addColumn('providerdata', 'text');
                $table->addColumn('sessiondata',  'text');
                $table->addColumn('lastupdate',   'datetime');
                $table->setPrimaryKey(['id']);

                return $table;
            }
        );

        // User/client session table
        $table_name = $this->getTableNameSessions();
        $this->app['integritychecker']->registerExtensionTable(
            function ($schema) use ($table_name) {
                $table = $schema->createTable($table_name);
                $table->addColumn('id',       'integer', ['autoincrement' => true]);
                $table->addColumn('userid',   'integer');
                $table->addColumn('session',  'string', ['length' => 64]);
                $table->addColumn('token',    'text',   ['notnull' => false, 'default' => null]);
                $table->addColumn('lastseen', 'datetime');
                $table->setPrimaryKey(['id']);
                $table->addIndex(['userid']);
                $table->addIndex(['session']);

                return $table;
            }
        );
    }
}
