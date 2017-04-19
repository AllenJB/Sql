<?php

namespace AllenJB\SqlQuery;

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
            if (is_object($value) && (($value instanceof \DateTimeImmutable) || ($value instanceof \DateTime))) {
                $clone = clone $value;
                $tz = new \DateTimeZone("UTC");
                // This reassignment handles DateTimeImmutable instances, which never modify themselves but return a new instance
                $clone = $clone->setTimezone($tz);
                $value = $clone->format('Y-m-d H:i:s');
            }
            if (is_bool($value)) {
                $value = ($value ? 1 : 0);
            }


            $retVal[$name] = $value;
        }

        return $retVal;
    }

}
