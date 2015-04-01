<?php

namespace Bolt\Extension\Bolt\ClientLogin;

use Symfony\Component\EventDispatcher\Event;

class ClientLoginEvent extends Event
{
    /** @var array The user record */
    private $user;

    /** @var string The user profile table name */
    private $tablename;

    /**
     * @param array  $user
     * @param string $tablename
     */
    public function __construct($user, $tablename)
    {
        $this->user      = $user;
        $this->tablename = $tablename;
    }

    /**
     * Return the user record
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Return the user profile table name
     */
    public function getTableNameProfiles()
    {
        return $this->tablename;
    }
}
