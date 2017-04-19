<?php
declare(strict_types = 1);

namespace AllenJB\Sql\Exception;

class TransactionAlreadyStartedException extends \PDOException
{

    protected $previousTransactionTrace = null;


    public function setPreviousTransactionTrace(array $stackTrace) : void
    {
        $this->previousTransactionTrace = $stackTrace;
    }


    public function getPreviousTransactionTrace() : ?array
    {
        return $this->previousTransactionTrace;
    }

}
