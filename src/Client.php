<?php

namespace Bolt\Extension\Bolt\ClientLogin;

use League\OAuth2\Client\Entity\User;

/**
 * Client details class
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Client
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

    /** @var string */
    protected $json;

    private $properties = [
            'id','provider', 'uid', 'nickname', 'name', 'firstName', 'lastName',
            'email', 'location', 'description', 'imageUrl', 'urls', 'gender',
            'locale'
        ];

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
        $class->uid         = $data['identifier'];
        $class->nickname    = $data['nickname'];
        $class->name        = $data['name'];;
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

    /**
     * Add an OAuth2 client data
     *
     * @param \League\OAuth2\Client\Entity\User $client
     */
    public function addOAuth2Client(User $client)
    {
        foreach ($this->properties as $property) {
            try {
                $this->{$property} = $client->{$property};
            } catch (\Exception $e) {
            }
        }

        $this->json = $this->getLeagueUserJson($client);
    }

    /**
     * Return the profile data as JSON
     *
     * @return string
     */
    public function getProfileJson()
    {
        return $this->json;
    }

    /**
     * Add a password based user data
     *
     * @param \stdClass $client
     */
    public function addPasswordClient(\stdClass $client)
    {
    }

    /**
     * Get a JSON representation of the User class for backwards compatibility
     *
     * This is ugly, and I will never admit to having written it! :-P
     *
     * @return string
     */
    private function getLeagueUserJson()
    {
        $json = [];
        foreach ($this->properties as $property) {
            $json[$property] = $this->{$property};
        }

        return json_encode($json);
    }
}
