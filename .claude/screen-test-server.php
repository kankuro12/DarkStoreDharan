<?php

// Local screen-testing router: serves the app against a throwaway database so the
// developer's real database/database.sqlite is never migrated or modified.
$database = dirname(__DIR__).'/database/screen-test.sqlite';

putenv("DB_DATABASE={$database}");
$_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = $database;
putenv('APP_URL=http://localhost:8001');
$_ENV['APP_URL'] = $_SERVER['APP_URL'] = 'http://localhost:8001';

chdir(dirname(__DIR__).'/public');

return require dirname(__DIR__).'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
