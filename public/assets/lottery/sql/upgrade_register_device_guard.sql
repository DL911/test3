-- 注册设备/IP风控升级脚本（执行一次）
CREATE TABLE IF NOT EXISTS `fa_user_register_device` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT '注册用户ID',
  `device_hash` char(64) NOT NULL COMMENT '设备标识SHA-256',
  `register_ip` varchar(45) NOT NULL DEFAULT '' COMMENT '注册IP',
  `user_agent` varchar(255) NOT NULL DEFAULT '' COMMENT '注册浏览器信息',
  `createtime` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_device_hash` (`device_hash`),
  UNIQUE KEY `uk_user_id` (`user_id`),
  KEY `idx_ip_time` (`register_ip`,`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户注册设备风控记录';
