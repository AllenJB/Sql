<?php
declare(strict_types = 1);

namespace AllenJB\SqlQuery\Mysql;

use AllenJB\SqlQuery\QueryFactory;

class DeleteTest extends \Aura\SqlQuery\Mysql\SelectTest
{

    public function setUp() : void
    {
        parent::setUp();

        $this->query_factory = new QueryFactory($this->db_type);
    }

}
