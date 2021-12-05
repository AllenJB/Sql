<?php
declare(strict_types=1);

namespace AllenJB\SqlQuery;

use PHPUnit\Framework\TestCase;

class ParametersToPositionalTest extends TestCase
{

    public function testNoParams(): void
    {
        $sql = "SELECT * FROM foo";
        $params = [];

        $retval = Utility::parametersToPositional($sql, $params);

        $this->assertEquals($sql, $retval["sql"]);
        $this->assertEmpty($retval["params"]);
    }


    public function testSingleParam(): void
    {
        $sql = "SELECT * FROM foo WHERE bar = :bar";
        $params = [
            "bar" => "qux",
        ];

        $retval = Utility::parametersToPositional($sql, $params);

        $expectedSql = "SELECT * FROM foo WHERE bar = ?";
        $this->assertEquals($expectedSql, $retval["sql"]);
        $this->assertCount(1, $retval["params"]);
        $this->assertEquals("qux", $retval["params"][0]);
    }


    public function testMultiParam(): void
    {
        $sql = "SELECT * FROM foo WHERE bar = :bar AND qux = :qux";
        $params = [
            "bar" => "quxbar",
            "qux" => "fooqux",
        ];

        $retval = Utility::parametersToPositional($sql, $params);

        $expectedSql = "SELECT * FROM foo WHERE bar = ? AND qux = ?";
        $this->assertEquals($expectedSql, $retval["sql"]);
        $this->assertCount(2, $retval["params"]);
        $this->assertEquals("quxbar", $retval["params"][0]);
        $this->assertEquals("fooqux", $retval["params"][1]);
    }


    public function testMultiParamSwapPos(): void
    {
        $sql = "SELECT * FROM foo WHERE bar = :bar AND qux = :qux";
        $params = [
            "qux" => "fooqux",
            "bar" => "quxbar",
        ];

        $retval = Utility::parametersToPositional($sql, $params);

        $expectedSql = "SELECT * FROM foo WHERE bar = ? AND qux = ?";
        $this->assertEquals($expectedSql, $retval["sql"]);
        $this->assertCount(2, $retval["params"]);
        $this->assertEquals("quxbar", $retval["params"][0]);
        $this->assertEquals("fooqux", $retval["params"][1]);
    }


    public function testSimilarlyNamedParam(): void
    {
        $sql = "SELECT * FROM foo WHERE bar = :bar AND qux = :barqux";
        $params = [
            "bar" => "quxbar",
            "barqux" => "fooqux",
        ];

        $retval = Utility::parametersToPositional($sql, $params);

        $expectedSql = "SELECT * FROM foo WHERE bar = ? AND qux = ?";
        $this->assertEquals($expectedSql, $retval["sql"]);
        $this->assertCount(2, $retval["params"]);
        $this->assertEquals("quxbar", $retval["params"][0]);
        $this->assertEquals("fooqux", $retval["params"][1]);
    }

}
