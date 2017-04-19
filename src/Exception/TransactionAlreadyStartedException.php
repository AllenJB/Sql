<?php

namespace AllenJB\Sql\Exception;

class TransactionAlreadyStartedException extends \PDOException
{

    protected $previousTransactionTrace = null;


    public function setPreviousTransactionTrace(array $stackTrace)
    {
        $this->previousTransactionTrace = $stackTrace;
    }


    public function getPreviousTransactionTrace()
    {
        return $this->previousTransactionTrace;
    }

}
