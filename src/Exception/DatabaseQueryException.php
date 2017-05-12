<?php
declare(strict_types = 1);

namespace AllenJB\Sql\Exception;

class DatabaseQueryException extends \PDOException
{

    protected $statement = null;

    protected $values = null;

    protected $sqlErrorCode = null;


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


    public function getSqlErrorCode()
    {
        return $this->sqlErrorCode;
    }


    public static function fromPDOException(\PDOException $e) : DatabaseQueryException
    {
        $obj = new static($e->getMessage(), 0, $e);
        $obj->errorInfo = $e->errorInfo;
        if (isset($e->errorInfo[0]) && (!empty($e->errorInfo[0]))) {
            $obj->sqlErrorCode = $e->errorInfo[0];
        }
        return $obj;
    }
}
