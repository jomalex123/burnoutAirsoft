<?php

declare(strict_types=1);

echo 'PHP version: ' . PHP_VERSION . PHP_EOL;
echo 'PHP binary: ' . PHP_BINARY . PHP_EOL;
echo 'Loaded php.ini: ' . (php_ini_loaded_file() ?: 'none') . PHP_EOL;
echo 'PDO: ' . (class_exists('PDO') ? 'yes' : 'no') . PHP_EOL;
echo 'pdo_mysql: ' . (extension_loaded('pdo_mysql') ? 'yes' : 'no') . PHP_EOL;
echo 'curl: ' . (extension_loaded('curl') ? 'yes' : 'no') . PHP_EOL;
echo 'json: ' . (extension_loaded('json') ? 'yes' : 'no') . PHP_EOL;
