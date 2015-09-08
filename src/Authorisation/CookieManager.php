<?php

namespace Bolt\Extension\Bolt\ClientLogin\Authorisation;

use Bolt\Extension\Bolt\ClientLogin\Database\RecordManager;
use RandomLib\Generator;
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

    /**
     * Constructor.
     *
     * @param RecordManager $records
     * @param Generator     $random
     */
    public function __construct(RecordManager $records, Generator $random)
    {
        $this->records = $records;
        $this->random = $random;
    }

    /**
     * Create an authentication cookie.
     *
     * @param integer $userId
     * @param Token   $token
     *
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function create($userId, Token $token)
    {
        $value = $this->random->generateString(32);
        $cookie = new Cookie('bolt_clientlogin', $value, $token->getExpires(), '/', null, false, false);
        $this->commit($userId, $cookie, $token);

        return $cookie;
    }

    /**
     * Update an authentication cookie.
     *
     * @param integer $userId
     * @param Cookie  $cookie
     * @param Token   $token
     *
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function update($userId, Cookie $cookie, Token $token)
    {
        $cookie = new Cookie('bolt_clientlogin', $cookie->getValue(), $token->getExpires(), '/', null, false, false);
        $this->commit($userId, $cookie, $token);

        return $cookie;
    }

    /**
     * Insert/update a session record for an authentication cookie.
     *
     * @param Cookie $cookie
     * @param Token  $token
     *
     * @return integer|null
     */
    protected function commit($userId, Cookie $cookie, Token $token)
    {
        if ($this->records->getSessionBySessionId((string) $cookie)) {
            return $this->records->updateSession($userId, $cookie->getValue(), $token);
        } else {
            return $this->records->insertSession($userId, $cookie->getValue(), $token);
        }
    }
}
