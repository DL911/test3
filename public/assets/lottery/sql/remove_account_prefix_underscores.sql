-- 手动执行。先备份 fa_user，并先运行下方预检；有结果时先处理冲突或长度。
-- 只处理已标记来源、且以 xy_ / xj_ 开头的账号；历史开奖记录不受影响。
SELECT id, account_source, username FROM fa_user
WHERE (account_source='agent' AND LEFT(username,3)='xy_')
   OR (account_source='invite' AND LEFT(username,3)='xj_');

SELECT a.id, a.username, b.id AS collision_id FROM fa_user a
JOIN fa_user b ON b.username = CONCAT(LEFT(a.username,2),SUBSTRING(a.username,4)) AND b.id<>a.id
WHERE (a.account_source='agent' AND LEFT(a.username,3)='xy_')
   OR (a.account_source='invite' AND LEFT(a.username,3)='xj_');

SELECT a.id, a.username, b.id AS nickname_collision_id FROM fa_user a
JOIN fa_user b ON b.nickname = CONCAT(LEFT(a.username,2),SUBSTRING(a.username,4)) AND b.id<>a.id
WHERE a.nickname=a.username AND ((a.account_source='agent' AND LEFT(a.username,3)='xy_')
   OR (a.account_source='invite' AND LEFT(a.username,3)='xj_'));

-- 确认 collision_id 无结果后，再单独执行以下事务。
START TRANSACTION;
-- 旧版 xj_ 账号原先使用全局本人比例；在改名前保存为账号比例。
UPDATE fa_user u JOIN fa_invite_reward_config c ON c.id=1
SET u.self_rebate_rate=c.self_rate
WHERE u.account_source='invite' AND LEFT(u.username,3)='xj_' AND u.self_rebate_rate=0;
UPDATE fa_user SET nickname=CONCAT('xy',SUBSTRING(username,4))
WHERE account_source='agent' AND LEFT(username,3)='xy_' AND nickname=username;
UPDATE fa_user SET username=CONCAT('xy',SUBSTRING(username,4))
WHERE account_source='agent' AND LEFT(username,3)='xy_';
UPDATE fa_user SET nickname=CONCAT('xj',SUBSTRING(username,4))
WHERE account_source='invite' AND LEFT(username,3)='xj_' AND nickname=username;
UPDATE fa_user SET username=CONCAT('xj',SUBSTRING(username,4))
WHERE account_source='invite' AND LEFT(username,3)='xj_';
COMMIT;
