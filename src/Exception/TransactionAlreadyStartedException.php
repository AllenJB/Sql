<?php
declare(strict_types=1);

namespace AllenJB\Sql\Exception;

class TransactionAlreadyStartedException extends \PDOException
{

    /**
     * @var array<int, array{function: string, line?: int, file?: string, class?: class-string, type?: '->'|'::', args?: array, object?: object}>|null
     */
    protected ?array $previousTransactionTrace = null;


    /**
     * @param array<int, array{function: string, line?: int, file?: string, class?: class-string, type?: '->'|'::', args?: array, object?: object}>|null $stackTrace
     */
    public function setPreviousTransactionTrace(array $stackTrace): void
    {
        $this->previousTransactionTrace = $stackTrace;
    }


    /**
     * @return array<int, array{function: string, line?: int, file?: string, class?: class-string, type?: '->'|'::', args?: array, object?: object}>|null
     */
    public function getPreviousTransactionTrace(): ?array
    {
        return $this->previousTransactionTrace;
    }

}
