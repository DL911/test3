-- 用户安全问题答案（执行一次）
CREATE TABLE IF NOT EXISTS `fa_user_security_answer` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `answer_teacher` varchar(255) NOT NULL COMMENT '小学班主任姓名答案哈希',
  `answer_hometown` varchar(255) NOT NULL COMMENT '童年居住地答案哈希',
  `answer_friend` varchar(255) NOT NULL COMMENT '难忘朋友姓名答案哈希',
  `createtime` int(10) unsigned NOT NULL DEFAULT 0,
  `updatetime` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户安全问题答案';
