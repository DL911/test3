-- ============================================
-- 站内转账表
-- ============================================
CREATE TABLE IF NOT EXISTS `fa_transfer_order` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(32) NOT NULL DEFAULT '' COMMENT '转账单号',
  `from_user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '转出用户ID',
  `to_user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '转入用户ID',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '转账金额',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '转账备注',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=成功 0=失败',
  `createtime` int(11) unsigned NOT NULL DEFAULT '0',
  `updatetime` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_from` (`from_user_id`),
  KEY `idx_to` (`to_user_id`),
  KEY `idx_order` (`order_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='站内转账记录';
