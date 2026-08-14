CREATE TABLE IF NOT EXISTS `fa_lottery_kefu_message` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT '用户ID(提问者)',
  `admin_id` int(10) unsigned DEFAULT '0' COMMENT '管理员ID(0表示未关联特定客服)',
  `channel` varchar(32) NOT NULL DEFAULT 'general' COMMENT '客服业务通道',
  `sender_type` enum('user','admin') NOT NULL COMMENT '发送方身份 (user:用户发出的, admin:客服回复的)',
  `content` text NOT NULL COMMENT '消息内容',
  `is_read` tinyint(1) DEFAULT '0' COMMENT '是否已读(0:未读, 1:已读)',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_channel_id` (`user_id`,`channel`,`id`),
  KEY `idx_channel_unread` (`channel`,`sender_type`,`is_read`),
  KEY `createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客服聊天记录表';

CREATE TABLE IF NOT EXISTS `fa_lottery_kefu_channel` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL COMMENT '通道编码',
  `name` varchar(50) NOT NULL COMMENT '通道名称',
  `description` varchar(255) NOT NULL DEFAULT '' COMMENT '前端说明',
  `announcement` varchar(500) NOT NULL DEFAULT '' COMMENT '滚动公告',
  `image` varchar(255) NOT NULL DEFAULT '' COMMENT '宣传图片',
  `intro` text COMMENT '玩法介绍',
  `icon` varchar(50) NOT NULL DEFAULT 'fa-comments' COMMENT 'FontAwesome图标',
  `color` varchar(20) NOT NULL DEFAULT '#18bc9c' COMMENT '展示颜色',
  `weigh` int(10) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1启用0停用',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客服业务通道';

INSERT IGNORE INTO `fa_lottery_kefu_channel` (`code`,`name`,`description`,`icon`,`color`,`weigh`,`status`,`createtime`,`updatetime`) VALUES
('general','综合咨询','其他业务及综合问题','fa-comments','#18bc9c',100,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('wanli','万丽百家乐开户窗口','万丽百家乐开户咨询','fa-gem','#ef4444',90,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('dongfang','东方汇百家乐','东方汇百家乐开户咨询','fa-chess-queen','#8b5cf6',80,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('crown','皇冠足球开户窗口','皇冠足球开户咨询','fa-futbol','#0ea5e9',70,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

CREATE TABLE IF NOT EXISTS `fa_lottery_kefu_user_remark` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT, `user_id` int(10) unsigned NOT NULL,
  `channel` varchar(32) NOT NULL DEFAULT 'general', `remark` varchar(100) NOT NULL DEFAULT '',
  `admin_id` int(10) unsigned NOT NULL DEFAULT 0, `createtime` bigint(16) DEFAULT NULL, `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_user_channel` (`user_id`,`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客服用户备注';
