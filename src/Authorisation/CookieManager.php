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
     * @param Token $token
     *
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function create(Token $token)
    {
        $value = $this->random->generateString(32);
        $cookie = new Cookie('bolt_clientlogin', $value, $token->getExpires(), '/', null, false, false);
        $this->commit($cookie, $token);

        return $cookie;
    }

    /**
     * Update an authentication cookie.
     *
     * @param Cookie $cookie
     * @param Token $token
     *
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function update(Cookie $cookie, Token $token)
    {
        $cookie = new Cookie('bolt_clientlogin', (string) $cookie, $token->getExpires(), '/', null, false, false);
        $this->commit($cookie, $token);

        return $cookie;
    }

    /**
     * Insert/update a session record for an authentication cookie.
     *
     * @param Cookie $cookie
     * @param Token $token
     *
     * @return integer|null
     */
    protected function commit(Cookie $cookie, Token $token)
    {
        if ($session = $this->records->getSessionBySessionId((string) $cookie)) {
            return $this->records->updateSession($session['userid'], (string) $cookie, $token);
        } else {
            return $this->records->insertSession($session['userid'], (string) $cookie, $token);
        }
    }
}
