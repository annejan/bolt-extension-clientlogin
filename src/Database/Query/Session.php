<?php

namespace Bolt\Extension\Bolt\ClientLogin\Database\Query;

/**
 * Client session table queries.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Session extends QueryBase
{
    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function queryByUserId()
    {
        return $this->getQueryBuilder()
            ->select('*')
            ->from($this->getTableNameSessions())
            ->where('userid = :userid')
            ->orderBy('lastseen', 'DESC')
        ;
    }
}
