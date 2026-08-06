-- 给充值和提现订单表加 usdt_address 字段
-- 在服务器 MySQL 中执行（宝塔面板 → phpMyAdmin → SQL 标签 → 粘贴执行）

ALTER TABLE `fa_recharge_order`
ADD COLUMN `usdt_address` VARCHAR(200) DEFAULT '' COMMENT 'USDT钱包地址' AFTER `pay_type`;

ALTER TABLE `fa_withdraw_order`
ADD COLUMN `usdt_address` VARCHAR(200) DEFAULT '' COMMENT 'USDT钱包地址' AFTER `withdraw_type`;
