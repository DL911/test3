-- ============================================
-- 福彩平台 - 财务管理完整SQL
-- 包含: 充值表、提现表、转账表、收款码配置、后台菜单
-- 导入方式: phpMyAdmin 或 命令行导入
-- ============================================

-- 1. 充值订单表
CREATE TABLE IF NOT EXISTS `fa_recharge_order` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(32) NOT NULL DEFAULT '' COMMENT '订单号',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '充值金额',
  `pay_type` varchar(20) NOT NULL DEFAULT '' COMMENT '支付方式: wechat/alipay/usdt',
  `pay_proof` varchar(500) NOT NULL DEFAULT '' COMMENT '转账凭证截图',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=待审核 1=已到账 2=已拒绝',
  `admin_remark` varchar(255) NOT NULL DEFAULT '' COMMENT '管理员备注',
  `createtime` int(11) unsigned NOT NULL DEFAULT '0',
  `updatetime` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_order` (`order_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值订单';

-- 2. 提现订单表
CREATE TABLE IF NOT EXISTS `fa_withdraw_order` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(32) NOT NULL DEFAULT '' COMMENT '订单号',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '提现金额',
  `withdraw_type` varchar(20) NOT NULL DEFAULT '' COMMENT '提现方式: wechat/alipay/usdt/bank',
  `account_info` varchar(500) NOT NULL DEFAULT '' COMMENT '收款账号信息(JSON)',
  `qr_image` varchar(500) NOT NULL DEFAULT '' COMMENT '用户收款码截图',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=待审核 1=已打款 2=已拒绝',
  `admin_remark` varchar(255) NOT NULL DEFAULT '' COMMENT '管理员备注',
  `createtime` int(11) unsigned NOT NULL DEFAULT '0',
  `updatetime` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='提现订单';

-- 3. 站内转账表
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

-- 4. 平台收款码配置表
CREATE TABLE IF NOT EXISTS `fa_payment_config` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `pay_type` varchar(20) NOT NULL DEFAULT '' COMMENT 'wechat/alipay/usdt',
  `title` varchar(50) NOT NULL DEFAULT '' COMMENT '显示名称',
  `qr_image` varchar(500) NOT NULL DEFAULT '' COMMENT '收款二维码图片',
  `account` varchar(200) NOT NULL DEFAULT '' COMMENT '收款账号/地址',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '提示信息',
  `min_amount` decimal(10,2) NOT NULL DEFAULT '10.00' COMMENT '最低充值',
  `max_amount` decimal(10,2) NOT NULL DEFAULT '50000.00' COMMENT '最高充值',
  `sort` int(5) NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=启用 0=停用',
  `createtime` int(11) unsigned NOT NULL DEFAULT '0',
  `updatetime` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='平台收款码配置';

-- 5. 插入默认收款码配置（后台修改qr_image字段上传图片路径）
INSERT IGNORE INTO `fa_payment_config` (`pay_type`,`title`,`qr_image`,`account`,`remark`,`min_amount`,`max_amount`,`sort`,`status`,`createtime`,`updatetime`) VALUES
('wechat','微信支付','','','请使用微信扫码转账，转账后务必上传截图',10.00,50000.00,1,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('alipay','支付宝','','','请使用支付宝扫码转账，转账后务必上传截图',10.00,50000.00,2,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('usdt','USDT(TRC20)','','','请转账至以下USDT地址(TRC20网络)',100.00,100000.00,3,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

-- 6. FastAdmin后台菜单（彩票财务管理）
-- 获取当前最大id避免冲突
SET @max_id = (SELECT IFNULL(MAX(id), 0) FROM `fa_auth_rule`);

-- 父菜单: 彩票财务管理
INSERT INTO `fa_auth_rule` (`id`,`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`) VALUES
(@max_id + 1, 'menu', 0, 'lottery_finance', '彩票财务', 'fa fa-rmb', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');

SET @pid = @max_id + 1;

-- 子菜单
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`createtime`,`updatetime`,`weigh`,`status`) VALUES
('menu', @pid, 'lottery/recharge/index', '充值审核', 'fa fa-plus-circle', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('menu', @pid, 'lottery/withdraw/index', '提现审核', 'fa fa-minus-circle', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'),
('menu', @pid, 'lottery/transfer/index', '转账记录', 'fa fa-exchange', '', '', '', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal');
