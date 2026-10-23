-- 代理仅与直属下级划转余额；由管理员手动执行一次。
CREATE TABLE IF NOT EXISTS `fa_agent_balance_transfer` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(40) NOT NULL,
  `request_id` varchar(64) NOT NULL COMMENT '客户端操作标识，防止重复提交',
  `agent_id` int(10) unsigned NOT NULL,
  `sub_id` int(10) unsigned NOT NULL,
  `direction` varchar(8) NOT NULL COMMENT 'give加款/take扣款',
  `amount` decimal(12,2) NOT NULL,
  `remark` varchar(200) NOT NULL DEFAULT '',
  `from_user_id` int(10) unsigned NOT NULL,
  `to_user_id` int(10) unsigned NOT NULL,
  `from_before` decimal(12,2) NOT NULL,
  `from_after` decimal(12,2) NOT NULL,
  `to_before` decimal(12,2) NOT NULL,
  `to_after` decimal(12,2) NOT NULL,
  `operator_ip` varchar(45) NOT NULL DEFAULT '',
  `createtime` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  UNIQUE KEY `uk_agent_request` (`agent_id`,`request_id`),
  KEY `idx_agent_time` (`agent_id`,`createtime`),
  KEY `idx_sub_time` (`sub_id`,`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='代理与直属下级余额划转记录';
