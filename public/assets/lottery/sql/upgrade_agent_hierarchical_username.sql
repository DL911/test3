-- 由管理员在发布代理分级用户名功能前手动执行。保留现有账号与开奖记录。
-- 代理用户名会逐级拼接，原 fa_user.username varchar(32) 不足以容纳。
ALTER TABLE `fa_user`
  MODIFY COLUMN `username` varchar(191) DEFAULT '' COMMENT '用户名',
  MODIFY COLUMN `nickname` varchar(191) DEFAULT '' COMMENT '昵称';
