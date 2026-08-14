-- 三类业务客服、公告内容、宣传图和客服备注升级脚本（执行一次）
ALTER TABLE `fa_lottery_kefu_channel`
  ADD COLUMN `announcement` varchar(500) NOT NULL DEFAULT '' COMMENT '滚动公告' AFTER `description`,
  ADD COLUMN `image` varchar(255) NOT NULL DEFAULT '' COMMENT '宣传图片' AFTER `announcement`,
  ADD COLUMN `intro` text COMMENT '玩法介绍' AFTER `image`;

CREATE TABLE IF NOT EXISTS `fa_lottery_kefu_user_remark` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `channel` varchar(32) NOT NULL DEFAULT 'general',
  `remark` varchar(100) NOT NULL DEFAULT '',
  `admin_id` int(10) unsigned NOT NULL DEFAULT 0,
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_user_channel` (`user_id`,`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客服用户备注';

INSERT INTO `fa_lottery_kefu_channel` (`code`,`name`,`description`,`announcement`,`image`,`intro`,`icon`,`color`,`weigh`,`status`,`createtime`,`updatetime`) VALUES
('wanli','万丽百家乐开户窗口','万丽百家乐开户咨询','欢迎咨询万丽百家乐开户业务','','请在聊天窗口提交您的开户需求，客服将为您详细介绍开户流程。','fa-gem','#ef4444',90,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('dongfang','东方汇百家乐','东方汇百家乐开户咨询','欢迎咨询东方汇百家乐业务','','请在聊天窗口说明您的咨询内容，客服将尽快为您服务。','fa-chess-queen','#8b5cf6',80,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('crown','皇冠足球开户窗口','皇冠足球开户咨询','欢迎咨询皇冠足球开户业务','','请联系在线客服了解开户流程、玩法及相关注意事项。','fa-futbol','#0ea5e9',70,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`),`description`=VALUES(`description`),`icon`=VALUES(`icon`),`color`=VALUES(`color`),`weigh`=VALUES(`weigh`),`status`=1,`updatetime`=UNIX_TIMESTAMP();
