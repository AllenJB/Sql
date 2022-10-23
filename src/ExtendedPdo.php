<?php
declare(strict_types=1);

namespace AllenJB\Sql;

use AllenJB\Sql\Exception\CollationException;
use AllenJB\Sql\Exception\DatabaseDeadlockException;
use AllenJB\Sql\Exception\DatabaseQueryException;
use AllenJB\Sql\Exception\TransactionAlreadyStartedException;
use Aura\Sql\Profiler\ProfilerInterface;

class ExtendedPdo extends \Aura\Sql\ExtendedPdo
{

    protected static ?ExtendedPdo $instance = null;

    protected static bool $setTimeZoneIsUTC = true;

    public const SQL_MODES_57 = [
        'ERROR_FOR_DIVISION_BY_ZERO',
        'NO_ZERO_DATE',
        'NO_ZERO_IN_DATE',
        'STRICT_ALL_TABLES',
        'ONLY_FULL_GROUP_BY',
        'NO_AUTO_CREATE_USER',
        'NO_ENGINE_SUBSTITUTION',
    ];

    public const SQL_MODES_80 = [
        'ERROR_FOR_DIVISION_BY_ZERO',
        'NO_ZERO_DATE',
        'NO_ZERO_IN_DATE',
        'STRICT_ALL_TABLES',
        'ONLY_FULL_GROUP_BY',
        'NO_ENGINE_SUBSTITUTION',
    ];

    protected static array $defaultSqlModes = self::SQL_MODES_57;

    protected ?array $transactionStartedInfo = null;

    protected ?string $lastQueryStatement = null;

    protected ?array $lastQueryBindValues = null;


    public function __construct(
        $dsn,
        $username = null,
        $password = null,
        array $options = [],
        array $queries = [],
        ProfilerInterface $profiler = null
    ) {
        if (stripos($dsn, 'mysql:') === 0) {
            $setSqlMode = "SET sql_mode = '" . implode(',', static::$defaultSqlModes) . "'";
            if (static::$setTimeZoneIsUTC) {
                $setSqlMode .= ", time_zone ='+00:00'";
            }
            $options[\PDO::MYSQL_ATTR_INIT_COMMAND] = $setSqlMode;

            if (stripos($dsn, 'charset=') === false) {
                $dsn .= ';charset=utf8mb4';
            }
        }
        $options[\PDO::ATTR_DEFAULT_FETCH_MODE] = \PDO::FETCH_OBJ;
        $options[\PDO::ATTR_EMULATE_PREPARES] = false;
        $options[\PDO::ATTR_STRINGIFY_FETCHES] = false;
        $options[\PDO::MYSQL_ATTR_DIRECT_QUERY] = false;

        parent::__construct($dsn, $username, $password, $options, $queries, $profiler);
    }


    /**
     * @return null|ExtendedPdo
     */
    public static function getInstance(): ?ExtendedPdo
    {
        return static::$instance;
    }


    public static function setInstance(ExtendedPdo $instance): void
    {
        static::$instance = $instance;
    }


    public static function setDefaultSqlModes(array $modes): void
    {
        static::$defaultSqlModes = $modes;
    }


    public static function getDefaultSqlModes(): array
    {
        return static::$defaultSqlModes;
    }


    public static function setTimeZoneIsUTC(bool $enabled): void
    {
        static::$setTimeZoneIsUTC = $enabled;
    }


    public static function getSetTimeZoneIsUTC(): bool
    {
        return static::$setTimeZoneIsUTC;
    }


    public function perform($statement, array $values = []): \PDOStatement
    {
        $this->recordQuery($statement, $values);
        try {
            return parent::perform($statement, $values);
        } catch (\PDOException $e) {
            throw $this->handleException($e, $statement, $values);
        }
    }


    protected function handleException(\PDOException $e, ?string $statement, ?array $values): \Exception
    {
        $regexIllegalMixMsg = '/1267 Illegal mix of collations/';
        if (stripos($e->getMessage(), 'Deadlock found') !== false) {
            $ex = DatabaseDeadlockException::fromPDOException($e);
        } elseif (stripos($e->getMessage(), '1366 Incorrect string value: \'\xF0') !== false) {
            $ex = CollationException::fromPDOException($e);
        } elseif (preg_match($regexIllegalMixMsg, $e->getMessage())) {
            $ex = CollationException::fromPDOException($e);
        } else {
            $ex = DatabaseQueryException::fromPDOException($e);
        }
        $ex->setStatement($statement);
        $ex->setValues($values);
        return $ex;
    }


    public function performWithDeadlockRetry(
        $statement,
        array $values = [],
        $maxTries = 3,
        $uSleep = 250
    ): \PDOStatement {
        $tries = 0;
        retryPerformWithDeadlock:
        try {
            $retVal = $this->perform($statement, $values);
        } catch (DatabaseDeadlockException $e) {
            $tries++;
            if ($tries < $maxTries) {
                usleep($uSleep);
                goto retryPerformWithDeadlock;
            }
            throw $e;
        }

        return $retVal;
    }


    /**
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function exec($statement)
    {
        try {
            $rs = parent::exec($statement);
        } catch (\PDOException $e) {
            throw $this->handleException($e, $statement, null);
        }

        return $rs;
    }


    public function query(string $query, ...$fetch): \PDOStatement
    {
        try {
            $result = parent::query($query, ...$fetch);
        } catch (\PDOException $e) {
            throw $this->handleException($e, $query, null);
        }

        return $result;
    }


    /**
     * @return string|false
     */
    #[\ReturnTypeWillChange]
    public function lastInsertId($name = null)
    {
        $retVal = parent::lastInsertId($name);
        if (("" . ($retVal ?? "")) === "") {
            throw new \UnexpectedValueException("No last insert id available");
        }
        return $retVal;
    }


    public function lastInsertIdAsInt($name = null): ?int
    {
        $insertId = $this->lastInsertId($name);
        if ($insertId === false) {
            return null;
        }
        if (! preg_match('/^[1-9][0-9]*$/', $retVal ?? '')) {
            throw new \UnexpectedValueException("Last insert id is not a number: " . $retVal);
        }
        return (int)$insertId;
    }


    public function beginTransaction(): bool
    {
        try {
            $retVal = parent::beginTransaction();
            $this->transactionStartedInfo = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        } catch (\PDOException $e) {
            if (stripos($e->getMessage(), 'already an active transaction') !== false) {
                $newException = new TransactionAlreadyStartedException(
                    "A transaction has already been started",
                    $e->getCode(),
                    $e
                );
                $newException->setPreviousTransactionTrace($this->transactionStartedInfo);
                throw $newException;
            }
            throw $e;
        }

        return $retVal;
    }


    public function rollBack(): bool
    {
        $this->transactionStartedInfo = null;
        return parent::rollBack();
    }


    public function commit(): bool
    {
        $this->transactionStartedInfo = null;
        return parent::commit();
    }


    public function close(): void
    {
        $this->disconnect();
    }


    protected function recordQuery($statement = null, array $values = []): void
    {
        $this->lastQueryStatement = $statement;
        $this->lastQueryBindValues = $values;
    }


    /**
     * Return the last SQL query that was executed.
     * Note that this version of the query will not be properly escaped, but can be useful enough for debugging.
     * ->quote() cannot be used here because the DB connection may no longer exist / be valid
     *
     * @return string|null Last query SQL
     */
    public function lastQuery(): ?string
    {
        $query = $this->lastQueryStatement;
        if (is_array($this->lastQueryBindValues)) {
            foreach ($this->lastQueryBindValues as $k => $v) {
                if (is_array($v)) {
                    $v = implode(', ', $v);
                }

                $query = str_replace(":{$k}", "'" . $v . "'", $query);
            }
        }
        return $query;
    }

}
