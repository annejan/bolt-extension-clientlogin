<?php

namespace Bolt\Extension\Bolt\ClientLogin;

use Hautelook\Phpass\PasswordHash;
use League\OAuth2\Client\Entity\User;

/**
 * Client details class
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Client implements \JsonSerializable
{
    /** @var mixed  */
    public $client = false;

    protected $id;
    protected $provider;
    protected $uid;
    protected $nickname;
    protected $name;
    protected $firstName;
    protected $lastName;
    protected $email;
    protected $location;
    protected $description;
    protected $imageUrl;
    protected $urls;
    protected $gender;
    protected $locale;
    protected $password;

    public function __construct()
    {
    }

    /**
     * @param string $name
     *
     * @throws \OutOfRangeException
     *
     * @return string
     */
    public function __get($name)
    {
        if (!property_exists($this, $name)) {
            throw new \OutOfRangeException(sprintf(
                '%s does not contain a property by the name of "%s"',
                __CLASS__,
                $name
            ));
        }

        return $this->{$name};
    }

    /**
     * @param string $property
     * @param string $value
     *
     * @throws \OutOfRangeException
     *
     * @return \Bolt\Extension\Bolt\ClientLogin\Client
     */
    public function __set($property, $value)
    {
        if (!property_exists($this, $property)) {
            throw new \OutOfRangeException(sprintf(
                '%s does not contain a property by the name of "%s"',
                __CLASS__,
                $property
            ));
        }

        $this->$property = $value;

        return $this;
    }

    /**
     * Valid output for json_encode()
     *
     * @return array
     */
    public function jsonSerialize()
    {
        $arr = [];
        foreach(array_keys(get_class_vars(__CLASS__)) as $property) {
            $arr[$property] = $this->$property;
        }

        return $arr;
    }

    /**
     * Create a class instance from our database records.
     *
     * Just… don't… ask… :-/
     *
     * @param array|boolean $user
     */
    public static function createFromDbRecord($user)
    {
        if ($user === false) {
            return false;
        }

        $classname = get_called_class();
        $class = new $classname();

        $data = json_decode($user['providerdata'], true);

        $class->id          = $user['id'];
        $class->provider    = $user['provider'];
        $class->uid         = isset($data['identifier']) ? $data['identifier'] : $data['uid'];
        $class->nickname    = $data['nickname'];
        $class->name        = $data['name'];
        $class->firstName   = $data['firstName'];
        $class->lastName    = $data['lastName'];
        $class->email       = $data['email'];
        $class->location    = $data['location'];
        $class->description = $data['description'];
        $class->imageUrl    = $data['imageUrl'];
        $class->urls        = $data['urls'];
        $class->gender      = $data['gender'];
        $class->locale      = $data['locale'];

        return $class;
    }

    public static function createPasswordAuth($username, $password)
    {
        $hasher = new PasswordHash(12, true);
        $password = $hasher->HashPassword($password);

        $classname = get_called_class();
        $class = new $classname();

        $class->uid         = $username;
        $class->password    = $password;
        $class->nickname    = $username;
        $class->name        = $username;
        $class->firstName   = '';
        $class->lastName    = '';
        $class->email       = '';
        $class->location    = '';
        $class->description = '';
        $class->imageUrl    = '';
        $class->urls        = '';
        $class->gender      = '';
        $class->locale      = '';

        return $class;
    }

    /**
     * Add an OAuth2 client data
     *
     * @param \League\OAuth2\Client\Entity\User $client
     */
    public function addOAuth2Client(User $client)
    {
        foreach(array_keys(get_class_vars(__CLASS__)) as $property) {
            try {
                $this->{$property} = $client->{$property};
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * Add a password based user data
     *
     * @param \stdClass $client
     */
    public function addPasswordClient(\stdClass $client)
    {
    }
}
