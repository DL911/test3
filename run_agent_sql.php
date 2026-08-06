<?php
require 'thinkphp/base.php';
$sql = file_get_contents('public/assets/lottery/sql/agent_table.sql');
$sqls = explode(';', $sql);
foreach ($sqls as $s) {
    if (trim($s)) {
        try {
            \think\Db::execute($s);
            echo "Success: " . substr(trim($s), 0, 50) . "...\n";
        } catch (\Exception $e) {
            echo "Skipped or Error: " . $e->getMessage() . "\n";
        }
    }
}
echo "Agent DB Migration Done.\n";
