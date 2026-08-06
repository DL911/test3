<?php
try { 
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=fucai3d;charset=utf8mb4', 'fucai3d', 'fucai3d'); 
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
    $stmt = $pdo->query("SELECT id FROM fa_auth_rule WHERE name='lottery_finance'"); 
    $pid = $stmt->fetchColumn(); 
    if($pid) { 
        $stmt2 = $pdo->query("SELECT id FROM fa_auth_rule WHERE name='lottery/payment_config/index'"); 
        if(!$stmt2->fetchColumn()){ 
            $pdo->exec("INSERT INTO fa_auth_rule(type,pid,name,title,icon,url,`condition`,remark,ismenu,createtime,updatetime,weigh,status) VALUES ('menu', {$pid}, 'lottery/payment_config/index', '收款码配置', 'fa fa-qrcode', '', '', '', 1, ".time().", ".time().", 0, 'normal')"); 
            echo 'Inserted Menu. '; 
        } else { 
            echo 'Menu Exists. '; 
        } 
    } else { 
        echo 'Parent lottery_finance not found. '; 
    } 
} catch(Exception $e){ 
    echo $e->getMessage(); 
} 

// Clear cache
$d = __DIR__.'/application/admin/controller/lottery';
// Add js files for fastadmin automatically logic bindings if needed, but not strictly needed if we just use normal views without fast-ajax initialization
$cacheDb = __DIR__.'/runtime/cache'; 
foreach(glob($cacheDb.'/*') as $f) {if(is_file($f)) unlink($f);}
echo 'Done.';
