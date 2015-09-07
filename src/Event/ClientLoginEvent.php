<?php

namespace Bolt\Extension\Bolt\ClientLogin\Event;

use Bolt\Extension\Bolt\ClientLogin\Profile;
use Symfony\Component\EventDispatcher\Event;

class ClientLoginEvent extends Event
{
    /** @var array User record */
    private $user;
    /** @var string User profile table name */
    private $tableName;

    /**
     * @param Profile $user
     * @param string  $tableName
     */
    public function __construct(Profile $user, $tableName)
    {
        $this->user      = $user;
        $this->tableName = $tableName;
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
        return $this->tableName;
    }
}
