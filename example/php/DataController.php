<?php

declare(strict_types=1);

require_once('../../DevExtreme/LoadHelper.php');
spl_autoload_register(['DevExtreme\LoadHelper', 'loadModule']);

use DevExtreme\DbSet;
use DevExtreme\DataSourceLoader;

final class DataController
{
    private DbSet $dbSet;

    public function __construct()
    {
        //TODO: use your database credentials
        $mySQL = new mysqli('serverName', 'userName', 'password', 'databaseName');
        $this->dbSet = new DbSet($mySQL, 'tableName');
    }

    /**
     * @throws \Exception
     */
    public function fillDbIfEmpty(): void
    {
        if ($this->dbSet->getCount() == 0) {
            $curDateString = '2013-1-1';

            for ($i = 1; $i <= 10000; $i++) {
                $curDT = new DateTime($curDateString);
                $curDT->add(new DateInterval('P' . strval(rand(1, 1500)) . 'D'));

                $item = [
                    'Name' => 'Name_' . strval(rand(1, 100)),
                    'Category' => 'Category_' . strval(rand(1, 30)),
                    'CustomerName' => 'Customer_' . strval(rand(1, 50)),
                    'BDate' => $curDT->format('Y-m-d'),
                ];

                $this->dbSet->insert($item);
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function get(array $params): array|string|null
    {
        $result = DataSourceLoader::load($this->dbSet, $params);

        if ($result == null) {
            $result = $this->dbSet->getLastError();
        }

        return $result;
    }

    public function post(array $values): int|string|null
    {
        $result = $this->dbSet->insert($values);

        if ($result == null) {
            $result = $this->dbSet->getLastError();
        }

        return $result;
    }

    /**
     * @throws \Exception
     */
    public function put(mixed $key, ?array $values): int|string|null
    {
        if ($values != null && !is_array($values)) {
            throw new Exception('Invalid params');
        }

        if (!is_array($key)) {
            $keyVal = $key;
            $key = [];
            $key['ID'] = $keyVal;
        }

        $result = $this->dbSet->update($key, $values);

        if (!isset($result)) {
            $result = $this->dbSet->getLastError();
        }

        return $result;
    }

    /**
     * @throws \Exception
     */
    public function delete(mixed $key): int|string|null
    {
        if ($key == null) {
            throw new Exception('Invalid params');
        }

        if (!is_array($key)) {
            $keyVal = $key;
            $key = [];
            $key['ID'] = $keyVal;
        }

        $result = $this->dbSet->delete($key);

        if (!isset($result)) {
            $result = $this->dbSet->getLastError();
        }

        return $result;
    }
}
