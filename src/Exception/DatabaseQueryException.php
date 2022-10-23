<?php
declare(strict_types=1);

namespace AllenJB\Sql\Exception;

/** @phpstan-consistent-constructor */
class DatabaseQueryException extends \PDOException
{

    protected ?string $statement = null;

    /**
     * @var array<string, mixed>
     */
    protected ?array $values = null;

    protected ?string $sqlErrorCode = null;


    /**
     * @param string $message
     * @param int $code
     */
    public function __construct($message = "", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }


    public function setStatement(?string $statement): void
    {
        $this->statement = $statement;
    }


    public function getStatement(): ?string
    {
        return $this->statement;
    }


    /**
     * @param array<string, mixed>|null $values
     */
    public function setValues(?array $values): void
    {
        $this->values = $values;
    }


    /**
     * @return array<string, mixed>
     */
    public function getValues(): ?array
    {
        return $this->values;
    }


    public function getSqlErrorCode(): ?string
    {
        return $this->sqlErrorCode;
    }


    public static function fromPDOException(\PDOException $e): DatabaseQueryException
    {
        $obj = new static($e->getMessage(), 0, $e);
        $obj->errorInfo = $e->errorInfo;
        if (isset($e->errorInfo[0]) && (! empty($e->errorInfo[0]))) {
            $obj->sqlErrorCode = $e->errorInfo[0];
        }
        return $obj;
    }
}
