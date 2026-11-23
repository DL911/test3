-- 仅在 upgrade_invite_market_v1.sql 的长度、用户名冲突、昵称冲突三项预检均无结果后手动执行。
-- 建议先备份 fa_user。只改当前代理体系账号，不触碰历史账号和邀请体系账号。
START TRANSACTION;
UPDATE `fa_user` SET `nickname`=CONCAT('xy_',`username`)
WHERE `account_source`='agent' AND LEFT(`username`,3)<>'xy_' AND `nickname`=`username`;
UPDATE `fa_user` SET `username`=CONCAT('xy_',`username`)
WHERE `account_source`='agent' AND LEFT(`username`,3)<>'xy_';
COMMIT;
