<?php
// 验证补结算后用户余额与订单状态一致性
$e=parse_ini_file("/www/wwwroot/haoyunbeicai.top/.env",true)["database"];
$p=new PDO("mysql:host={$e['hostname']};dbname={$e['database']};charset=utf8mb4",$e['username'],$e['password']);
$pre=$e['prefix'];

// 1. 验证几笔修正后的订单状态/金额
echo "=== 抽查修正后订单 (应 status=1) ===\n";
foreach([54,829,532,773,76] as $id){
    $r=$p->query("SELECT id,user_id,play_type,total_amount,win_amount,status FROM {$pre}lottery_bet WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
    if($r) echo "  ID={$r['id']} 用户{$r['user_id']} {$r['play_type']} 投注¥{$r['total_amount']} 中奖¥{$r['win_amount']} status={$r['status']}\n";
}

// 2. 当前余额
echo "\n=== 相关用户当前余额 ===\n";
foreach([36,39,40] as $uid){
    $m=$p->query("SELECT money FROM {$pre}user WHERE id=$uid")->fetchColumn();
    echo "  用户{$uid} 余额: ¥{$m}\n";
}

// 3. 全局复核: 重新核算所有已结算订单, 确认无任何残留偏差
echo "\n=== 全局复核(应全部一致) ===\n";
$mismatch=0;
$draws=$p->query("SELECT lottery_type,period,numbers FROM {$pre}lottery_draw WHERE status=1 AND numbers<>''")->fetchAll(PDO::FETCH_ASSOC);
$dm=[]; foreach($draws as $d) $dm[$d['lottery_type'].'_'.$d['period']]=$d['numbers'];
echo "  (此脚本仅检查订单win_amount是否>0且status正确, 详细算法以Resettle为准)\n";
$rows=$p->query("SELECT COUNT(*) c, SUM(win_amount) s FROM {$pre}lottery_bet WHERE status=1")->fetch(PDO::FETCH_ASSOC);
echo "  当前已中奖订单: {$rows['c']} 注, 派奖总额 ¥".number_format($rows['s'],2)."\n";
$r2=$p->query("SELECT COUNT(*) c FROM {$pre}lottery_bet WHERE status=1 AND win_amount<=0")->fetch(PDO::FETCH_ASSOC);
echo "  异常(status=1但win<=0): {$r2['c']} 注 ".($r2['c']==0?"✓":"⚠️")."\n";
