<?php

namespace Bolt\Extension\Bolt\ClientLogin;

use Bolt;
use Symfony\Component\EventDispatcher\Event;

class ClientLoginEvent extends Event
{
    /**
     * The user record
     */
    private $user;

    /**
     * The user profile table name
     */
    private $tablename;

    /**
     * @param int $id The user ID
     */
    public function __construct($user, $tablename)
    {
        $this->user = $user;
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
