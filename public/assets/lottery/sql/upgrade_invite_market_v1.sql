-- 手动执行；先核对预检结果和备份。不要对历史 agent_level=0 账号批量开启邀请奖励。
-- 先执行本结构脚本；核对下方预检，再单独执行 rename_agent_accounts_xy.sql。
ALTER TABLE `fa_user` ADD COLUMN `account_source` varchar(16) NOT NULL DEFAULT 'legacy' COMMENT 'legacy历史/agent代理/invite邀请' AFTER `agent_level`;
UPDATE `fa_user` SET `account_source`='agent' WHERE `agent_level`>0;
CREATE INDEX `idx_account_source_pid` ON `fa_user` (`account_source`,`pid`);

CREATE TABLE IF NOT EXISTS `fa_invite_reward_config` (
  `id` tinyint unsigned NOT NULL DEFAULT 1,
  `self_rate` decimal(8,6) NOT NULL DEFAULT 0,
  `parent_rate` decimal(8,6) NOT NULL DEFAULT 0,
  `min_bet` decimal(12,2) NOT NULL DEFAULT 0,
  `status` tinyint NOT NULL DEFAULT 0,
  `updatetime` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='独立邀请市场奖励配置';
INSERT IGNORE INTO `fa_invite_reward_config` (`id`,`self_rate`,`parent_rate`,`min_bet`,`status`,`updatetime`)
VALUES (1,0,0,0,0,UNIX_TIMESTAMP());

-- 预检：以下任何一条有结果都先处理，禁止直接执行改名事务。
SELECT `id`,`username` FROM `fa_user` WHERE `account_source`='agent' AND LEFT(`username`,3)<>'xy_' AND CHAR_LENGTH(CONCAT('xy_',`username`))>20;
SELECT a.id,a.username,b.id AS collision_id FROM `fa_user` a JOIN `fa_user` b ON b.username=CONCAT('xy_',a.username) AND b.id<>a.id WHERE a.account_source='agent' AND LEFT(a.username,3)<>'xy_';
SELECT a.id,a.username,b.id AS nickname_collision_id FROM `fa_user` a JOIN `fa_user` b ON b.nickname=CONCAT('xy_',a.username) AND b.id<>a.id WHERE a.account_source='agent' AND LEFT(a.username,3)<>'xy_';
SELECT COUNT(*) AS rename_count FROM `fa_user` WHERE `account_source`='agent' AND LEFT(`username`,3)<>'xy_';
