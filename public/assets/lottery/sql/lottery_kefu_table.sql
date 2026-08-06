CREATE TABLE IF NOT EXISTS `fa_lottery_kefu_message` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT '用户ID(提问者)',
  `admin_id` int(10) unsigned DEFAULT '0' COMMENT '管理员ID(0表示未关联特定客服)',
  `sender_type` enum('user','admin') NOT NULL COMMENT '发送方身份 (user:用户发出的, admin:客服回复的)',
  `content` text NOT NULL COMMENT '消息内容',
  `is_read` tinyint(1) DEFAULT '0' COMMENT '是否已读(0:未读, 1:已读)',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客服聊天记录表';
