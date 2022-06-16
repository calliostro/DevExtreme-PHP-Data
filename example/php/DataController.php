<?php

require_once('../../DevExtreme/LoadHelper.php');
spl_autoload_register(['DevExtreme\LoadHelper', 'loadModule']);

use DevExtreme\DbSet;
use DevExtreme\DataSourceLoader;

class DataController
{
    private $dbSet;

    public function __construct()
    {
        //TODO: use your database credentials
        $mySQL = new mysqli('serverName', 'userName', 'password', 'databaseName');
        $this->dbSet = new DbSet($mySQL, 'tableName');
    }

    public function fillDbIfEmpty()
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

    public function get($params)
    {
        $result = DataSourceLoader::load($this->dbSet, $params);

        if (!isset($result)) {
            $result = $this->dbSet->getLastError();
        }

        return $result;
    }

    public function post($values)
    {
        $result = $this->dbSet->insert($values);

        if (!isset($result)) {
            $result = $this->dbSet->getLastError();
        }

        return $result;
    }

    public function put($key, $values)
    {
        if (!isset($key) || (isset($values) && !is_array($values))) {
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

    public function delete($key)
    {
        if (!isset($key)) {
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
