<?php

declare(strict_types=1);

namespace DevExtreme\Tests;

use DevExtreme\DbSet;
use PDO;
use PHPUnit\Framework\TestCase;

require_once('ConfigHelper.php');

class TestBase extends TestCase
{
    protected static PDO $pdo;
    protected static string $tableName;
    protected DbSet $dbSet;

    public static function setUpBeforeClass(): void
    {
        $dbConfig = ConfigHelper::getConfiguration();
        self::$tableName = $dbConfig['tableName'];
        self::$pdo = new PDO(
            "mysql:host={$dbConfig['serverName']};dbname={$dbConfig['databaseName']}",
            $dbConfig['user'],
            $dbConfig['passowrd']
        );
    }

    protected function setUp(): void
    {
        $this->dbSet = new DbSet(self::$pdo, self::$tableName);
    }
}
