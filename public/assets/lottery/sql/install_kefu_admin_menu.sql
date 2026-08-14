-- 客服工作台后台左侧菜单（可重复执行）
SET @kefu_parent = (SELECT `id` FROM `fa_auth_rule` WHERE `name`='lottery/kefu_center' LIMIT 1);
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`menutype`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'menu',0,'lottery/kefu_center','客服工作台','fa fa-comments','','','','1',NULL,UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),80,'normal'
FROM DUAL WHERE @kefu_parent IS NULL;
SET @kefu_parent = (SELECT `id` FROM `fa_auth_rule` WHERE `name`='lottery/kefu_center' LIMIT 1);

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`menutype`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'menu',@kefu_parent,'lottery/kefu/index','在线会话','fa fa-commenting','','','','1','addtabs',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),100,'normal'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='lottery/kefu/index');

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`menutype`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'menu',@kefu_parent,'lottery/kefu_channel/index','通道管理','fa fa-cogs','','','','1','addtabs',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),90,'normal'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='lottery/kefu_channel/index');
