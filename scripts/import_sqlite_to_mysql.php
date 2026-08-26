<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sqlitePath = getenv('SQLITE_PATH') ?: $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.sqlite';

$mysqlHost = getenv('DB_HOST') ?: '127.0.0.1';
$mysqlPort = getenv('DB_PORT') ?: '3306';
$mysqlDatabase = getenv('DB_DATABASE') ?: 'laravel';
$mysqlUsername = getenv('DB_USERNAME') ?: 'root';
$mysqlPassword = getenv('DB_PASSWORD') ?: '';

if (! is_file($sqlitePath)) {
    fwrite(STDERR, "SQLite file not found: {$sqlitePath}" . PHP_EOL);
    exit(1);
}

$sqlite = new PDO('sqlite:' . $sqlitePath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$mysql = new PDO(
    "mysql:host={$mysqlHost};port={$mysqlPort};dbname={$mysqlDatabase};charset=utf8mb4",
    $mysqlUsername,
    $mysqlPassword,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
);

function mysql_ident(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function sqlite_ident(string $identifier): string
{
    return '"' . str_replace('"', '""', $identifier) . '"';
}

$tables = $sqlite
    ->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
    ->fetchAll(PDO::FETCH_COLUMN);

if ($tables === []) {
    fwrite(STDERR, 'No SQLite tables found.' . PHP_EOL);
    exit(1);
}

$mysqlTables = $mysql->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$missingTables = array_values(array_diff($tables, $mysqlTables));

if ($missingTables !== []) {
    fwrite(STDERR, 'Missing MySQL tables: ' . implode(', ', $missingTables) . PHP_EOL);
    exit(1);
}

$mysql->beginTransaction();
$mysql->exec('SET FOREIGN_KEY_CHECKS=0');

try {
    foreach ($tables as $table) {
        $mysql->exec('TRUNCATE TABLE ' . mysql_ident($table));
    }

    $counts = [];

    foreach ($tables as $table) {
        $columns = $sqlite->query('PRAGMA table_info(' . sqlite_ident($table) . ')')->fetchAll();
        $columnNames = array_map(static fn (array $column): string => $column['name'], $columns);

        if ($columnNames === []) {
            $counts[$table] = 0;
            continue;
        }

        $selectSql = 'SELECT ' . implode(', ', array_map('sqlite_ident', $columnNames)) . ' FROM ' . sqlite_ident($table);
        $rows = $sqlite->query($selectSql);

        $insertSql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            mysql_ident($table),
            implode(', ', array_map('mysql_ident', $columnNames)),
            implode(', ', array_fill(0, count($columnNames), '?')),
        );
        $insert = $mysql->prepare($insertSql);

        $count = 0;
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $insert->execute(array_values($row));
            $count++;
        }

        $counts[$table] = $count;
        echo "{$table}: {$count}" . PHP_EOL;
    }

    $mysql->exec('SET FOREIGN_KEY_CHECKS=1');
    $mysql->commit();
} catch (Throwable $exception) {
    $mysql->rollBack();
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

