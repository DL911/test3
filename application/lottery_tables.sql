-- ============================================
-- 彩票投注系统 数据库表
-- 前缀: fa_
-- 仅支持: 福彩3D, 排列三
-- ============================================

-- 开奖记录表
CREATE TABLE IF NOT EXISTS `fa_lottery_draw` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
    `lottery_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '彩种 1=福彩3D 2=排列三',
    `period` varchar(20) NOT NULL DEFAULT '' COMMENT '期号',
    `numbers` varchar(30) NOT NULL DEFAULT '' COMMENT '开奖号码(逗号分隔)',
    `sum_value` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT '号码总和',
    `draw_time` datetime DEFAULT NULL COMMENT '开奖时间',
    `next_draw_time` datetime DEFAULT NULL COMMENT '下期开奖时间',
    `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=待开奖 1=已开奖',
    `createtime` int(10) unsigned DEFAULT NULL COMMENT '创建时间',
    `updatetime` int(10) unsigned DEFAULT NULL COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_type_period` (`lottery_type`, `period`),
    KEY `idx_status` (`status`),
    KEY `idx_draw_time` (`draw_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='开奖记录表';

-- 投注记录表
CREATE TABLE IF NOT EXISTS `fa_lottery_bet` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
    `order_no` varchar(32) NOT NULL DEFAULT '' COMMENT '订单号',
    `user_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '用户ID',
    `lottery_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '彩种 1=福彩3D 2=排列三',
    `period` varchar(20) NOT NULL DEFAULT '' COMMENT '投注期号',
    `play_type` varchar(30) NOT NULL DEFAULT '' COMMENT '玩法类型',
    `play_sub` varchar(30) NOT NULL DEFAULT '' COMMENT '子玩法/位置',
    `panel_type` varchar(20) NOT NULL DEFAULT 'shuangmian' COMMENT '盘口 shuangmian/biaozhun',
    `bet_content` text COMMENT '投注内容(JSON)',
    `bet_count` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '注数',
    `bet_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '单注金额',
    `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '总投注金额',
    `odds` decimal(8,2) NOT NULL DEFAULT 0.00 COMMENT '赔率',
    `win_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '中奖金额',
    `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=待开奖 1=已中奖 2=未中奖 3=已撤单',
    `settle_time` datetime DEFAULT NULL COMMENT '结算时间',
    `createtime` int(10) unsigned DEFAULT NULL COMMENT '创建时间',
    `updatetime` int(10) unsigned DEFAULT NULL COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order_no` (`order_no`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_type_period` (`lottery_type`, `period`),
    KEY `idx_status` (`status`),
    KEY `idx_createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='投注记录表';

-- 彩种配置表
CREATE TABLE IF NOT EXISTS `fa_lottery_config` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
    `lottery_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '彩种',
    `name` varchar(30) NOT NULL DEFAULT '' COMMENT '彩种名称',
    `code` varchar(20) NOT NULL DEFAULT '' COMMENT '彩种代码',
    `icon` varchar(10) NOT NULL DEFAULT '' COMMENT '图标文字',
    `draw_interval` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '开奖间隔(秒)',
    `draw_start_time` time DEFAULT NULL COMMENT '每日开奖时间',
    `draw_end_time` time DEFAULT NULL COMMENT '每日结束时间',
    `bet_close_seconds` int(10) unsigned NOT NULL DEFAULT 30 COMMENT '封盘秒数',
    `min_bet` decimal(10,2) NOT NULL DEFAULT 1.00 COMMENT '最低投注金额',
    `max_bet` decimal(10,2) NOT NULL DEFAULT 10000.00 COMMENT '最高投注金额',
    `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0=禁用 1=启用',
    `weigh` int(10) NOT NULL DEFAULT 0 COMMENT '排序权重',
    `createtime` int(10) unsigned DEFAULT NULL COMMENT '创建时间',
    `updatetime` int(10) unsigned DEFAULT NULL COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_code` (`code`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='彩种配置表';

-- 玩法赔率表
CREATE TABLE IF NOT EXISTS `fa_lottery_odds` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
    `lottery_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '彩种',
    `play_type` varchar(30) NOT NULL DEFAULT '' COMMENT '玩法类型',
    `bet_key` varchar(30) NOT NULL DEFAULT '' COMMENT '投注项标识',
    `bet_name` varchar(30) NOT NULL DEFAULT '' COMMENT '投注项名称',
    `odds` decimal(8,2) NOT NULL DEFAULT 0.00 COMMENT '赔率',
    `max_odds` decimal(8,2) NOT NULL DEFAULT 0.00 COMMENT '最高赔率',
    `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0=禁用 1=启用',
    `createtime` int(10) unsigned DEFAULT NULL COMMENT '创建时间',
    `updatetime` int(10) unsigned DEFAULT NULL COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_type_play` (`lottery_type`, `play_type`),
    KEY `idx_bet_key` (`bet_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='玩法赔率表';

-- ============================================
-- 初始数据
-- ============================================

-- 彩种配置
INSERT INTO `fa_lottery_config` (`lottery_type`, `name`, `code`, `icon`, `draw_interval`, `draw_start_time`, `draw_end_time`, `bet_close_seconds`, `min_bet`, `max_bet`, `status`, `weigh`, `createtime`) VALUES
(1, '福彩3D', 'fc3d', '福', 86400, '21:15:00', '21:15:00', 60, 1.00, 50000.00, 1, 100, UNIX_TIMESTAMP()),
(2, '排列三', 'pl3', '排', 86400, '20:30:00', '20:30:00', 60, 1.00, 50000.00, 1, 99, UNIX_TIMESTAMP());

-- 赔率数据 (福彩3D)
INSERT INTO `fa_lottery_odds` (`lottery_type`, `play_type`, `bet_key`, `bet_name`, `odds`, `status`, `createtime`) VALUES
-- 快捷/双面 大小单双质合
(1, 'kuaijie', 'da', '大', 1.97, 1, UNIX_TIMESTAMP()),
(1, 'kuaijie', 'xiao', '小', 1.97, 1, UNIX_TIMESTAMP()),
(1, 'kuaijie', 'dan', '单', 1.97, 1, UNIX_TIMESTAMP()),
(1, 'kuaijie', 'shuang', '双', 1.97, 1, UNIX_TIMESTAMP()),
(1, 'kuaijie', 'zhi', '质', 1.97, 1, UNIX_TIMESTAMP()),
(1, 'kuaijie', 'he', '合', 1.97, 1, UNIX_TIMESTAMP()),
-- 定位号码 0-9
(1, 'dingwei', 'num_0', '0', 9.85, 1, UNIX_TIMESTAMP()),
(1, 'dingwei', 'num_1', '1', 9.85, 1, UNIX_TIMESTAMP()),
(1, 'dingwei', 'num_2', '2', 9.85, 1, UNIX_TIMESTAMP()),
(1, 'dingwei', 'num_3', '3', 9.85, 1, UNIX_TIMESTAMP()),
(1, 'dingwei', 'num_4', '4', 9.85, 1, UNIX_TIMESTAMP()),
(1, 'dingwei', 'num_5', '5', 9.85, 1, UNIX_TIMESTAMP()),
(1, 'dingwei', 'num_6', '6', 9.85, 1, UNIX_TIMESTAMP()),
(1, 'dingwei', 'num_7', '7', 9.85, 1, UNIX_TIMESTAMP()),
(1, 'dingwei', 'num_8', '8', 9.85, 1, UNIX_TIMESTAMP()),
(1, 'dingwei', 'num_9', '9', 9.85, 1, UNIX_TIMESTAMP()),
-- 组合玩法
(1, 'zuhe', 'yizi_zuhe', '一字组合', 3.20, 1, UNIX_TIMESTAMP()),
(1, 'zuhe', 'erzi_zuhe', '二字组合', 28.00, 1, UNIX_TIMESTAMP()),
(1, 'zuhe', 'sanzi_zuhe', '三字组合', 170.00, 1, UNIX_TIMESTAMP()),
-- 组选
(1, 'zuxuan', 'zusan', '组三', 173.00, 1, UNIX_TIMESTAMP()),
(1, 'zuxuan', 'zuliu', '组六', 86.50, 1, UNIX_TIMESTAMP()),
-- 直选
(1, 'zhixuan', 'zhixuan', '直选', 520.00, 1, UNIX_TIMESTAMP()),

-- 赔率数据 (排列三) 
(2, 'kuaijie', 'da', '大', 1.97, 1, UNIX_TIMESTAMP()),
(2, 'kuaijie', 'xiao', '小', 1.97, 1, UNIX_TIMESTAMP()),
(2, 'kuaijie', 'dan', '单', 1.97, 1, UNIX_TIMESTAMP()),
(2, 'kuaijie', 'shuang', '双', 1.97, 1, UNIX_TIMESTAMP()),
(2, 'kuaijie', 'zhi', '质', 1.97, 1, UNIX_TIMESTAMP()),
(2, 'kuaijie', 'he', '合', 1.97, 1, UNIX_TIMESTAMP()),
-- 龙虎
(2, 'longhu', 'long', '龙', 1.97, 1, UNIX_TIMESTAMP()),
(2, 'longhu', 'hu', '虎', 1.97, 1, UNIX_TIMESTAMP()),
-- 形态
(2, 'xingtai', 'baozi', '豹子', 180.00, 1, UNIX_TIMESTAMP()),
(2, 'xingtai', 'shunzi', '顺子', 55.00, 1, UNIX_TIMESTAMP()),
(2, 'xingtai', 'duizi', '对子', 18.00, 1, UNIX_TIMESTAMP()),
(2, 'xingtai', 'banshun', '半顺', 3.20, 1, UNIX_TIMESTAMP()),
(2, 'xingtai', 'zaliu', '杂六', 2.80, 1, UNIX_TIMESTAMP()),
-- 定位号码
(2, 'dingwei', 'num_0', '0', 9.85, 1, UNIX_TIMESTAMP()),
(2, 'dingwei', 'num_1', '1', 9.85, 1, UNIX_TIMESTAMP()),
(2, 'dingwei', 'num_2', '2', 9.85, 1, UNIX_TIMESTAMP()),
(2, 'dingwei', 'num_3', '3', 9.85, 1, UNIX_TIMESTAMP()),
(2, 'dingwei', 'num_4', '4', 9.85, 1, UNIX_TIMESTAMP()),
(2, 'dingwei', 'num_5', '5', 9.85, 1, UNIX_TIMESTAMP()),
(2, 'dingwei', 'num_6', '6', 9.85, 1, UNIX_TIMESTAMP()),
(2, 'dingwei', 'num_7', '7', 9.85, 1, UNIX_TIMESTAMP()),
(2, 'dingwei', 'num_8', '8', 9.85, 1, UNIX_TIMESTAMP()),
(2, 'dingwei', 'num_9', '9', 9.85, 1, UNIX_TIMESTAMP()),
-- 组选
(2, 'zuxuan', 'zusan', '组三', 173.00, 1, UNIX_TIMESTAMP()),
(2, 'zuxuan', 'zuliu', '组六', 86.50, 1, UNIX_TIMESTAMP()),
-- 直选
(2, 'zhixuan', 'zhixuan', '直选', 520.00, 1, UNIX_TIMESTAMP());

-- ============================================
-- 模拟开奖数据（近10期）
-- ============================================

-- 福彩3D 近10期
INSERT INTO `fa_lottery_draw` (`lottery_type`, `period`, `numbers`, `sum_value`, `draw_time`, `status`, `createtime`) VALUES
(1, '2026102', '4,2,0', 6,  '2026-04-22 21:15:00', 1, UNIX_TIMESTAMP()),
(1, '2026101', '1,8,4', 13, '2026-04-21 21:15:00', 1, UNIX_TIMESTAMP()),
(1, '2026100', '2,0,7', 9,  '2026-04-20 21:15:00', 1, UNIX_TIMESTAMP()),
(1, '2026099', '8,1,8', 17, '2026-04-19 21:15:00', 1, UNIX_TIMESTAMP()),
(1, '2026098', '5,1,3', 9,  '2026-04-18 21:15:00', 1, UNIX_TIMESTAMP()),
(1, '2026097', '9,7,4', 20, '2026-04-17 21:15:00', 1, UNIX_TIMESTAMP()),
(1, '2026096', '2,2,6', 10, '2026-04-16 21:15:00', 1, UNIX_TIMESTAMP()),
(1, '2026095', '8,1,8', 17, '2026-04-15 21:15:00', 1, UNIX_TIMESTAMP()),
(1, '2026094', '5,6,8', 19, '2026-04-14 21:15:00', 1, UNIX_TIMESTAMP()),
(1, '2026093', '4,1,8', 13, '2026-04-13 21:15:00', 1, UNIX_TIMESTAMP()),
-- 下一期（待开奖）
(1, '2026103', '', 0, '2026-04-23 21:15:00', 0, UNIX_TIMESTAMP()),

-- 排列三 近10期
(2, '2026102', '4,9,0', 13, '2026-04-22 20:30:00', 1, UNIX_TIMESTAMP()),
(2, '2026101', '0,9,9', 18, '2026-04-21 20:30:00', 1, UNIX_TIMESTAMP()),
(2, '2026100', '0,9,0', 9,  '2026-04-20 20:30:00', 1, UNIX_TIMESTAMP()),
(2, '2026099', '4,3,1', 8,  '2026-04-19 20:30:00', 1, UNIX_TIMESTAMP()),
(2, '2026098', '9,7,4', 20, '2026-04-18 20:30:00', 1, UNIX_TIMESTAMP()),
(2, '2026097', '2,2,6', 10, '2026-04-17 20:30:00', 1, UNIX_TIMESTAMP()),
(2, '2026096', '6,8,6', 20, '2026-04-16 20:30:00', 1, UNIX_TIMESTAMP()),
(2, '2026095', '5,6,8', 19, '2026-04-15 20:30:00', 1, UNIX_TIMESTAMP()),
(2, '2026094', '9,3,8', 20, '2026-04-14 20:30:00', 1, UNIX_TIMESTAMP()),
(2, '2026093', '1,7,2', 10, '2026-04-13 20:30:00', 1, UNIX_TIMESTAMP()),
-- 下一期（待开奖）
(2, '2026103', '', 0, '2026-04-23 20:30:00', 0, UNIX_TIMESTAMP());
