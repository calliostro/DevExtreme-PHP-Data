<?php

declare(strict_types=1);

namespace DevExtreme;

final class LoadHelper
{
    public static function loadModule(string $className): void
    {
        $namespaceNamePos = strpos($className, __NAMESPACE__);

        if (0 === $namespaceNamePos) {
            $subFolderPath = substr($className, $namespaceNamePos + strlen(__NAMESPACE__));
            $filePath = __DIR__ . str_replace("\\", DIRECTORY_SEPARATOR, $subFolderPath) . '.php';
            require_once($filePath);
        }
    }
}
