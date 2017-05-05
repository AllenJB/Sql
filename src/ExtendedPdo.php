<?php
declare(strict_types = 1);

namespace AllenJB\Sql;

use AllenJB\Sql\Exception\DatabaseDeadlockException;
use AllenJB\Sql\Exception\DatabaseQueryException;
use AllenJB\Sql\Exception\TransactionAlreadyStartedException;
use Aura\Sql\Profiler;
use Aura\SqlSchema\ColumnFactory;
use Aura\SqlSchema\MysqlSchema;

class ExtendedPdo extends \Aura\Sql\ExtendedPdo
{

    protected static $instance = null;

    protected static $setTimeZoneUTC = true;

    /**
     * @var MysqlSchema
     */
    protected static $schema;

    protected $transactionStartedInfo = null;

    protected $activeTransactions = 0;

    protected $warnTransactionDepth = 3;

    protected $maxTransactionDepth = 7;

    protected static $emulateNestedTransactions = false;

    protected $lastQueryStatement = null;

    protected $lastQueryBindValues = null;


    public function __construct(
        $dsn,
        $username = null,
        $password = null,
        array $options = [],
        array $attributes = []
    )
    {
        if (stripos($dsn, 'mysql:') === 0) {
            $modes = [
                'ERROR_FOR_DIVISION_BY_ZERO',
                'NO_ZERO_DATE',
                'NO_ZERO_IN_DATE',
                'STRICT_ALL_TABLES',
                'ONLY_FULL_GROUP_BY',
                'NO_AUTO_CREATE_USER',
                'NO_ENGINE_SUBSTITUTION',
            ];
            $setSqlMode = "SET sql_mode = '" . implode(',', $modes) . "'";
            if (static::$setTimeZoneUTC) {
                $setSqlMode .= ", time_zone ='+00:00'";
            }
            $options[\PDO::MYSQL_ATTR_INIT_COMMAND] = $setSqlMode;

            if (stripos($dsn, 'charset=') === false) {
                $dsn .= ';charset=utf8mb4';
            }
        }
        $attributes[\PDO::ATTR_DEFAULT_FETCH_MODE] = \PDO::FETCH_OBJ;
        $attributes[\PDO::ATTR_EMULATE_PREPARES] = false;
        $attributes[\PDO::ATTR_STRINGIFY_FETCHES] = false;
        $attributes[\PDO::MYSQL_ATTR_DIRECT_QUERY] = false;

        parent::__construct($dsn, $username, $password, $options, $attributes);

        $this->setProfiler(new Profiler());
        $this->getProfiler()->setActive(false);
        if (stripos($dsn, 'mysql:') === 0) {
            $columnFactory = new ColumnFactory();
            $schema = new MysqlSchema($this, $columnFactory);
            static::setSchema($schema);
        }
    }


    /**
     * @return null|ExtendedPdo
     */
    public static function getInstance() : ?ExtendedPdo
    {
        return static::$instance;
    }


    public static function setInstance(ExtendedPdo $instance)
    {
        static::$instance = $instance;
    }


    public static function setTimeZoneUTC(bool $enabled) : void
    {
        static::$setTimeZoneUTC = $enabled;
    }


    public static function getSetTimeZoneUTC() : bool
    {
        return static::$setTimeZoneUTC;
    }


    public function setEmulateNestedTransactions(bool $enabled) : void
    {
        static::$emulateNestedTransactions = $enabled;
    }


    public function setTransactionDepthWarningLevel(int $maxTransactionDepth) : void
    {
        $this->warnTransactionDepth = $maxTransactionDepth;
    }


    public static function getSchema() : ?MysqlSchema
    {
        return static::$schema;
    }


    public static function setSchema(MysqlSchema $schema) : void
    {
        static::$schema = $schema;
    }


    public function perform($statement, array $values = [])
    {
        try {
            $retVal = parent::perform($statement, $values);
        } catch (\PDOException $e) {
            if (stripos($e->getMessage(), 'Deadlock found') !== false) {
                $ex = new DatabaseDeadlockException($e->getMessage(), 0, $e);
            } else {
                $ex = new DatabaseQueryException($e->getMessage(), 0, $e);
            }
            $ex->setStatement($statement);
            $ex->setValues($values);
            throw $ex;
        }
        return $retVal;
    }


    public function lastInsertId($name = null)
    {
        $retVal = parent::lastInsertId($name);
        if (("" . ($retVal ?? "")) === "") {
            throw new \UnexpectedValueException("No last insert id available");
        }
        if (! preg_match('/^[1-9][0-9]*$/', $retVal ?? '')) {
            throw new \UnexpectedValueException("Last insert id is not a number: " . $retVal);
        }
        return (int) $retVal;
    }


    public function beginTransaction()
    {
        if (static::$emulateNestedTransactions) {
            if ($this->activeTransactions > 0) {
                return $this->emulateNestedTransactionStart();
            }
        }

        try {
            $retVal = parent::beginTransaction();
            $this->transactionStartedInfo = debug_backtrace();
            $this->activeTransactions++;
        } catch (\PDOException $e) {
            if (stripos($e->getMessage(), 'already an active transaction') !== false) {
                $newException = new TransactionAlreadyStartedException("A transaction has already been started", $e->getCode(), $e);
                $newException->setPreviousTransactionTrace($this->transactionStartedInfo);
                throw $newException;
            }
            throw $e;
        }

        return $retVal;
    }


    protected function emulateNestedTransactionStart() : bool
    {
        $this->exec(sprintf("SAVEPOINT T%d", $this->activeTransactions));
        $this->activeTransactions++;

        if ($this->activeTransactions >= $this->warnTransactionDepth) {
            trigger_error("Nested transactions at depth {$this->activeTransactions}", E_USER_WARNING);
        }
        if ($this->activeTransactions >= $this->maxTransactionDepth) {
            throw new DatabaseQueryException("Reached max nested transaction depth ({$this->activeTransactions})");
        }

        return true;
    }


    public function rollBack()
    {
        if ($this->activeTransactions < 1) {
            throw new \UnexpectedValueException("No active transactions");
        }

        if (static::$emulateNestedTransactions) {

            if ($this->activeTransactions > 1) {
                $this->activeTransactions--;
                $this->exec(sprintf("ROLLBACK TO SAVEPOINT T%d", $this->activeTransactions));
                return true;
            }
        }

        $this->transactionStartedInfo = null;
        $this->activeTransactions--;
        return parent::rollBack();
    }


    public function commit()
    {
        if ($this->activeTransactions < 1) {
            throw new \UnexpectedValueException("No active transactions");
        }

        if (static::$emulateNestedTransactions) {
            if ($this->activeTransactions > 1) {
                $this->activeTransactions--;
                $this->exec(sprintf("RELEASE SAVEPOINT T%d", $this->activeTransactions));
                return true;
            }
        }

        $this->transactionStartedInfo = null;
        $this->activeTransactions--;
        return parent::commit();
    }


    /**
     * Returns the number of active transactions.
     *
     * Transactions wrapper that provides emulated nested transaction support via InnoDB savepoints
     *
     * @return int
     */
    public function activeTransactions() : int
    {
        return $this->activeTransactions;
    }


    public function close() : void
    {
        $this->disconnect();
    }


    protected function endProfile($statement = null, array $values = [])
    {
        $this->lastQueryStatement = $statement;
        $this->lastQueryBindValues = $values;
        return parent::endProfile($statement, $values);
    }


    /**
     * Return the last SQL query that was executed
     *
     * @return string Last query SQL
     */
    protected function lastProfile() : ?string
    {
        $profiler = $this->getProfiler();
        if ($profiler === null) {
            return null;
        }
        $profiles = $profiler->getProfiles();
        return end($profiles);
    }


    /**
     * Return the last SQL query that was executed.
     * Note that this version of the query will not be properly escaped, but can be useful enough for debugging
     *
     * @return string Last query SQL
     */
    public function lastQuery() : ?string
    {
        $query = $this->lastQueryStatement;
        if (is_array($this->lastQueryBindValues)) {
            foreach ($this->lastQueryBindValues as $k => $v) {
                if (is_array($v)) {
                    $v = implode(', ', $v);
                }

                $query = str_replace(":{$k}", $v, $query);
            }
        }
        return $query;
    }

}
