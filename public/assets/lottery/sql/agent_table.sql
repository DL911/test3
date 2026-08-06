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
