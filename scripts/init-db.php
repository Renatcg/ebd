<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Core/Config.php';
require_once dirname(__DIR__) . '/src/Core/Database.php';

Database::connection();

echo 'Banco inicializado usando driver ' . Database::driver() . PHP_EOL;
