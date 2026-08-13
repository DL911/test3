-- 已有客服表的升级脚本（只需执行一次）
ALTER TABLE `fa_lottery_kefu_message` ADD COLUMN `channel` varchar(32) NOT NULL DEFAULT 'general' COMMENT '客服业务通道' AFTER `admin_id`;
ALTER TABLE `fa_lottery_kefu_message` ADD KEY `idx_user_channel_id` (`user_id`,`channel`,`id`), ADD KEY `idx_channel_unread` (`channel`,`sender_type`,`is_read`);

CREATE TABLE IF NOT EXISTS `fa_lottery_kefu_channel` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT, `code` varchar(32) NOT NULL, `name` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '', `icon` varchar(50) NOT NULL DEFAULT 'fa-comments',
  `color` varchar(20) NOT NULL DEFAULT '#18bc9c', `weigh` int(10) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1, `createtime` bigint(16) DEFAULT NULL, `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客服业务通道';
INSERT IGNORE INTO `fa_lottery_kefu_channel` (`code`,`name`,`description`,`icon`,`color`,`weigh`,`status`,`createtime`,`updatetime`) VALUES
('general','综合咨询','其他业务及综合问题','fa-comments','#18bc9c',100,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('finance','充值提现','充值、提现及到账问题','fa-credit-card','#f59e0b',90,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('lottery','投注咨询','投注、开奖及规则问题','fa-ticket','#3b82f6',80,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());
