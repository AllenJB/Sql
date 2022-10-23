<?php
declare(strict_types=1);

namespace AllenJB\SqlQuery;

class Utility
{

    /**
     * Convert named parameters to positional parameters (for when working with other libraries such as react/mysql)
     *
     * This method makes several assumptions:
     * - Each parameter key appears only once in the SQL (this is already a restriction when not using emulated prepares)
     * - Parameters never appear directly next to each other (no other characters between them)
     *
     * Returns an associative array containing keys "sql" (converted SQL string) and "params" (converted params array)
     * @param array<string, mixed> $params
     */
    public static function parametersToPositional(string $sql, array $params): array
    {
        if (count($params) < 1) {
            return [
                "sql" => $sql,
                "params" => $params,
            ];
        }

        // Sort the parameters by key, longest first
        $keys = array_keys($params);
        usort(
            $keys,
            function (string $a, string $b): int {
                return strlen($b) <=> strlen($a);
            }
        );

        // Find the positions of the parameters within the SQL string
        $tmpSql = $sql;
        $keyPos = [];
        foreach ($keys as $key) {
            $searchKey = $key;
            if (0 !== strpos($key, ":")) {
                $searchKey = ":" . $searchKey;
            }
            $keyPos[$key] = strpos($tmpSql, $searchKey);
            // Replace the key in the search string so we don't accidentally find it parsing similarly named (shorter) keys later
            // We replace it with a string of the same length to ensure we don't mess up the position value of other keys
            $tmpSql = str_replace($searchKey, str_repeat("?", strlen($searchKey)), $tmpSql);
        }

        // Sort by position to create the final params list in the correct order
        asort($keyPos);
        $finalParams = [];
        foreach ($keyPos as $key => $pos) {
            $finalParams[] = $params[$key];
        }

        $finalSql = preg_replace("/\?+/", "?", $tmpSql);

        return [
            "sql" => $finalSql,
            "params" => $finalParams,
        ];
    }

}
