<?php

$execute = null;
if (is_file(__DIR__ . '/thinkphp/base.php')) {
    require __DIR__ . '/thinkphp/base.php';
    $execute = function ($sql) { return \think\Db::execute($sql); };
} else {
    // 部分交付目录不包含 thinkphp 源码，使用 database.php 中 Env::get 的默认值连接。
    $source = file_get_contents(__DIR__ . '/application/database.php');
    $readDefault = function ($key, $fallback = '') use ($source) {
        $pattern = "/Env::get\\('database\\." . preg_quote($key, '/') . "',\\s*'([^']*)'\\)/";
        return preg_match($pattern, $source, $match) ? $match[1] : $fallback;
    };
    $host = $readDefault('hostname', '127.0.0.1');
    $database = $readDefault('database', 'fastadmin');
    $username = $readDefault('username', 'root');
    $password = $readDefault('password', '');
    $port = $readDefault('hostport', '3306') ?: '3306';
    $charset = $readDefault('charset', 'utf8mb4');
    try {
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset={$charset}", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        fwrite(STDERR, "Database connection failed. Start MySQL and retry: " . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    $execute = function ($sql) use ($pdo) { return $pdo->exec($sql); };
}

$files = [
    'public/assets/lottery/sql/agent_table.sql',
    'public/assets/lottery/sql/upgrade_agent_rebate_v2.sql',
];
foreach ($files as $file) {
    $sqls = explode(';', file_get_contents(__DIR__ . '/' . $file));
    foreach ($sqls as $sql) {
        if (!trim($sql)) continue;
        try {
            $execute($sql);
            echo "Success [{$file}]: " . substr(trim($sql), 0, 60) . "...\n";
        } catch (Exception $e) {
            echo "Skipped or Error [{$file}]: " . $e->getMessage() . "\n";
        }
    }
}
echo "Agent DB Migration Done.\n";
