<?php

declare(strict_types=1);

namespace DevExtreme\Tests;

final class ConfigHelper
{
    public static function getConfiguration(): ?array
    {
        $configFileName = getenv('TEST_CONF');

        if (false === $configFileName) {
            $configFileName = 'config.json';
        }

        $configContent = file_get_contents($configFileName);

        return json_decode($configContent, true);
    }
}
