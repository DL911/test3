-- 实名认证表
CREATE TABLE IF NOT EXISTS `fa_user_verify` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户ID',
  `real_name` varchar(50) NOT NULL DEFAULT '' COMMENT '真实姓名',
  `id_card` varchar(30) NOT NULL DEFAULT '' COMMENT '身份证号',
  `front_image` varchar(500) NOT NULL DEFAULT '' COMMENT '身份证正面照',
  `back_image` varchar(500) NOT NULL DEFAULT '' COMMENT '身份证反面照',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=待审核 1=已通过 2=已拒绝',
  `reject_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '拒绝原因',
  `createtime` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `updatetime` int(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户实名认证';

-- 后台菜单（pid取彩票管理的父级ID，如不确定可填0放顶层，之后在后台拖动调整）
INSERT INTO `fa_auth_rule` (`type`, `pid`, `name`, `title`, `icon`, `weigh`, `status`, `createtime`, `updatetime`)
SELECT 'menu', 0, 'lottery/verify/index', '实名认证审核', 'fa fa-shield', 0, 'normal', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name` = 'lottery/verify/index');
