ALTER TABLE `fa_user` ADD COLUMN `pid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '上级代理ID' AFTER `id`;

CREATE TABLE IF NOT EXISTS `fa_lottery_commission` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT '代理用户ID',
  `sub_id` int(10) unsigned NOT NULL COMMENT '下级用户ID',
  `type` varchar(50) NOT NULL COMMENT '奖励类型(bet_rebate:下级投注返佣)',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '佣金金额',
  `remark` varchar(255) DEFAULT '' COMMENT '备注',
  `createtime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `sub_id` (`sub_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='全民代理佣金记录';

CREATE TABLE IF NOT EXISTS `fa_user_register_device` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT '注册用户ID',
  `device_hash` char(64) NOT NULL COMMENT '设备标识SHA-256',
  `register_ip` varchar(45) NOT NULL DEFAULT '' COMMENT '注册IP',
  `user_agent` varchar(255) NOT NULL DEFAULT '' COMMENT '注册浏览器信息',
  `createtime` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_device_hash` (`device_hash`), UNIQUE KEY `uk_user_id` (`user_id`),
  KEY `idx_ip_time` (`register_ip`,`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户注册设备风控记录';
