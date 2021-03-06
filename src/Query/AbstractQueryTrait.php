<?php
declare(strict_types = 1);

namespace AllenJB\SqlQuery;

use AllenJB\Sql\ExtendedPdo;

trait AbstractQueryTrait
{

    /**
     *
     * Gets the values to bind to placeholders.
     *
     * @return array
     *
     */
    public function getBindValues()
    {
        $retVal = [];
        foreach ($this->bind_values as $name => $value) {
            $retVal[$name] = $this->convertBindValue($value);
        }

        if (isset($this->bind_values_bulk)) {
            foreach ($this->bind_values_bulk as $name => $value) {
                $retVal[$name] = $this->convertBindValue($value);
            }
        }

        return $retVal;
    }


    protected function convertBindValue($value)
    {
        if (is_object($value) && (($value instanceof \DateTimeImmutable) || ($value instanceof \DateTime))) {
            $clone = clone $value;
            if (ExtendedPdo::getSetTimeZoneUTC()) {
                $tz = new \DateTimeZone("UTC");
                // This reassignment handles DateTimeImmutable instances, which never modify themselves but return a new instance
                $clone = $clone->setTimezone($tz);
            }
            $value = $clone->format('Y-m-d H:i:s');
        }
        if (is_bool($value)) {
            $value = ($value ? 1 : 0);
        }

        return $value;
    }

}
