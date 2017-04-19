<?php
declare(strict_types = 1);

namespace AllenJB\SqlQuery;

use Aura\SqlQuery\AbstractQuery;

class QueryFactory extends \Aura\SqlQuery\QueryFactory
{

    /**
     *
     * Returns a new query object.
     *
     * @param string $query The query object type.
     *
     * @return AbstractQuery
     *
     */
    protected function newInstance($query)
    {
        if ($this->common) {
            $class = "AllenJB\\SqlQuery\\Common";
        } else {
            $class = "AllenJB\\SqlQuery\\{$this->db}";
        }

        $class .= "\\{$query}";

        if (!class_exists($class)) {
            return parent::newInstance($query);
        }

        return new $class(
            $this->getQuoter(),
            $this->newSeqBindPrefix()
        );
    }
}
