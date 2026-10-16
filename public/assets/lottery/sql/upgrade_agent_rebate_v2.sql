-- 代理开户与多级级差返水升级（可由 run_agent_sql.php 执行）
ALTER TABLE `fa_user` ADD COLUMN `is_agent` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否代理 0否1是' AFTER `pid`;
ALTER TABLE `fa_user` ADD COLUMN `can_open_account` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否允许开户' AFTER `is_agent`;
ALTER TABLE `fa_user` ADD COLUMN `agent_level` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '代理层级' AFTER `can_open_account`;
ALTER TABLE `fa_user` ADD INDEX `idx_agent_pid` (`pid`,`is_agent`,`status`);

CREATE TABLE IF NOT EXISTS `fa_agent_config` (
  `id` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `registration_mode` varchar(20) NOT NULL DEFAULT 'agent_only' COMMENT '注册模式',
  `max_rebate_rate` decimal(8,6) NOT NULL DEFAULT 0.090000 COMMENT '系统最高返水比例',
  `min_rate_gap` decimal(8,6) NOT NULL DEFAULT 0.000000 COMMENT '最小级差',
  `unlock_mode` varchar(20) NOT NULL DEFAULT 'next_day' COMMENT '解锁模式',
  `claim_mode` varchar(20) NOT NULL DEFAULT 'manual' COMMENT '领取模式',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `createtime` int(10) unsigned NOT NULL DEFAULT 0,
  `updatetime` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='代理返水系统配置';

INSERT IGNORE INTO `fa_agent_config`
(`id`,`registration_mode`,`max_rebate_rate`,`min_rate_gap`,`unlock_mode`,`claim_mode`,`status`,`createtime`,`updatetime`)
VALUES (1,'agent_only',0.090000,0.000000,'next_day','manual',1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

ALTER TABLE `fa_xima_record` ADD COLUMN `reward_type` varchar(20) NOT NULL DEFAULT 'self' COMMENT 'self自身/level_diff级差' AFTER `type`;
ALTER TABLE `fa_xima_record` ADD COLUMN `level_distance` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '距投注人层级' AFTER `source_user_id`;
ALTER TABLE `fa_xima_record` ADD COLUMN `beneficiary_rate` decimal(8,6) NOT NULL DEFAULT 0.000000 COMMENT '受益人比例快照' AFTER `xima_rate`;
ALTER TABLE `fa_xima_record` ADD COLUMN `child_rate` decimal(8,6) NOT NULL DEFAULT 0.000000 COMMENT '下一级比例快照' AFTER `beneficiary_rate`;
ALTER TABLE `fa_xima_record` ADD COLUMN `unlock_date` date DEFAULT NULL COMMENT '可领取日期' AFTER `xima_amount`;
ALTER TABLE `fa_xima_record` ADD COLUMN `claim_batch_no` varchar(32) NOT NULL DEFAULT '' COMMENT '领取批次号' AFTER `unlock_date`;
ALTER TABLE `fa_xima_record` ADD COLUMN `cancel_time` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '作废时间' AFTER `claim_time`;
ALTER TABLE `fa_xima_record` ADD COLUMN `cancel_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '作废原因' AFTER `cancel_time`;
ALTER TABLE `fa_xima_record` ADD UNIQUE KEY `uk_bet_beneficiary_type` (`bet_order_no`,`user_id`,`reward_type`);
ALTER TABLE `fa_xima_record` ADD INDEX `idx_user_unlock_status` (`user_id`,`unlock_date`,`status`);

UPDATE `fa_xima_record`
SET `reward_type` = IF(`type`='self','self','level_diff'),
    `unlock_date` = DATE(FROM_UNIXTIME(`createtime` + 86400))
WHERE `unlock_date` IS NULL;

CREATE TABLE IF NOT EXISTS `fa_xima_claim_batch` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `batch_no` varchar(32) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `record_count` int(10) unsigned NOT NULL DEFAULT 0,
  `claim_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance_before` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance_after` decimal(12,2) NOT NULL DEFAULT 0.00,
  `client_ip` varchar(45) NOT NULL DEFAULT '',
  `createtime` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_batch_no` (`batch_no`),
  KEY `idx_user_time` (`user_id`,`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='返水领取批次';
