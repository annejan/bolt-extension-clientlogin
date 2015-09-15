<?php

namespace Bolt\Extension\Bolt\ClientLogin\Authorisation;

use Bolt\Configuration\ResourceManager;
use Bolt\Extension\Bolt\ClientLogin\Database\RecordManager;
use RandomLib\Generator;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Cookie manager class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class CookieManager
{
    /** @var RecordManager */
    protected $records;
    /** @var \RandomLib\Generator */
    protected $random;
    /** @var ResourceManager */
    protected $resourceManager;

    /**
     * Constructor.
     *
     * @param RecordManager   $records
     * @param Generator       $random
     * @param ResourceManager $resourceManager
     */
    public function __construct(RecordManager $records, Generator $random, ResourceManager $resourceManager)
    {
        $this->records = $records;
        $this->random = $random;
        $this->resourceManager = $resourceManager;
    }

    /**
     * Create an authentication cookie.
     *
     * @param integer $userId
     * @param Token   $token
     *
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function create($userId, AccessToken $accessToken)
    {
        if (!$expire = $accessToken->getExpires()) {
            $expire = time() + 3600;
        }
        $path = $this->resourceManager->getUrl('root');

        return new Cookie('clientlogin_access_token', $accessToken->getToken(), $expire, $path);
    }
}
