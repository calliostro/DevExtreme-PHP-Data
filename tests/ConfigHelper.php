<?php

class ConfigHelper
{
    public static function GetConfiguration()
    {
        $configFileName = getenv('TEST_CONF');

        if ($configFileName === false) {
            $configFileName = 'config.json';
        }

        $configContent = file_get_contents($configFileName);

        return json_decode($configContent, true);
    }
}
