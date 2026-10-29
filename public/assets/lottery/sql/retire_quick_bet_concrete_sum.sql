-- 仅由管理员手动执行。代码发布后，清理已下线的“具体和值”14项共用赔率配置。
-- 不触碰历史投注、快译审计及赔率修改日志；执行前请自行备份数据库。
DELETE FROM `fa_quick_bet_odds`
WHERE `lottery_type` = 0
  AND `play_code` IN (
    'sum_0','sum_1','sum_2','sum_3','sum_4','sum_5','sum_6',
    'sum_7','sum_8','sum_9','sum_10','sum_11','sum_12','sum_13'
  );
