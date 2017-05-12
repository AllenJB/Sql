<?php
declare(strict_types = 1);

namespace AllenJB\Sql\Exception;

class DatabaseQueryException extends \PDOException
{

    protected $statement = null;

    protected $values = null;


    public function setStatement($statement)
    {
        $this->statement = $statement;
    }


    public function getStatement()
    {
        return $this->statement;
    }


    public function setValues(array $values)
    {
        $this->values = $values;
    }


    public function getValues()
    {
        return $this->values;
    }
}
