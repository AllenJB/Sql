<?php
declare(strict_types = 1);

namespace AllenJB\Sql\Exception;

class DatabaseQueryException extends \PDOException
{

    protected ?string $statement = null;

    protected ?array $values = null;

    protected ?string $sqlErrorCode = null;


    public function setStatement(?string $statement)
    {
        $this->statement = $statement;
    }


    public function getStatement(): ?string
    {
        return $this->statement;
    }


    public function setValues(array $values)
    {
        $this->values = $values;
    }


    public function getValues(): ?array
    {
        return $this->values;
    }


    public function getSqlErrorCode(): ?string
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
