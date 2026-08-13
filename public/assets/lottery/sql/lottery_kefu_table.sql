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
('finance','充值提现','充值、提现及到账问题','fa-credit-card','#f59e0b',90,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('lottery','投注咨询','投注、开奖及规则问题','fa-ticket','#3b82f6',80,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());
