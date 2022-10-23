<?php
declare(strict_types = 1);

namespace AllenJB\SqlQuery;

use AllenJB\Sql\ExtendedPdo;

trait AbstractQueryTrait
{

    /**
     * Gets the values to bind to placeholders.
     *
     * @return array<string, mixed>
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


    /**
     * @param mixed $value
     * @return int|mixed|string
     */
    protected function convertBindValue($value)
    {
        if (($value instanceof \DateTimeImmutable) || ($value instanceof \DateTime)) {
            $clone = clone $value;
            if (ExtendedPdo::getSetTimeZoneIsUTC()) {
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
