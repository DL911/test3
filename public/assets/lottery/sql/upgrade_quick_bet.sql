-- 快译模式：仅由管理员手动执行。默认玩法全部停用，核对赔率后逐项启用。
CREATE TABLE IF NOT EXISTS `fa_quick_bet_odds` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lottery_type` tinyint(3) unsigned NOT NULL,
  `play_code` varchar(32) NOT NULL,
  `odds` decimal(12,4) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `updatetime` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_type_play` (`lottery_type`,`play_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='快译玩法赔率';

CREATE TABLE IF NOT EXISTS `fa_quick_bet_odds_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `odds_id` int(10) unsigned NOT NULL,
  `admin_id` int(10) unsigned NOT NULL,
  `before_data` text NOT NULL,
  `after_data` text NOT NULL,
  `createtime` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`), KEY `idx_odds_time` (`odds_id`,`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='快译赔率修改记录';

CREATE TABLE IF NOT EXISTS `fa_quick_bet_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(40) NOT NULL,
  `request_id` varchar(64) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `lottery_type` tinyint(3) unsigned NOT NULL,
  `period` varchar(32) NOT NULL,
  `raw_text` text NOT NULL,
  `confirmed_items` text NOT NULL,
  `settings_version` varchar(64) NOT NULL,
  `createtime` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_order_no` (`order_no`), UNIQUE KEY `uk_user_request` (`user_id`,`request_id`), KEY `idx_user_time` (`user_id`,`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='快译原文与确认注单';

INSERT IGNORE INTO `fa_quick_bet_odds` (`lottery_type`,`play_code`,`odds`,`description`,`status`,`updatetime`) VALUES
(1,'direct',900,'三码顺序完全相同',0,UNIX_TIMESTAMP()),(1,'onepos',9,'指定位置号码相同',0,UNIX_TIMESTAMP()),(1,'twopos',90,'指定两位置号码相同',0,UNIX_TIMESTAMP()),
(1,'sumodd',1.8,'三位和值为单数',0,UNIX_TIMESTAMP()),(1,'sumeven',1.8,'三位和值为双数',0,UNIX_TIMESTAMP()),(1,'sumbig',1.8,'三位和值14至27',0,UNIX_TIMESTAMP()),(1,'sumsmall',1.8,'三位和值0至13',0,UNIX_TIMESTAMP()),
(1,'posodd',1.8,'指定位置为单数',0,UNIX_TIMESTAMP()),(1,'poseven',1.8,'指定位置为双数',0,UNIX_TIMESTAMP()),(1,'posbig',1.8,'指定位置数字5至9',0,UNIX_TIMESTAMP()),(1,'possmall',1.8,'指定位置数字0至4',0,UNIX_TIMESTAMP()),
(2,'direct',900,'三码顺序完全相同',0,UNIX_TIMESTAMP()),(2,'onepos',9,'指定位置号码相同',0,UNIX_TIMESTAMP()),(2,'twopos',90,'指定两位置号码相同',0,UNIX_TIMESTAMP()),
(2,'sumodd',1.8,'三位和值为单数',0,UNIX_TIMESTAMP()),(2,'sumeven',1.8,'三位和值为双数',0,UNIX_TIMESTAMP()),(2,'sumbig',1.8,'三位和值14至27',0,UNIX_TIMESTAMP()),(2,'sumsmall',1.8,'三位和值0至13',0,UNIX_TIMESTAMP()),
(2,'posodd',1.8,'指定位置为单数',0,UNIX_TIMESTAMP()),(2,'poseven',1.8,'指定位置为双数',0,UNIX_TIMESTAMP()),(2,'posbig',1.8,'指定位置数字5至9',0,UNIX_TIMESTAMP()),(2,'possmall',1.8,'指定位置数字0至4',0,UNIX_TIMESTAMP());

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`menutype`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',0,'lottery/quickodds/index','快译玩法赔率','fa fa-language','','','','1','addtabs',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),60,'normal'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `fa_auth_rule` WHERE `name`='lottery/quickodds/index');

INSERT INTO `fa_auth_rule` (`type`,`pid`,`name`,`title`,`icon`,`url`,`condition`,`remark`,`ismenu`,`menutype`,`createtime`,`updatetime`,`weigh`,`status`)
SELECT 'file',`id`,'lottery/quickodds/save','保存快译赔率','fa fa-save','','','','0',NULL,UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),59,'normal'
FROM `fa_auth_rule` WHERE `name`='lottery/quickodds/index'
AND NOT EXISTS (SELECT 1 FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name`='lottery/quickodds/save') AS existing_rule);
