-- 仅新增后台只读报表菜单与查询权限；不修改投注、派奖、返水或资金表。
INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`menutype`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',0,'lottery/agentmarket/index','代理市场报表','fa fa-bar-chart','','','','1','addtabs',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),58,'normal'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='lottery/agentmarket/index');

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`menutype`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',`id`,'lottery/agentmarket/agents','搜索代理','fa fa-search','','','','0',NULL,UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),57,'normal'
FROM `fa_auth_rule` WHERE `name`='lottery/agentmarket/index'
AND NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='lottery/agentmarket/agents') AS existing_rule);
