<?php

declare(strict_types=1);

namespace DevExtreme\Tests;

use DevExtreme\DbSet;
use mysqli;
use PHPUnit\Framework\TestCase;

require_once('ConfigHelper.php');

class TestBase extends TestCase
{
    protected static mysqli $mySQL;
    protected static string $tableName;
    protected DbSet $dbSet;

    public static function setUpBeforeClass(): void
    {
        $dbConfig = ConfigHelper::getConfiguration();
        self::$tableName = $dbConfig['tableName'];
        self::$mySQL = new mysqli(
            $dbConfig['serverName'],
            $dbConfig['user'],
            $dbConfig['passowrd'],
            $dbConfig['databaseName']
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::$mySQL->close();
    }

    protected function setUp(): void
    {
        $this->dbSet = new DbSet(self::$mySQL, self::$tableName);
    }
}
