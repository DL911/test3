/**
 * ============================================
 * 彩票投注系统 - 完整前端逻辑
 * 支持: 福彩3D, 排列三
 * ============================================
 */

/* ==================================================================
   LOTTERY APP - 大厅页逻辑
   ================================================================== */
var LotteryApp = (function() {
    var countdownTimers = [];

    function initCountdowns() {
        document.querySelectorAll('.js-countdown').forEach(function(el) {
            var drawTime = el.getAttribute('data-draw-time');
            var seconds;
            if (drawTime) {
                // 根据开奖时间动态计算剩余秒数
                seconds = calcSecondsToDrawTime(drawTime);
            } else {
                seconds = parseInt(el.getAttribute('data-seconds')) || 0;
            }
            startCountdown(el, seconds);
        });
    }

    function calcSecondsToDrawTime(drawTimeStr) {
        var now = new Date();
        var parts = drawTimeStr.split(':');
        var target = new Date(now);
        target.setHours(parseInt(parts[0]), parseInt(parts[1]), parseInt(parts[2] || 0), 0);
        var diff = Math.floor((target - now) / 1000);
        if (diff <= 0) {
            // 今天的开奖时间已过，倒计时到明天
            target.setDate(target.getDate() + 1);
            diff = Math.floor((target - now) / 1000);
        }
        return diff;
    }

    function startCountdown(el, totalSeconds) {
        function update() {
            if (totalSeconds <= 0) { el.textContent = '00:00:00'; el.classList.add('urgent'); return; }
            var h = Math.floor(totalSeconds / 3600);
            var m = Math.floor((totalSeconds % 3600) / 60);
            var s = totalSeconds % 60;
            el.textContent = pad(h) + ':' + pad(m) + ':' + pad(s);
            if (totalSeconds < 60) el.classList.add('urgent');
            totalSeconds--;
        }
        update();
        var t = setInterval(function() { update(); if (totalSeconds < 0) clearInterval(t); }, 1000);
        countdownTimers.push(t);
    }

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    function initSidebarNav() {
        var items = document.querySelectorAll('.sidebar-item[data-cat]');
        items.forEach(function(item) {
            item.addEventListener('click', function(e) {
                e.preventDefault(); e.stopPropagation();
                items.forEach(function(i) { i.classList.remove('active'); });
                item.classList.add('active');
                filterCards(item.dataset.cat);
            });
        });
    }

    function initTypeTabNav() {
        var tabs = document.querySelectorAll('.type-tab[data-type]');
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                tabs.forEach(function(t) { t.classList.remove('active'); });
                tab.classList.add('active');
                var type = tab.dataset.type;
                // map tab types to card lottery values
                var filterType = type === '3d' ? 'fc3d' : type;
                filterCards(filterType);
                // also sync sidebar
                var sItems = document.querySelectorAll('.sidebar-item[data-cat]');
                sItems.forEach(function(si) {
                    si.classList.toggle('active', si.dataset.cat === filterType || filterType === 'all');
                });
            });
        });
    }

    function filterCards(type) {
        var cards = document.querySelectorAll('.lottery-card[data-lottery]');
        cards.forEach(function(card) {
            if (type === 'all' || card.dataset.lottery === type) {
                card.style.display = '';
                card.style.animation = 'fadeIn 0.3s ease';
            } else {
                card.style.display = 'none';
            }
        });
    }

    return { initCountdowns: initCountdowns, initSidebarNav: initSidebarNav, initTypeTabNav: initTypeTabNav };
})();

/* ==================================================================
   TOAST
   ================================================================== */
function showToast(msg, type) {
    type = type || 'info';
    var c = document.getElementById('toastContainer');
    if (!c) return;
    // 清除之前的 toast
    c.innerHTML = '';
    var t = document.createElement('div');
    t.className = 'toast-item ' + type;
    var icons = {success:'<i class="fa-solid fa-circle-check" style="font-size:20px;color:#27ae60;"></i>', error:'<i class="fa-solid fa-circle-xmark" style="font-size:20px;color:#e74c3c;"></i>', warning:'<i class="fa-solid fa-triangle-exclamation" style="font-size:20px;color:#f39c12;"></i>', info:'<i class="fa-solid fa-circle-info" style="font-size:20px;color:#3498db;"></i>'};
    t.innerHTML = (icons[type] || icons.info) + '<span>' + msg + '</span>';
    c.appendChild(t);
    setTimeout(function() { t.style.opacity = '0'; t.style.transform = 'scale(0.8)'; t.style.transition = 'all 0.3s'; setTimeout(function() { t.remove(); }, 300); }, 2500);
}

/* ==================================================================
   BET PAGE
   ================================================================== */
var BetPage = (function() {

    /* ---------------------------------------------------------------
       福彩3D 玩法配置
       号码范围: 0-9, 三位: 百位/十位/个位
       开奖号码: 000 ~ 999, 每天21:15开奖
       ---------------------------------------------------------------
       官方玩法:
       - 直选(单选): 按位精确匹配, 奖金1040元
       - 组选三(组三): 两位相同一位不同, 不论顺序, 奖金346元
       - 组选六(组六): 三位各不同, 不论顺序, 奖金173元
       - 1D(一字定位): 某一位号码一致
       - 2D(二字定位): 指定两位号码一致
       - 和值: 三位数之和(0-27)
       - 双面盘(非官方): 大小单双质合
       --------------------------------------------------------------- */
    var FC3D_PLAYS = {
        shuangmian: [
            { key: 'shuangmian', name: '双面', subs: [] },
            { key: 'yizi_zuhe', name: '一字组合', subs: [] },
            { key: 'erzi_zuhe', name: '二字组合', subs: [] },
            { key: 'sanzi_zuhe', name: '三字组合', subs: [] },
            { key: 'yizi_dingwei', name: '一字定位', subs: [] },
            { key: 'erzi_dingwei', name: '二字定位', subs: [
                { key: 'baishi', name: '百十' },
                { key: 'baige', name: '百个' },
                { key: 'shige', name: '十个' }
            ]},
            { key: 'sanzi_dingwei', name: '三字定位', subs: [] },
            { key: 'erzi_heshu', name: '二字和数', subs: [
                { key: 'baishi', name: '百十' },
                { key: 'baige', name: '百个' },
                { key: 'shige', name: '十个' }
            ]},
            { key: 'hezhi', name: '三字和数', subs: [] },
            { key: 'zusan', name: '组三', subs: [] },
            { key: 'zuliu', name: '组六', subs: [] }
        ],
        biaozhun: [
            // === 三星 ===
            { key: 'sx_zx_fushi', name: '三星直选复式', prize: 900, ui: 'row3', min: 1 },
            { key: 'sx_zx_danshi', name: '三星直选单式', prize: 900, ui: 'text' },
            { key: 'sx_zx_hezhi', name: '三星直选和值', prize: 900, ui: 'hz27' },
            { key: 'sx_zx_kuadu', name: '三星直选跨度', prize: 900, ui: 'kd9' },
            { key: 'sx_zx3_fushi', name: '三星组选三复式', prize: 300, ui: 'row1', min: 2 },
            { key: 'sx_zx3_danshi', name: '三星组选三单式', prize: 300, ui: 'text' },
            { key: 'sx_zx6_fushi', name: '三星组选六复式', prize: 150, ui: 'row1', min: 3 },
            { key: 'sx_hunhe', name: '三星混合组选', prize: '150~300', ui: 'text' },
            { key: 'sx_zx_hezhi2', name: '三星组选和值', prize: 150, ui: 'hz27' },
            { key: 'sx_zx_baodan', name: '三星组选包胆', prize: 150, ui: 'row1', min: 1, max: 1 },
            { key: 'sx_tx_fushi', name: '三星通选复式', prize: 900, ui: 'row3', min: 1 },
            { key: 'sx_tx_danshi', name: '三星通选单式', prize: 900, ui: 'text' },
            { key: 'sx_hzweishu', name: '三星和值尾数', prize: 0, ui: 'kd9' },
            // === 前二 ===
            { key: 'qe_zx_fushi', name: '前二直选复式', prize: 90, ui: 'row2_qe', min: 1 },
            { key: 'qe_zx_danshi', name: '前二直选单式', prize: 90, ui: 'text' },
            { key: 'qe_zx_hezhi', name: '前二直选和值', prize: 90, ui: 'hz18' },
            { key: 'qe_zx_kuadu', name: '前二直选跨度', prize: 90, ui: 'kd9' },
            { key: 'qe_zuxuan_fushi', name: '前二组选复式', prize: 45, ui: 'row1', min: 2 },
            { key: 'qe_zuxuan_danshi', name: '前二组选单式', prize: 45, ui: 'text' },
            { key: 'qe_zuxuan_hezhi', name: '前二组选和值', prize: 45, ui: 'hz18' },
            { key: 'qe_zuxuan_baodan', name: '前二组选包胆', prize: 45, ui: 'row1', min: 1, max: 1 },
            // === 后二 ===
            { key: 'he_zx_fushi', name: '后二直选复式', prize: 90, ui: 'row2_he', min: 1 },
            { key: 'he_zx_danshi', name: '后二直选单式', prize: 90, ui: 'text' },
            { key: 'he_zx_hezhi', name: '后二直选和值', prize: 90, ui: 'hz18' },
            { key: 'he_zx_kuadu', name: '后二直选跨度', prize: 90, ui: 'kd9' },
            { key: 'he_zuxuan_fushi', name: '后二组选复式', prize: 45, ui: 'row1', min: 2 },
            { key: 'he_zuxuan_danshi', name: '后二组选单式', prize: 45, ui: 'text' },
            { key: 'he_zuxuan_hezhi', name: '后二组选和值', prize: 45, ui: 'hz18' },
            { key: 'he_zuxuan_baodan', name: '后二组选包胆', prize: 45, ui: 'row1', min: 1, max: 1 },
            // === 定位胆 ===
            { key: 'dingweidan', name: '定位胆', prize: 9, ui: 'row3', min: 1 },
            // === 不定胆 ===
            { key: 'sx_yimabuding', name: '三星一码不定胆', prize: 3.3, ui: 'row1', min: 1 },
            { key: 'sx_ermabuding', name: '三星二码不定胆', prize: 16.6, ui: 'row1', min: 2 },
            // === 大小单双 ===
            { key: 'dxds', name: '大小单双（百十个）', prize: 3.94, ui: 'dxds_pos3' }
        ]
    };

    /* --------------------------------------------------------------- 标准盘导航配置 --------------------------------------------------------------- */
    var FC3D_BZP_NAV = {
        remen: ['dingweidan','sx_zx_fushi','sx_zx3_fushi','sx_zx6_fushi','sx_yimabuding'],
        quanbu: [
            { key:'sanxing', name:'三星', groups:[
                { name:'直选', plays:['sx_zx_fushi','sx_zx_danshi','sx_zx_hezhi','sx_zx_kuadu'] },
                { name:'组选', plays:['sx_zx3_fushi','sx_zx6_fushi'] }
            ]},
            { key:'qianer', name:'前二', groups:[
                { name:'直选', plays:['qe_zx_fushi','qe_zx_danshi','qe_zx_hezhi','qe_zx_kuadu'] },
                { name:'组选', plays:['qe_zuxuan_fushi','qe_zuxuan_danshi','qe_zuxuan_hezhi','qe_zuxuan_baodan'] }
            ]},
            { key:'houer', name:'后二', groups:[
                { name:'直选', plays:['he_zx_fushi','he_zx_danshi','he_zx_hezhi','he_zx_kuadu'] },
                { name:'组选', plays:['he_zuxuan_fushi','he_zuxuan_danshi','he_zuxuan_hezhi','he_zuxuan_baodan'] }
            ]},
            { key:'dingweidan_c', name:'定位胆', groups:[{ name:'定位胆', plays:['dingweidan'] }]},
            { key:'budindan', name:'不定胆', groups:[{ name:'不定胆', plays:['sx_yimabuding','sx_ermabuding'] }]}
        ]
    };

    var PL3_BZP_NAV = {
        remen: ['q3_zx_fushi','q3_zx6_fushi','q3_zx3_fushi','dingweidan','q3_yimabuding'],
        quanbu: [
            { key:'wuxing', name:'五星', groups:[
                { name:'直选', plays:['wx_zx_fushi','wx_zx_danshi','wx_zx_zuhe'] },
                { name:'组选', plays:['wx_zx120','wx_zx60','wx_zx30','wx_zx20','wx_zx10','wx_zx5'] }
            ]},
            { key:'sixing', name:'四星', groups:[
                { name:'前四直选', plays:['q4_zx_fushi','q4_zx_danshi','q4_zx_zuhe'] },
                { name:'前四组选', plays:['q4_zx24','q4_zx12','q4_zx6','q4_zx4'] },
                { name:'后四直选', plays:['h4_zx_fushi','h4_zx_danshi','h4_zx_zuhe'] },
                { name:'后四组选', plays:['h4_zx24','h4_zx12','h4_zx6','h4_zx4'] }
            ]},
            { key:'qiansan', name:'前三', groups:[
                { name:'直选', plays:['q3_zx_fushi','q3_zx_danshi','q3_zx_zuhe','q3_zx_hezhi','q3_zx_kuadu'] },
                { name:'组选', plays:['q3_zx3_fushi','q3_zx6_fushi','q3_zx3_danshi','q3_zx6_danshi'] },
                { name:'其他', plays:['q3_hzweishu'] }
            ]},
            { key:'zhongsan', name:'中三', groups:[
                { name:'直选', plays:['z3_zx_fushi','z3_zx_danshi','z3_zx_zuhe','z3_zx_hezhi','z3_zx_kuadu'] },
                { name:'组选', plays:['z3_zx3_fushi','z3_zx6_fushi','z3_zx3_danshi','z3_zx6_danshi'] },
                { name:'其他', plays:['z3_hzweishu'] }
            ]},
            { key:'housan', name:'后三', groups:[
                { name:'直选', plays:['h3_zx_fushi','h3_zx_danshi','h3_zx_zuhe','h3_zx_hezhi','h3_zx_kuadu'] },
                { name:'组选', plays:['h3_zx3_fushi','h3_zx6_fushi','h3_zx3_danshi','h3_zx6_danshi'] },
                { name:'其他', plays:['h3_hzweishu'] }
            ]},
            { key:'qianer', name:'前二', groups:[
                { name:'直选', plays:['qe_zx_fushi','qe_zx_danshi','qe_zx_hezhi','qe_zx_kuadu'] },
                { name:'组选', plays:['qe_zuxuan_fushi','qe_zuxuan_danshi','qe_zuxuan_hezhi','qe_zuxuan_baodan'] }
            ]},
            { key:'houer', name:'后二', groups:[
                { name:'直选', plays:['he_zx_fushi','he_zx_danshi','he_zx_hezhi','he_zx_kuadu'] },
                { name:'组选', plays:['he_zuxuan_fushi','he_zuxuan_danshi','he_zuxuan_hezhi','he_zuxuan_baodan'] }
            ]},
            { key:'dingweidan_c', name:'定位胆', groups:[{ name:'定位胆', plays:['dingweidan'] }]},
            { key:'budindan', name:'不定胆', groups:[
                { name:'三星', plays:['q3_yimabuding','q3_ermabuding','z3_yimabuding','z3_ermabuding','h3_yimabuding','h3_ermabuding'] },
                { name:'四星', plays:['q4_yimabuding','q4_ermabuding','q4_sanmabuding','h4_yimabuding','h4_ermabuding','h4_sanmabuding'] },
                { name:'五星', plays:['wx_yimabuding','wx_ermabuding','wx_sanmabuding'] }
            ]},
            { key:'dxds_c', name:'大小单双', groups:[
                { name:'组合', plays:['dxds_q2','dxds_q3','dxds_h2','dxds_h3'] },
                { name:'和値', plays:['dxds_wx_hz','dxds_sx_hz'] }
            ]},
            { key:'quwei', name:'趣味', groups:[{ name:'趣味', plays:['yffs','hscs','sxbx','sjfc'] }]},
            { key:'renxuan2', name:'任选2', groups:[
                { name:'直选', plays:['rx2_zx_fushi','rx2_zx_danshi','rx2_zx_hezhi'] },
                { name:'组选', plays:['rx2_zuxuan_fushi','rx2_zuxuan_danshi','rx2_zuxuan_hezhi','rx2_zuxuan_baodan'] }
            ]},
            { key:'renxuan3', name:'任选3', groups:[
                { name:'直选', plays:['rx3_zx_fushi','rx3_zx_danshi','rx3_zx_hezhi'] },
                { name:'组选', plays:['rx3_zx3_fushi','rx3_zx6_fushi','rx3_hunhe','rx3_zx_hezhi2'] }
            ]},
            { key:'renxuan4', name:'任选4', groups:[
                { name:'直选', plays:['rx4_zx_fushi','rx4_zx_danshi'] },
                { name:'组选', plays:['rx4_zx24','rx4_zx12','rx4_zx6','rx4_zx4'] }
            ]},

            { key:'suoha', name:'梭哈', groups:[{ name:'梭哈', plays:['suoha'] }]},
            { key:'zhajinhua', name:'炸金花', groups:[{ name:'炸金花', plays:['zhajinhua'] }]},
            { key:'niuniu', name:'牛牛', groups:[{ name:'牛牛', plays:['niuniu'] }]}
        ]
    };

    function getBzpNav() {
        // 排列三与福彩3D使用完全相同的标准盘导航
        return FC3D_BZP_NAV;
    }

    /* ---------------------------------------------------------------
       排列三 玩法配置
       号码范围: 0-9, 三位（但排列五有5位）
       开奖号码: 000 ~ 999, 每天20:30开奖
       ---------------------------------------------------------------
       官方玩法:
       - 直选: 按位精确匹配, 奖金1040元
       - 组选三: 两位相同一位不同, 不论顺序, 奖金346元
       - 组选六: 三位各不同, 不论顺序, 奖金173元
       - 和值: 三位数之和(0-27)
       - 双面盘(非官方): 大小单双, 龙虎, 前/中/后三形态
       --------------------------------------------------------------- */
    var PL3_PLAYS = FC3D_PLAYS;

    /* ---------------------------------------------------------------  赔率 --------------------------------------------------------------- */
    var ODDS = {
        da: 1.97, xiao: 1.97, dan: 1.97, shuang: 1.97,
        number: 9.85,           // 单号定位
        yizi_zuhe: 3.634,        // 一字组合
        erzi_zuhe: 18.24, erzi_zuhe_dui: 35.178,  // 二字组合(非对子/对子)
        sanzi_zuhe: 164.166, sanzi_zuhe_dui: 328.33, sanzi_zuhe_bao: 985, // 三字组合(组六/对子/豹子)
        sanzi_dingwei: 992.5,     // 三字定位
        erzi_dingwei: 98,       // 二字定位
        zusan: 328.333,          // 组三
        zuliu: 164.166,         // 组六
        zhixuan: 520,           // 直选 (1040/2)
        long: 2.188, hu: 2.188, longhu_he: 9.85,
        baozi: 98.5, shunzi: 16.416, duizi: 3.648, banshun: 2.736, zaliu: 3.283
    };

    // 赔率格式化：整数不带小数点，小数最多3位且去掉末尾0
    function fmtOdds(v) {
        var n = parseFloat(v);
        if (isNaN(n)) return '0';
        if (n === Math.floor(n)) return '' + n;
        var s = n.toFixed(3);
        return s.replace(/0+$/, '').replace(/\.$/, '');
    }

    /* 逐项赔率表: ODDS_MAP[play_type][bet_key] = odds */
    var ODDS_MAP = {};

    function getItemOdds(playType, betKey, fallback) {
        // 1. 优先从本身对应的 playType 中读取配置
        if (ODDS_MAP[playType]) {
            if (Array.isArray(betKey)) {
                for (var i = 0; i < betKey.length; i++) {
                    if (ODDS_MAP[playType][betKey[i]] !== undefined) {
                        return ODDS_MAP[playType][betKey[i]];
                    }
                }
            } else {
                if (ODDS_MAP[playType][betKey] !== undefined) {
                    return ODDS_MAP[playType][betKey];
                }
            }
        }

        // 2. 特殊拦截：百位、十位、个位的定位胆玩法映射到 yizi_dingwei 的数据库配置中 (作为降级)
        if (playType === 'baiwei' || playType === 'shiwei' || playType === 'gewei') {
            var dbKey = 'yz_dw_' + playType + '_';
            var bk = Array.isArray(betKey) ? betKey[0] : betKey;
            if (bk.indexOf('num_') === 0) dbKey += bk.substring(4);
            else dbKey += bk;

            if (ODDS_MAP['yizi_dingwei'] && ODDS_MAP['yizi_dingwei'][dbKey] !== undefined) {
                return ODDS_MAP['yizi_dingwei'][dbKey];
            }
        }

        // 3. 特殊拦截：三字和数 (hezhi) 范围选项动态等比缩放
        if (playType === 'hezhi') {
            var bk = Array.isArray(betKey) ? betKey[0] : betKey;
            if (bk === 'hz_0-6' || bk === 'hz_21-27' || bk === '0-6' || bk === '21-27') {
                var hz10 = ODDS_MAP['hezhi'] && ODDS_MAP['hezhi']['hz_10'] !== undefined ? parseFloat(ODDS_MAP['hezhi']['hz_10']) : 9.85;
                var ratio = hz10 / 9.85;
                return parseFloat((parseFloat(fallback) * ratio).toFixed(3));
            }
        }

        // 4. 特殊拦截：二字和数 (erzi_heshu) 与二字和数尾数 (erzi_heshu_ws)
        if (playType && playType.indexOf('erzi_heshu_') === 0) {
            var bk = Array.isArray(betKey) ? betKey[0] : betKey;
            var isWs = playType.indexOf('_ws') !== -1;
            
            // 提取位置键名（如 baishi、baige、shige）
            var parts = playType.split('_');
            var posKey = parts[2];
            
            if (isWs) {
                // 将 ehzws_3 或 3 映射为 ehsw_{pos}_3
                var digit = bk;
                if (digit.indexOf('ehzws_') === 0) digit = digit.substring(6);
                var dbKey = 'ehsw_' + posKey + '_' + digit;
                if (ODDS_MAP[playType] && ODDS_MAP[playType][dbKey] !== undefined) {
                    return ODDS_MAP[playType][dbKey];
                }
            } else {
                // 将 ehz_0-4 映射为 ehs_{pos}_0，ehz_14-18 映射为 ehs_{pos}_10，其余映射为 ehs_{pos}_{num-4}
                var val = bk;
                if (val.indexOf('ehz_') === 0) val = val.substring(4);
                
                var idx = -1;
                if (val === '0-4') idx = 0;
                else if (val === '14-18') idx = 10;
                else {
                    var num = parseInt(val);
                    if (!isNaN(num) && num >= 5 && num <= 13) {
                        idx = num - 4;
                    }
                }
                
                if (idx !== -1) {
                    var dbKey = 'ehs_' + posKey + '_' + idx;
                    if (ODDS_MAP[playType] && ODDS_MAP[playType][dbKey] !== undefined) {
                        return ODDS_MAP[playType][dbKey];
                    }
                }
            }
        }

        return fallback;
    }

    /* --------------------------------------------------------------- API数据 --------------------------------------------------------------- */
    var apiData = {
        latest: null,     // 最新一期已开奖
        next: null,       // 下一期(待开奖)
        config: null,     // 彩种配置
        countdown: 0,     // 服务端计算的倒计时
        historyData: []   // 开奖历史列表
    };

    /* --------------------------------------------------------------- 状态 --------------------------------------------------------------- */
    var state = {
        lotteryType: 'fc3d', panelType: 'shuangmian', playType: '', subTab: '',
        betAmount: 1, selectedBets: [], countdownSeconds: 0, countdownTimer: null,
        bzpMode: 'remen', bzpCat: 'sanxing', bzpMultiple: 1, bzpUnit: 1,
        userBalance: 0,          // 用户余额（CNY）
        coldHotMode: null,       // null=关闭 'hot'=冷热 'missing'=遗漏
        coldHotPeriods: 100,     // 期数
        coldHotData: {}          // { 位置: { 0:count,...9:count } }
    };

    /* --------------------------------------------------------------- 玩法说明 --------------------------------------------------------------- */
    var PLAY_RULES = {
        // ===== 双面盘 =====
        shuangmian: '选择一个号码位置，并投注一种属性，投注的属性与该位置的开奖号码属性相同时，即中奖。\n大小：开奖号码≥5为"大"，≤4为"小"。\n单双：开奖号码1、3、5、7、9为"单"，0、2、4、6、8为"双"。\n质合：开奖号码1、2、3、5、7为"质"，0、4、6、8、9为"合"。\n\n百十/百个/十个：\n和数单双：对应2个位置号码之和的个位数为1、3、5、7、9为"和单"，0、2、4、6、8为"和双"。\n和数尾数大小：对应2个位置号码之和的个位数≥5为"和尾大"，≤4为"和尾小"。\n和数尾数质合：对应2个位置号码之和的个位数是1、2、3、5、7为"和尾质"，0、4、6、8、9为"和尾合"。\n\n百十个总和：\n和数大小：开奖3个号码之和≥14为"和大"，≤13为"和小"。\n和数单双：开奖3个号码之和的个位数为1、3、5、7、9为"和单"，0、2、4、6、8为"和双"。',
        yizi_zuhe: '选择1个号码为1注。开奖号码包含所选号码（顺序不限），即中奖。\n举例：开奖号码1,2,3，投注「1」，即中奖。',
        erzi_zuhe: '选择1组号码为1注。开奖号码包含所选的1组（顺序不限），即中奖。\n举例：开奖号码2,1,1，投注「12」，即中奖。',
        sanzi_zuhe: '选择1组号码为1注。开奖号码与所选号码相同（顺序不限），即中奖。\n举例：开奖号码3,2,1，投注「123」，即中奖。',
        yizi_dingwei: '从百位、十位、个位任意位置选择1个号码组成1注，所选号码与该位置上的开奖号码一致，即中奖。\n举例：开奖号码1,2,3，投注「百位1」，即中奖。',
        erzi_dingwei: '从百位、十位、个位中任意选择2个位置，在这2个位置上各选择1个号码组成一注。开奖号码与所选2个号码相同，且顺序一致，即中奖。\n举例：开奖号码1,2,3，投注「百位1，十位2」，即中奖。',
        sanzi_dingwei: '从百位、十位、个位中各选择1个号码组成1注，所选号码与开奖号码相同，且顺序一致，即为中奖。\n举例：开奖号码1,2,3，投注「百位1，十位2，个位3」，即中奖。',
        erzi_heshu: '和数：选择的数值与开奖号码中对应位置的2个号码之和相等，即中奖。\n举例：开奖号码2,3,1，投注「十个4」，即中奖。\n\n和数尾数：选择1个数值，与开奖号码中对应位置的2个号码之和的个位数相同，即中奖。\n举例：开奖号码1,2,3，投注「百十尾3」，即中奖。',
        hezhi: '百十个和数：选择的数值与开奖号码之和相等，即中奖。\n举例：开奖号码2,3,1，投注「0-6」，即中奖。\n\n百十个和数尾数：选择1个数值，与开奖号码之和的个位数相同，即中奖。\n举例：开奖号码9,9,6，投注「4」，即中奖。',
        zusan: '选择1个重号与1个不重号组成1注，重号在开奖号码中出现2次，不重号出现1次（顺序不限），即中奖。\n举例：开奖号码2,1,1，投注「重号1，不重号2」，即中奖。',
        zuliu: '选择3个号码组成1注。所选号码与开奖号码相同（顺序不限），即中奖。\n举例：开奖号码3,2,1，投注「1,2,3」，即中奖。',
        kuadu: '选择1个数值，与开奖号码中最大号码与最小号码相减的差值相同，即中奖。\n举例：开奖号码1,2,3，投注「2」，即中奖。',
        kuaijie: '在第一球~第五球(或百位/十位/个位)中选择大小单双质合或号码0-9投注，该位属性或号码一致即中奖。',
        zhenghe: '总和大小：开奖号码之和≥23为"大"，≤22为"小"。\\n总和单双：开奖号码之和的个位数为奇数为"单"，偶数为"双"。\\n\\n龙虎和：第一球>第五球为龙，第一球<第五球为虎，相同为和。\\n\\n前三/中三/后三特殊：\\n豹子：3个相同号码。顺子：3个相连号码(0-9循环)，顺序不限。\\n对子：2个相同+1个不同。半顺：仅2个相连。杂六：互不相同且互不相连。',
        longhu: '龙虎：百位>个位为龙，百位<个位为虎。',
        // ===== 标准盘 =====
        dingweidan: '定位胆：从百位、十位、个位任意位置选择号码，所选号码与该位置上的开奖号码一致即中奖。\n每注奖金9.85元。可同时在多个位置选择号码，各位独立计算。',
        longhuhe: '龙虎和：百位>个位为"龙"，百位<个位为"虎"，百位=个位为"和"。',
        sx_zx_fushi: '三星直选复式：在百位、十位、个位各选1个或多个号码，所选号码与开奖号码相同且顺序一致即中奖。\n注数 = 百位选号数 × 十位选号数 × 个位选号数。',
        sx_zx_danshi: '三星直选单式：手动输入3位数号码，所选号码与开奖号码完全一致即中奖。\n多注用逗号或换行分隔。',
        sx_zx_hezhi: '三星直选和值：选择3位数之和（0-27），开奖号码三位之和等于所选值即中奖。',
        sx_zx_kuadu: '三星直选跨度：选择开奖号码最大值与最小值之差（0-9），差值与所选一致即中奖。',
        sx_zx3_fushi: '三星组选三复式：选择2个号码组成2注，任意1个号码在开奖号码中出现2次，另外1个号码出现1次（顺序不限），即中奖。\n中奖范例：投注方案：1,2；开奖号码：1,2,2，即中奖。\n注数 = C(n,2)。',
        sx_zx3_danshi: '三星组选三单式：手动输入组三号码，如 112,334。',
        sx_zx6_fushi: '三星组选六复式：选3个或以上号码，开奖号码三位各不相同，且与所选号码一致（不论顺序）即中奖。\n注数 = C(n,3)。',
        sx_hunhe: '三星混合组选：手动输入号码，系统自动判断是组三还是组六。',
        sx_zx_hezhi2: '三星组选和值：选择3位数之和（0-27），开奖号码三位之和等于所选值即中奖（不论顺序）。',
        sx_zx_baodan: '三星组选包胆：选择1个号码作为胆码，开奖号码包含该号码（不论顺序）即中奖。',
        sx_tx_fushi: '三星通选复式：在百位、十位、个位各选号码，同时覆盖直选和组选两种中奖方式。',
        sx_tx_danshi: '三星通选单式：手动输入3位数号码进行通选投注。',
        sx_hzweishu: '三星和值尾数：选择开奖号码三位之和的个位数（0-9），一致即中奖。',
        sx_yimabuding: '三星一码不定胆：选择1个号码组成1注。开奖号码包含所选号码，即中奖。若开奖结果出现重复数字，则只会派奖一次不累加。\n中奖范例：投注方案：3；开奖号码：1,3,2，即中奖。',
        sx_ermabuding: '三星二码不定胆：选择2个号码组成1注。开奖号码包含所选2个号码，即中奖。\n中奖范例：投注方案：1,3；开奖号码：1,3,2，即中奖。',
        dingweidan: '定位胆：从百位、十位、个位任意位置选择1个号码组成1注，所选号码与相同位置上的开奖号码一致，即中奖。\n中奖范例：投注方案：3,-,-；开奖号码：3,*,*，即中奖。\n每注奖金9元。可同时在多个位置选择号码，各位独立计算。',
        dxds: '大小单双：对百位、十位、个位的大小单双属性进行投注。',
        qe_zx_fushi: '前二直选复式：在百位、十位各选号码，两位与开奖号码前两位相同且顺序一致即中奖。\n注数 = 百位选号数 × 十位选号数。',
        qe_zx_danshi: '前二直选单式：手动输入2位数号码，与开奖号码前两位完全一致即中奖。\n多注用逗号或换行分隔。',
        qe_zx_hezhi: '前二直选和值：选择前两位号码之和（0-18），开奖号码前两位之和等于所选值即中奖。',
        qe_zx_kuadu: '前二直选跨度：选择前两位号码最大值与最小值之差（0-9），差值与所选一致即中奖。',
        qe_zuxuan_fushi: '前二组选复式：选择2个或以上号码，开奖前两位与所选一致（不论顺序）即中奖。\n注数 = C(n,2)。',
        qe_zuxuan_danshi: '前二组选单式：录入1个2位数的号码组成1注（不可输入对子号）。录入号码与开奖号码百位、十位相同（顺序不限），即中奖。\n中奖范例：投注方案：12；开奖号码：2,1,*，即中奖。\n多注用逗号或换行分隔。',
        qe_zuxuan_hezhi: '前二组选和值：选择一个数值，所选数值等于开奖号码的百位、十位2个号码之和（不含对子号），即中奖。',
        qe_zuxuan_baodan: '前二组选包胆：选择一个号码，开奖号码的百位、十位中任意一位和所选号码相同（不含对子号），即中奖。\n注数 = 9注。',
        he_zx_fushi: '后二直选复式：在十位、个位各选号码，两位与开奖号码后两位相同且顺序一致即中奖。\n注数 = 十位选号数 × 个位选号数。',
        he_zx_danshi: '后二直选单式：手动输入2位数号码，与开奖号码后两位完全一致即中奖。\n多注用逗号或换行分隔。',
        he_zx_hezhi: '后二直选和值：选择后两位号码之和（0-18），开奖号码后两位之和等于所选值即中奖。',
        he_zx_kuadu: '后二直选跨度：选择后两位号码最大值与最小值之差（0-9），差值与所选一致即中奖。',
        he_zuxuan_fushi: '后二组选复式：选择2个或以上号码，开奖后两位与所选一致（不论顺序）即中奖。\n注数 = C(n,2)。',
        he_zuxuan_danshi: '后二组选单式：录入1个2位数的号码组成1注（不可输入对子号）。录入号码与开奖号码十位、个位相同（顺序不限），即中奖。\n中奖范例：投注方案：12；开奖号码：*,2,1，即中奖。\n多注用逗号或换行分隔。',
        he_zuxuan_hezhi: '后二组选和值：选择一个数值，所选数值等于开奖号码的十位、个位2个号码之和（不含对子号），即中奖。',
        he_zuxuan_baodan: '后二组选包胆：选择一个号码，开奖号码的十位、个位中任意一位和所选号码相同（不含对子号），即中奖。\n注数 = 9注。',
        // PL3/5 新增玩法
        wx_zx_fushi: '五星直选复式：从万位、千位、百位、十位、个位各选1个或多个号码，所选号码与开奖号码相同且顺序一致即中奖。',
        wx_zx_danshi: '五星直选单式：手动输入5位数号码，与开奖号码完全一致即中奖。',
        wx_zx_zuhe: '五星直选组合：从万位、千位、百位、十位、个位各选号码，以组合方式计算中奖。',
        wx_zx120: '五星组选120：选择5个不同号码，开奖号码包含这5个号码（不论顺序）即中奖。',
        wx_zx60: '五星组选60：手动输入号码，1组对子+3个不同号码，开奖包含即中奖（不论顺序）。',
        wx_zx30: '五星组选30：手动输入号码，2组对子+1个不同号码，开奖包含即中奖（不论顺序）。',
        wx_zx20: '五星组选20：手动输入号码，1组三同+2个不同号码，开奖包含即中奖（不论顺序）。',
        wx_zx10: '五星组选10：手动输入号码，1组三同+1组对子，开奖包含即中奖（不论顺序）。',
        wx_zx5: '五星组选5：手动输入号码，1组四同+1个不同号码，开奖包含即中奖（不论顺序）。',
        sx4_zx_fushi: '四星直选复式：从千位、百位、十位、个位各选号码，4位全对即中奖。',
        sx4_zx_danshi: '四星直选单式：手动输入4位数号码。',
        sx4_zx24: '四星组选24：选4个不同号码，不论顺序即中奖。',
        sx4_zx12: '四星组选12：1组对子+2个不同号码，不论顺序即中奖。',
        sx4_zx6: '四星组选6：2组对子，不论顺序即中奖。',
        sx4_zx4: '四星组选4：1组三同+1个不同号码，不论顺序即中奖。',
        q3_zx_fushi: '前三直选复式：从万位、千位、百位各选号码，3位全对且顺序一致即中奖。',
        q3_zx_danshi: '前三直选单式：手动输入3位数号码。',
        q3_zx_hezhi: '前三直选和值：选择前三位之和（0-27），一致即中奖。',
        q3_zx_kuadu: '前三直选跨度：选择前三位最大值与最小值之差（0-9）。',
        q3_zx3_fushi: '前三组选三复式：选2个或以上号码，前三位有且仅有两位相同即中奖。',
        q3_zx3_danshi: '前三组选三单式：手动输入组三号码。',
        q3_zx6_fushi: '前三组选六复式：选3个或以上号码，前三位各不同且与所选一致即中奖。',
        q3_hunhe: '前三混合组选：手动输入号码，系统自动判断组三或组六。',
        q3_zx_hezhi2: '前三组选和值：选择前三位之和，不论顺序即中奖。',
        q3_zx_baodan: '前三组选包胆：选1个胆码，前三位包含该号即中奖。',
        q3_tx_fushi: '前三通选复式：同时覆盖直选和组选两种中奖方式。',
        q3_tx_danshi: '前三通选单式：手动输入号码进行通选。',
        q3_hzweishu: '前三和值尾数：选择前三位之和的个位数（0-9），一致即中奖。',
        q3_yimabuding: '前三一码不定胆：选1个号码，前三位任意一位包含该号即中奖。',
        q3_ermabuding: '前三二码不定胆：选2个号码，前三位包含这2个号码即中奖。',
        z3_zx_fushi: '中三直选复式：从千位、百位、十位各选号码，3位全对且顺序一致即中奖。',
        z3_zx3_fushi: '中三组选三复式：选2个或以上号码，中间三位有且仅有两位相同即中奖。',
        z3_zx6_fushi: '中三组选六复式：选3个或以上号码，中间三位各不同且与所选一致即中奖。',
        h3_zx_fushi: '后三直选复式：从百位、十位、个位各选号码，3位全对且顺序一致即中奖。',
        h3_zx3_fushi: '后三组选三复式：选2个或以上号码，后三位有且仅有两位相同即中奖。',
        h3_zx6_fushi: '后三组选六复式：选3个或以上号码，后三位各不同且与所选一致即中奖。',
        yffs: '一帆风顺：选1个号码，开奖号码5位中任意一位包含该号即中奖。',
        hscs: '好事成双：选1个号码，开奖号码5位中至少有相邻两位为该号即中奖。',
        sxbx: '三星报喜：选1个号码，开奖号码5位中至少有3位为该号即中奖。',
        sjfc: '四季发财：选1个号码，开奖号码5位中至少有4位为该号即中奖。',
        rx2_zx_fushi: '任二直选复式：选择任意2个位置，各位选号码，2位全对即中奖。',
        rx3_zx_fushi: '任三直选复式：选择任意3个位置，各位选号码，3位全对即中奖。',
        rx4_zx_fushi: '任四直选复式：选择任意4个位置，各位选号码，4位全对即中奖。',
        suoha: '梭哈：根据开奖号码组成的牌型大小进行比较，支持同花顺、四条、葫芦等牌型投注。',
        zhajinhua: '炸金花：取开奖号码的3位组成牌型，支持豹子、同花顺、同花、顺子等牌型投注。',
        niuniu: '牛牛：取开奖号码组成牛牛牌型，支持牛牛、牛九、牛八等投注。'
    };

    function showPlayRule() {
        var pt = state.playType || '';
        var ruleText = PLAY_RULES[pt] || '暂无该玩法的说明。';
        // 查找玩法名
        var playName = pt;
        var allPlays = (state.lotteryType === 'fc3d' ? FC3D_PLAYS : PL3_PLAYS);
        Object.keys(allPlays).forEach(function(panel) {
            allPlays[panel].forEach(function(p) {
                if (p.key === pt) playName = p.name;
            });
        });
        // 弹窗
        var overlay = document.createElement('div');
        overlay.id = 'playRuleOverlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;';
        overlay.innerHTML = '<div style="background:#fff;border-radius:12px;max-width:520px;width:90%;max-height:80vh;overflow-y:auto;padding:24px;box-shadow:0 8px 32px rgba(0,0,0,0.2);">' +
            '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">' +
            '<h3 style="margin:0;font-size:18px;color:#333;">📖 ' + playName + ' - 玩法说明</h3>' +
            '<span id="closePlayRule" style="cursor:pointer;font-size:24px;color:#999;line-height:1;">&times;</span>' +
            '</div>' +
            '<div style="font-size:14px;line-height:2;color:#555;white-space:pre-line;">' + ruleText + '</div>' +
            '</div>';
        document.body.appendChild(overlay);
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay || e.target.id === 'closePlayRule') {
                overlay.remove();
            }
        });
    }

    /* --------------------------------------------------------------- INIT --------------------------------------------------------------- */
    function init(type) {
        state.lotteryType = type || 'fc3d';
        state.panelType = 'shuangmian';

        // 初始化用户余额
        (function() {
            var u = typeof LotteryAuth !== 'undefined' ? LotteryAuth.getCachedUser() : null;
            if (u) state.userBalance = parseFloat(u.money) || 0;
            // 异步刷新余额
            if (typeof LotteryAuth !== 'undefined') {
                LotteryAuth.fetchUserInfo(function(info) {
                    if (info) { state.userBalance = parseFloat(info.money) || 0; updateBetCount(); }
                });
            }
        })();

        // 先从API获取数据，再渲染UI
        fetchApiData(function() {
            updateLotteryInfo();
            initPanelToggle();
            initBzpModeToggle();
            initAmountChips();
            initBottomActions();
            startBetCountdown();
            renderHistory();
            renderTrendChart();
            initColdHotToolbar();
            // 根据当前面板类型初始化
            renderMode();
        });
    }

    /* --------------------------------------------------------------- API请求 --------------------------------------------------------------- */
    function fetchApiData(callback) {
        var type = state.lotteryType;
        var done = 0;
        var total = 3; // 3个请求: getLatestDraw + getDraws + getOdds

        function check() { done++; if (done >= total && callback) callback(); }

        // 1. 获取最新开奖 + 下期 + 倒计时
        fetch('/index.php/api/lottery/getLatestDraw?type=' + type)
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.code === 1 && res.data) {
                    apiData.latest = res.data.latest;
                    apiData.next = res.data.next;
                    apiData.config = res.data.config;
                    apiData.countdown = res.data.countdown || 0;
                    state.countdownSeconds = apiData.countdown;
                }
                check();
            })
            .catch(function() { check(); });

        // 3. 从数据库加载赔率覆盖hardcoded值
        fetch('/index.php/api/lottery/getOdds?type=' + type)
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.code === 1 && res.data) {
                    var d = res.data;
                    // 构建 ODDS_MAP: 按play_type和bet_key存储每一项的赔率
                    for (var pt in d) {
                        if (!d.hasOwnProperty(pt)) continue;
                        ODDS_MAP[pt] = {};
                        d[pt].forEach(function(item) {
                            ODDS_MAP[pt][item.key] = item.odds;
                        });
                    }
                    // 同时更新全局ODDS兜底值
                    var keyMap = {
                        'da': 'da', 'xiao': 'xiao', 'dan': 'dan', 'shuang': 'shuang',
                        'zhi': 'zhi', 'he': 'he', 'long': 'long', 'hu': 'hu',
                        'longhu_he': 'longhu_he', 'baozi': 'baozi', 'shunzi': 'shunzi',
                        'duizi': 'duizi', 'banshun': 'banshun', 'zaliu': 'zaliu'
                    };
                    ['shuangmian','zhenghe','longhu','xingtai'].forEach(function(pt) {
                        if (d[pt]) d[pt].forEach(function(item) {
                            if (keyMap[item.key]) ODDS[keyMap[item.key]] = item.odds;
                        });
                    });
                    // 从百位提取号码赔率兜底(取第一个数字的)
                    // 数据库key可能是纯数字(0-9)或 num_0~num_9 格式
                    // 属性key可能是 da/xiao 或 baiwei_da/baiwei_xiao 格式
                    var attrKeyMap = {
                        'da':'da','xiao':'xiao','dan':'dan','shuang':'shuang','zhi':'zhi','he':'he',
                        'baiwei_da':'da','baiwei_xiao':'xiao','baiwei_dan':'dan','baiwei_shuang':'shuang','baiwei_zhi':'zhi','baiwei_he':'he',
                        'shiwei_da':'da','shiwei_xiao':'xiao','shiwei_dan':'dan','shiwei_shuang':'shuang','shiwei_zhi':'zhi','shiwei_he':'he',
                        'gewei_da':'da','gewei_xiao':'xiao','gewei_dan':'dan','gewei_shuang':'shuang','gewei_zhi':'zhi','gewei_he':'he'
                    };
                    ['baiwei','shiwei','gewei','ball1','ball2','ball3','ball4','ball5'].forEach(function(pt) {
                        if (d[pt]) d[pt].forEach(function(item) {
                            if (/^\d+$/.test(item.key) || /^num_\d+$/.test(item.key)) { ODDS.number = item.odds; }
                            else if (attrKeyMap[item.key]) ODDS[attrKeyMap[item.key]] = item.odds;
                            else if (keyMap[item.key]) ODDS[keyMap[item.key]] = item.odds;
                        });
                    });
                    if (d['yizi_zuhe'] && d['yizi_zuhe'][0]) ODDS.yizi_zuhe = d['yizi_zuhe'][0].odds;
                    
                    // erzi_dingwei: 整合 baishi / baige / shige 的第一个元素作为全局赔率
                    var ez_dw = d['erzi_dingwei'] || d['erzi_dingwei_baishi'] || d['erzi_dingwei_baige'] || d['erzi_dingwei_shige'];
                    if (ez_dw && ez_dw[0]) ODDS.erzi_dingwei = ez_dw[0].odds;
                    
                    if (d['sanzi_dingwei'] && d['sanzi_dingwei'][0]) ODDS.sanzi_dingwei = d['sanzi_dingwei'][0].odds;
                    if (d['zusan'] && d['zusan'][0]) ODDS.zusan = d['zusan'][0].odds;
                    if (d['zuliu'] && d['zuliu'][0]) ODDS.zuliu = d['zuliu'][0].odds;
                    if (d['kuadu'] && d['kuadu'][0]) ODDS.kuadu = d['kuadu'][0].odds;
                    
                    // sanzi_zuhe: 映射数据库赔率到前端不同的三字组合选项中
                    if (d['sanzi_zuhe']) {
                        d['sanzi_zuhe'].forEach(function(item) {
                            if (item.key === 'sanzi_zuliu') ODDS.sanzi_zuhe = item.odds;
                            if (item.key === 'sanzi_zusan') ODDS.sanzi_zuhe_dui = item.odds;
                            if (item.key === 'sanzi_baozi' || item.key === 'sanzi_zhixuan') ODDS.sanzi_zuhe_bao = item.odds;
                        });
                    }

                    // 标准盘赔率动态覆盖: 从数据库 bzp_* 分类读取并覆盖 FC3D_PLAYS.biaozhun 的 prize
                    var bzpCategories = ['bzp_sanxing','bzp_qianer','bzp_houer','bzp_dingweidan','bzp_budindan','bzp_dxds'];
                    var bzpOddsMap = {};
                    var bzpMaxOddsMap = {};
                    bzpCategories.forEach(function(cat) {
                        if (d[cat]) {
                            d[cat].forEach(function(item) {
                                bzpOddsMap[item.key] = item.odds;
                                if (item.max_odds && item.max_odds > 0) {
                                    bzpMaxOddsMap[item.key] = item.max_odds;
                                }
                            });
                        }
                    });
                    // 覆盖 biaozhun 数组中的 prize
                    if (Object.keys(bzpOddsMap).length > 0 && FC3D_PLAYS.biaozhun) {
                        FC3D_PLAYS.biaozhun.forEach(function(play) {
                            if (bzpOddsMap[play.key] !== undefined) {
                                if (bzpMaxOddsMap[play.key] && bzpMaxOddsMap[play.key] !== bzpOddsMap[play.key]) {
                                    // 范围奖金：存为 "min~max" 字符串
                                    play.prize = bzpOddsMap[play.key] + '~' + bzpMaxOddsMap[play.key];
                                } else {
                                    play.prize = bzpOddsMap[play.key];
                                }
                            }
                        });
                    }
                }
                check();
            })
            .catch(function() { check(); });

        // 2. 获取开奖历史
        fetch('/index.php/api/lottery/getDraws?type=' + type + '&limit=10')
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.code === 1 && res.data) {
                    apiData.historyData = res.data.map(function(d) {
                        return {
                            period: d.period,
                            numbers: d.numbers_arr.map(Number),
                            sum: d.sum_value
                        };
                    });
                }
                check();
            })
            .catch(function() { check(); });
    }

    /* --------------------------------------------------------------- 彩种信息(从API数据填充) --------------------------------------------------------------- */
    var isMobile = document.body.classList.contains('m-bet-page');

    function updateLotteryInfo() {
        var el = {
            name: document.getElementById('betLotteryName'),
            icon: document.getElementById('betLotteryIcon'),
            title: document.getElementById('pageTitle'),
            nums: document.getElementById('betDrawNumbers'),
            meta: document.getElementById('betLotteryMeta'),
            period: document.getElementById('betTimerPeriod')
        };

        // 号码球颜色
        var colors = state.lotteryType === 'fc3d'
            ? ['purple','cyan','pink'] : ['blue','green','orange'];

        var logoImg = '<img src="/c788ffd04d7bbafa657bd2dcf4c6ecc4.png" style="width:100%;height:100%;object-fit:contain;border-radius:6px;">';
        if (state.lotteryType === 'fc3d') {
            if (el.name) el.name.textContent = '福彩3D';
            if (el.icon && !isMobile) { el.icon.className = 'bet-lottery-icon fc3d'; el.icon.innerHTML = logoImg; }
            else if (el.icon && isMobile) { el.icon.className = 'm-card-icon fc3d'; el.icon.style.overflow = 'hidden'; el.icon.innerHTML = logoImg; }
            if (el.title) el.title.textContent = '福彩3D 投注 - DB彩票';
            if (el.meta) el.meta.innerHTML = '<span class="meta-tag official">官方彩</span><span class="meta-tag freq">每天一期 21:15开奖</span>';
        } else {
            if (el.name) el.name.textContent = '排列三';
            if (el.icon && !isMobile) { el.icon.className = 'bet-lottery-icon pl3'; el.icon.innerHTML = logoImg; }
            else if (el.icon && isMobile) { el.icon.className = 'm-card-icon pl3'; el.icon.style.overflow = 'hidden'; el.icon.innerHTML = logoImg; }
            if (el.title) el.title.textContent = '排列三 投注 - DB彩票';
            if (el.meta) el.meta.innerHTML = '<span class="meta-tag official">官方彩</span><span class="meta-tag freq">每天一期 20:30开奖</span>';
        }

        // 最新开奖号码(从API)
        if (apiData.latest && apiData.latest.numbers_arr && el.nums) {
            var numsHtml = '';
            var ballClass = isMobile ? 'm-num' : 'number-ball';
            apiData.latest.numbers_arr.forEach(function(n, i) {
                numsHtml += '<span class="' + ballClass + ' ' + colors[i % colors.length] + '">' + n + '</span>';
            });
            el.nums.innerHTML = numsHtml;
        }

        // 当前期号
        if (apiData.next && el.period) {
            el.period.textContent = apiData.next.period + '期';
        } else if (apiData.latest && el.period) {
            el.period.textContent = apiData.latest.period + '期(已开奖)';
        }
    }

    /* --------------------------------------------------------------- 倒计时 --------------------------------------------------------------- */
    function startBetCountdown() {
        if (state.countdownTimer) clearInterval(state.countdownTimer);
        function tick() {
            if (state.countdownSeconds <= 0) {
                updateTimerDisplay(0,0,0);
                var s = document.getElementById('betTimerStatus');
                if (s) { s.textContent = '开奖中...'; s.style.background = '#fff7e6'; s.style.color = '#fa8c16'; }
                clearInterval(state.countdownTimer);
                // 触发自动开奖 + 轮询刷新
                triggerDrawAndRefresh();
                return;
            }
            var h = Math.floor(state.countdownSeconds / 3600);
            var m = Math.floor((state.countdownSeconds % 3600) / 60);
            var s = state.countdownSeconds % 60;
            updateTimerDisplay(h, m, s);
            state.countdownSeconds--;
        }
        tick();
        state.countdownTimer = setInterval(tick, 1000);
    }

    // 倒计时归零后：触发开奖 + 轮询等待下一期
    function triggerDrawAndRefresh() {
        // 1. 触发自动开奖
        fetch('/index.php/api/lottery/triggerAutoDraw?key=db_lottery_auto_draw_2026')
            .then(function(r) { return r.json(); })
            .catch(function() { return {}; })
            .then(function() {
                // 2. 无论成功失败，开始轮询等待新一期出现
                startRefreshPolling();
            });
    }

    function startRefreshPolling() {
        var attempts = 0;
        var maxAttempts = 40; // 最多轮询20分钟 (40 × 30秒)
        var pollTimer = setInterval(function() {
            attempts++;
            fetch('/index.php/api/lottery/getLatestDraw?type=' + state.lotteryType)
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.code === 1 && res.data) {
                        apiData.latest = res.data.latest;
                        apiData.next = res.data.next;
                        apiData.config = res.data.config;
                        apiData.countdown = res.data.countdown || 0;

                        // 有新一期且倒计时 > 0，说明开奖完成
                        if (res.data.next && res.data.countdown > 0) {
                            clearInterval(pollTimer);
                            state.countdownSeconds = res.data.countdown;
                            updateLotteryInfo();
                            startBetCountdown();
                            var s = document.getElementById('betTimerStatus');
                            if (s) { s.textContent = '投注中'; s.style.background = '#f6ffed'; s.style.color = '#52c41a'; }
                        } else {
                            // 还没开奖完成，再次触发
                            if (attempts % 4 === 0) {
                                fetch('/index.php/api/lottery/triggerAutoDraw?key=db_lottery_auto_draw_2026').catch(function(){});
                            }
                        }
                    }
                })
                .catch(function() {});
            if (attempts >= maxAttempts) {
                clearInterval(pollTimer);
                var s = document.getElementById('betTimerStatus');
                if (s) { s.textContent = '已封盘'; s.style.background = '#fff1f0'; s.style.color = '#ff4d4f'; }
            }
        }, 30000); // 每30秒轮询一次
    }

    function updateTimerDisplay(h,m,s) {
        setD('timerH1',pad(h)[0]); setD('timerH2',pad(h)[1]);
        setD('timerM1',pad(m)[0]); setD('timerM2',pad(m)[1]);
        setD('timerS1',pad(s)[0]); setD('timerS2',pad(s)[1]);
    }
    function setD(id,v) { var e=document.getElementById(id); if(e) e.textContent=v; }
    function pad(n) { return n < 10 ? '0'+n : ''+n; }

    /* --------------------------------------------------------------- 配置 --------------------------------------------------------------- */
    function getCurrentPlays() {
        // 排列三与福彩3D使用完全相同的玩法配置
        return FC3D_PLAYS[state.panelType] || [];
    }
    function findBzpPlay(key) {
        var plays = getCurrentPlays();
        return plays.find(function(p){ return p.key === key; });
    }

    /* --------------------------------------------------------------- 盘口切换 --------------------------------------------------------------- */
    function initPanelToggle() {
        document.querySelectorAll('.panel-toggle-btn, .m-panel-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.panel-toggle-btn, .m-panel-btn').forEach(function(b){b.classList.remove('active');});
                btn.classList.add('active');
                state.panelType = btn.dataset.panel;
                state.selectedBets = [];
                renderMode();
            });
        });
    }

    function renderMode() {
        var bzpModeWrap = document.getElementById('bzpModeWrap');
        var bzpCatWrap = document.getElementById('bzpCatNav');
        var chipGroup = document.getElementById('betAmountGroup');
        var unitBar = document.getElementById('bzpUnitBar');

        if (state.panelType === 'biaozhun') {
            if (!state.bzpMode) state.bzpMode = 'remen';
            if (bzpModeWrap) bzpModeWrap.style.display = 'flex';
            // 标准盘：隐藏筹码行，显示每注/倍数行
            if (chipGroup) chipGroup.style.display = 'none';
            if (unitBar) { unitBar.style.display = 'flex'; initBzpUnitBar(); }
            renderBzpTabs();
        } else {
            if (bzpModeWrap) bzpModeWrap.style.display = 'none';
            if (bzpCatWrap) bzpCatWrap.style.display = 'none';
            // 双面盘：显示筹码行，隐藏每注/倍数行
            if (chipGroup) chipGroup.style.display = '';
            if (unitBar) unitBar.style.display = 'none';
            renderPlayTypeTabs();
            var plays = getCurrentPlays();
            if (plays.length > 0) selectPlayType(plays[0].key);
        }
    }

    /* 标准盘 每注/倍数 事件绑定 */
    function initBzpUnitBar() {
        // 每注单价
        document.querySelectorAll('.bzp-unit-btn[data-unit]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.bzp-unit-btn[data-unit]').forEach(function(b) { b.classList.remove('active'); });
                btn.classList.add('active');
                state.bzpUnit = parseFloat(btn.dataset.unit);
                updateBetCount();
            });
        });
        // 倍数快捷
        document.querySelectorAll('.bzp-unit-btn[data-multi]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                state.bzpMultiple = parseInt(btn.dataset.multi);
                var inp = document.getElementById('bzpMultiInput');
                if (inp) inp.value = state.bzpMultiple;
                updateBetCount();
            });
        });
        // 倍数 +/-
        var multiInp = document.getElementById('bzpMultiInput');
        var minusBtn = document.getElementById('bzpMultiMinus');
        var plusBtn = document.getElementById('bzpMultiPlus');
        if (minusBtn) minusBtn.addEventListener('click', function() {
            state.bzpMultiple = Math.max(1, state.bzpMultiple - 1);
            if (multiInp) multiInp.value = state.bzpMultiple;
            updateBetCount();
        });
        if (plusBtn) plusBtn.addEventListener('click', function() {
            state.bzpMultiple++;
            if (multiInp) multiInp.value = state.bzpMultiple;
            updateBetCount();
        });
        if (multiInp) multiInp.addEventListener('change', function() {
            var v = parseInt(multiInp.value);
            if (v > 0) state.bzpMultiple = v; else { state.bzpMultiple = 1; multiInp.value = 1; }
            updateBetCount();
        });
    }

    /* --------------------------------------------------------------- 标准盘Tab渲染 --------------------------------------------------------------- */
    function renderBzpTabs() {
        var tabC = document.getElementById('playTypeTabs'); if (!tabC) return;
        var subC = document.getElementById('playSubTabs'); if (subC) { subC.innerHTML = ''; subC.style.display = 'none'; }
        var catNav = document.getElementById('bzpCatNav');

        if (state.bzpMode === 'remen') {
            // 热门玩法: 扁平tab列表
            if (catNav) catNav.style.display = 'none';
            var html = '';
            getBzpNav().remen.forEach(function(key, i) {
                var p = findBzpPlay(key);
                if (p) html += '<div class="play-type-tab' + (i === 0 ? ' active' : '') + '" data-play="' + key + '">' + p.name + '</div>';
            });
            tabC.innerHTML = html;
            tabC.querySelectorAll('.play-type-tab').forEach(function(tab) {
                tab.addEventListener('click', function() {
                    tabC.querySelectorAll('.play-type-tab').forEach(function(t){t.classList.remove('active');});
                    tab.classList.add('active');
                    selectBzpPlay(tab.dataset.play);
                });
            });
            // 默认选中第一个
            if (getBzpNav().remen.length > 0) selectBzpPlay(getBzpNav().remen[0]);
        } else {
            // 全部玩法: 一级分类Tab + 二级分组链接
            if (catNav) catNav.style.display = 'flex';
            var catHtml = '';
            getBzpNav().quanbu.forEach(function(cat, i) {
                catHtml += '<div class="play-type-tab' + (i === 0 ? ' active' : '') + '" data-cat="' + cat.key + '">' + cat.name + '</div>';
            });
            tabC.innerHTML = catHtml;
            tabC.querySelectorAll('.play-type-tab').forEach(function(tab) {
                tab.addEventListener('click', function() {
                    tabC.querySelectorAll('.play-type-tab').forEach(function(t){t.classList.remove('active');});
                    tab.classList.add('active');
                    state.bzpCat = tab.dataset.cat;
                    renderBzpGroupLinks();
                });
            });
            state.bzpCat = getBzpNav().quanbu[0].key;
            renderBzpGroupLinks();
        }
    }

    function renderBzpGroupLinks() {
        var catNav = document.getElementById('bzpCatNav');
        if (!catNav) {
            // 动态创建分组导航容器（在playSubTabs下方）
            var subC = document.getElementById('playSubTabs');
            catNav = document.createElement('div');
            catNav.id = 'bzpCatNav';
            catNav.className = 'bzp-group-nav';
            if (subC) subC.parentNode.insertBefore(catNav, subC.nextSibling);
        }
        var cat = getBzpNav().quanbu.find(function(c){ return c.key === state.bzpCat; });
        if (!cat) return;
        var html = '';
        var firstPlay = '';
        cat.groups.forEach(function(g) {
            html += '<div class="bzp-group"><span class="bzp-group-label">' + g.name + '</span>';
            g.plays.forEach(function(pkey, pi) {
                var p = findBzpPlay(pkey);
                if (!p) return;
                if (!firstPlay) firstPlay = pkey;
                html += '<a class="bzp-play-link' + (pi === 0 && !firstPlay ? '' : '') + '" data-play="' + pkey + '">' + p.name + '</a>';
            });
            html += '</div>';
        });
        catNav.innerHTML = html;
        catNav.style.display = 'flex';
        catNav.querySelectorAll('.bzp-play-link').forEach(function(link) {
            link.addEventListener('click', function() {
                catNav.querySelectorAll('.bzp-play-link').forEach(function(l){l.classList.remove('active');});
                link.classList.add('active');
                selectBzpPlay(link.dataset.play);
            });
        });
        // 默认选中第一个
        if (firstPlay) {
            var first = catNav.querySelector('.bzp-play-link');
            if (first) first.classList.add('active');
            selectBzpPlay(firstPlay);
        }
    }

    function selectBzpPlay(key) {
        state.playType = key;
        state.selectedBets = [];
        var play = findBzpPlay(key);
        if (!play) return;
        // 更新玩法说明
        var ruleEl = document.getElementById('playRuleDesc');
        if (ruleEl) {
            var bzpAmt = (state.bzpUnit || 1) * (state.bzpMultiple || 1);
            var prizeVal = '';
            if (play.prize) {
                if (typeof play.prize === 'number') {
                    prizeVal = fmtOdds(play.prize * bzpAmt);
                } else {
                    var pp = String(play.prize).split('~');
                    if (pp.length === 2) {
                        prizeVal = fmtOdds(parseFloat(pp[0]) * bzpAmt) + '~' + fmtOdds(parseFloat(pp[1]) * bzpAmt);
                    } else { prizeVal = play.prize; }
                }
            }
            var prizeText = prizeVal ? '奖金: <strong style=\"color:var(--danger);\"><span id=\"ruleOddsValue\">' + prizeVal + '</span></strong> 元' : '';
            ruleEl.innerHTML = '<span style="color:var(--primary);font-weight:600;">' + play.name + '</span>  ' + prizeText;
        }
        renderBetArea();
        updateBetCount();
    }

    /* --------------------------------------------------------------- 双面盘Tab渲染 --------------------------------------------------------------- */
    function renderPlayTypeTabs() {
        var c = document.getElementById('playTypeTabs'); if (!c) return;
        var plays = getCurrentPlays(), html = '';
        plays.forEach(function(p, i) {
            html += '<div class="play-type-tab' + (i === 0 ? ' active' : '') + '" data-play="' + p.key + '">' + p.name + '</div>';
        });
        c.innerHTML = html;
        c.querySelectorAll('.play-type-tab').forEach(function(tab) {
            tab.addEventListener('click', function() {
                c.querySelectorAll('.play-type-tab').forEach(function(t){t.classList.remove('active');});
                tab.classList.add('active');
                selectPlayType(tab.dataset.play);
            });
        });
    }

    function selectPlayType(key) {
        state.playType = key; state.selectedBets = [];
        var play = getCurrentPlays().find(function(p){return p.key===key;});
        if (!play) return;
        renderSubTabs(play);
        state.subTab = (play.subs && play.subs.length > 0) ? play.subs[0].key : '';
        renderBetArea(); updateBetCount();
    }

    function updateKuaijieOdds() {
        var pt = state.subTab || 'baiwei';
        var area = document.getElementById('betArea');
        if (!area) return;
        area.querySelectorAll('.bet-row-item').forEach(function(row) {
            var key = row.dataset.key;
            if (!key) return;
            var o;
            if (key.startsWith('num_')) {
                var num = key.replace('num_', '');
                o = getItemOdds(pt, ['num_' + num, '' + num], ODDS.number);
            } else {
                o = getItemOdds(pt, [pt + '_' + key, key], ODDS[key] || 1.97);
            }
            row.dataset.odds = o;
            var oddsSpan = row.querySelector('.row-odds');
            if (oddsSpan) oddsSpan.innerHTML = fmtOdds(o);
        });
        updateSel();
    }

    function renderSubTabs(play) {
        var c = document.getElementById('playSubTabs'); if (!c) return;
        if (!play.subs || play.subs.length === 0) { c.innerHTML = ''; c.style.display = 'none'; return; }
        c.style.display = 'flex';
        var isMultiSelect = (play.key === 'kuaijie');
        var html = '';
        play.subs.forEach(function(s, i) {
            var active = isMultiSelect ? ' active' : (i === 0 ? ' active' : '');
            html += '<div class="play-sub-tab' + active + '" data-sub="' + s.key + '">' + s.name + '</div>';
        });
        c.innerHTML = html;
        if (isMultiSelect) {
            // 多选模式：点击切换选中状态
            c.querySelectorAll('.play-sub-tab').forEach(function(tab) {
                tab.addEventListener('click', function() {
                    tab.classList.toggle('active');
                    // 至少保留一个选中
                    var activeCount = c.querySelectorAll('.play-sub-tab.active').length;
                    if (activeCount === 0) tab.classList.add('active');
                    // 收集所有选中的子Tab
                    state.subTabs = [];
                    c.querySelectorAll('.play-sub-tab.active').forEach(function(t) {
                        state.subTabs.push(t.dataset.sub);
                    });
                    state.subTab = state.subTabs.length > 0 ? state.subTabs[0] : '';
                    updateKuaijieOdds();
                });
            });
            // 初始化默认全部选中
            state.subTabs = play.subs.map(function(s){ return s.key; });
        } else {
            c.querySelectorAll('.play-sub-tab').forEach(function(tab) {
                tab.addEventListener('click', function() {
                    c.querySelectorAll('.play-sub-tab').forEach(function(t){t.classList.remove('active');});
                    tab.classList.add('active');
                    state.subTab = tab.dataset.sub;
                    renderBetArea();
                });
            });
        }
    }

    /* ===============================================================
       冷热 / 遗漏
       =============================================================== */
    function initColdHotToolbar() {
        var container = document.getElementById('coldHotToolbar');
        if (!container) return;
        container.innerHTML =
            '<div class="ch-period-btns">' +
            '<button class="ch-btn ch-period" data-p="20">20</button>' +
            '<button class="ch-btn ch-period" data-p="50">50</button>' +
            '<button class="ch-btn ch-period active" data-p="100">100</button>' +
            '</div>' +
            '<div class="ch-mode-btns">' +
            '<label class="ch-radio"><input type="radio" name="chMode" value="hot"> 冷热</label>' +
            '<label class="ch-radio"><input type="radio" name="chMode" value="missing"> 当前遗漏</label>' +
            '</div>';

        // 期数切换
        container.querySelectorAll('.ch-period').forEach(function(btn) {
            btn.addEventListener('click', function() {
                container.querySelectorAll('.ch-period').forEach(function(b){ b.classList.remove('active'); });
                btn.classList.add('active');
                state.coldHotPeriods = parseInt(btn.dataset.p);
                if (state.coldHotMode) fetchColdHotData();
            });
        });

        // 模式切换
        container.querySelectorAll('input[name="chMode"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                state.coldHotMode = radio.value;
                fetchColdHotData();
            });
        });
    }

    function fetchColdHotData() {
        var limit = state.coldHotPeriods;
        fetch('/index.php/api/lottery/getDraws?type=' + state.lotteryType + '&limit=' + limit)
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.code !== 1 || !res.data) return;
                var draws = res.data;
                var numLen = (draws[0] && draws[0].numbers_arr) ? draws[0].numbers_arr.length : 3;
                // 初始化统计数组
                var freq = [];    // freq[pos][num] = count
                var last = [];    // last[pos][num] = last appearance index (0=most recent)
                for (var p = 0; p < numLen; p++) {
                    freq.push({});
                    last.push({});
                    for (var n = 0; n <= 9; n++) { freq[p][n] = 0; last[p][n] = -1; }
                }
                // 统计
                draws.forEach(function(d, idx) {
                    var nums = d.numbers_arr.map(Number);
                    nums.forEach(function(num, pos) {
                        if (pos >= numLen) return;
                        freq[pos][num]++;
                        if (last[pos][num] === -1) last[pos][num] = idx; // 最近出现期
                    });
                });
                // 计算遗漏：当前期 - 最近出现期（未出现则=limit）
                var missing = [];
                for (var p2 = 0; p2 < numLen; p2++) {
                    missing.push({});
                    for (var n2 = 0; n2 <= 9; n2++) {
                        missing[p2][n2] = last[p2][n2] === -1 ? limit : last[p2][n2];
                    }
                }
                state.coldHotData = { freq: freq, missing: missing, numLen: numLen };
                applyColdHotColors();
            })
            .catch(function() {});
    }

    function applyColdHotColors() {
        if (!state.coldHotMode || !state.coldHotData || !state.coldHotData.freq) return;
        var area = document.getElementById('betArea');
        if (!area) return;
        var data = state.coldHotData;
        var useFreq = (state.coldHotMode === 'hot');

        area.querySelectorAll('.bzp-ball').forEach(function(ball) {
            var pos = parseInt(ball.dataset.pos);
            var num = parseInt(ball.dataset.num);
            if (isNaN(pos) || isNaN(num)) return;
            if (pos >= data.numLen) return;

            var vals = useFreq ? data.freq[pos] : data.missing[pos];
            var valArr = [];
            for (var k = 0; k <= 9; k++) valArr.push(vals[k]);
            var maxV = Math.max.apply(null, valArr);
            var minV = Math.min.apply(null, valArr);
            var v = vals[num];

            // 清除旧标注
            ball.removeAttribute('data-ch');
            var hint = ball.querySelector('.ch-hint');
            if (hint) hint.parentNode.removeChild(hint);

            // 添加数值标注
            var hintEl = document.createElement('span');
            hintEl.className = 'ch-hint';
            hintEl.textContent = v;
            ball.appendChild(hintEl);

            // 着色
            if (v === maxV) { ball.setAttribute('data-ch', 'hot'); }
            else if (v === minV) { ball.setAttribute('data-ch', 'cold'); }
            else { ball.setAttribute('data-ch', 'normal'); }
        });
    }

    function clearColdHotColors() {
        var area = document.getElementById('betArea');
        if (!area) return;
        area.querySelectorAll('.bzp-ball').forEach(function(ball) {
            ball.removeAttribute('data-ch');
            var hint = ball.querySelector('.ch-hint');
            if (hint) hint.parentNode.removeChild(hint);
        });
    }

    /* ===============================================================
       投注区渲染
       =============================================================== */
    function renderBetArea() {
        var c = document.getElementById('betArea'), r = document.getElementById('playRuleDesc');
        if (!c) return;
        var html = '', rule = '';

        // 自动隐藏冷热/遗漏工具栏：仅球形玩法显示
        var chToolbar = document.getElementById('coldHotToolbar');
        if (chToolbar) {
            var ballUIs = ['row1','row2','row3','dxds_pos3'];
            var hasBall = false;
            if (state.panelType === 'shuangmian') {
                var curPlay = getCurrentPlays().find(function(p){return p.key===state.playType;});
                if (curPlay && ballUIs.indexOf(curPlay.ui) > -1) hasBall = true;
            } else {
                var bzpPlay = findBzpPlay(state.playType);
                if (bzpPlay && ballUIs.indexOf(bzpPlay.ui) > -1) hasBall = true;
            }
            chToolbar.style.display = hasBall ? '' : 'none';
            if (!hasBall) { state.coldHotMode = null; clearColdHotColors(); }
        }

        // ===== 标准盘：根据play定义的ui类型渲染 =====
        if (state.panelType === 'biaozhun') {
            var play = findBzpPlay(state.playType);
            if (play) {
                var bzpAmt = (state.bzpUnit || 1) * (state.bzpMultiple || 1);
                var prizeDisplay = '';
                if (play.prize) {
                    if (typeof play.prize === 'number') {
                        prizeDisplay = fmtOdds(play.prize * bzpAmt);
                    } else {
                        var pp = String(play.prize).split('~');
                        if (pp.length === 2) {
                            prizeDisplay = fmtOdds(parseFloat(pp[0]) * bzpAmt) + '~' + fmtOdds(parseFloat(pp[1]) * bzpAmt);
                        } else {
                            prizeDisplay = play.prize;
                        }
                    }
                }
                rule = play.name + (prizeDisplay ? '  奖金: <span id=\"ruleOddsValue\">' + prizeDisplay + '</span>元' : '');
                switch (play.ui) {
                    case 'row5': html = renderBzpBallRows(['万位','千位','百位','十位','个位'], play); break;
                    case 'row4': html = renderBzpBallRows(['千位','百位','十位','个位'], play); break;
                    case 'row4_q4': html = renderBzpBallRows(['万位','千位','百位','十位'], play); break;
                    case 'row3': html = renderBzpRow3(play); break;
                    case 'row3_q3': html = renderBzpBallRows(['万位','千位','百位'], play); break;
                    case 'row3_z3': html = renderBzpBallRows(['千位','百位','十位'], play); break;
                    case 'row3_h3': html = renderBzpBallRows(['百位','十位','个位'], play); break;
                    case 'row2_qe': html = renderBzpRow2(['百位','十位'], play); break;
                    case 'row2_he': html = renderBzpRow2(['十位','个位'], play); break;
                    case 'row5_pick2': case 'row5_pick3': case 'row5_pick4':
                        html = renderBzpBallRows(['万位','千位','百位','十位','个位'], play); break;
                    case 'row2_zx60': case 'row2_zx30': case 'row2_zx12':
                        html = renderBzpBallRows(['二重号', '单号'], play); break;
                    case 'row2_zx20': case 'row2_zx4':
                        html = renderBzpBallRows(['三重号', '单号'], play); break;
                    case 'row2_zx10': html = renderBzpBallRows(['三重号', '二重号'], play); break;
                    case 'row2_zx5': html = renderBzpBallRows(['四重号', '单号'], play); break;
                    case 'row1': html = renderBzpRow1(play); break;
                    case 'text': html = renderBzpText(play); break;
                    case 'hz27': html = renderBzpHz(27); break;
                    case 'hz18': html = renderBzpHz(18); break;
                    case 'kd9': html = renderBzpKd(); break;
                    case 'lhh': html = renderBzpLhh(); break;
                    case 'dxds_pos': html = renderBzpDxds(['百位','十位','个位']); break;
                    case 'dxds_pos3': html = renderBzpDxds(['百位','十位','个位']); break;
                    case 'dxds_pos2_q': html = renderBzpDxds(['万位','千位']); break;
                    case 'dxds_pos3_q': html = renderBzpDxds(['万位','千位','百位']); break;
                    case 'dxds_pos2_h': html = renderBzpDxds(['十位','个位']); break;
                    case 'dxds_pos3_h': html = renderBzpDxds(['百位','十位','个位']); break;
                    case 'dxds_hz4': html = renderBzpDxdsHz(); break;
                    case 'poker_suoha': html = renderPokerSuoha(); break;
                    case 'poker_zjh': html = renderPokerZjh(); break;
                    case 'poker_nn': html = renderPokerNiuniu(); break;
                    default: html = '<div style="padding:40px;text-align:center;color:var(--text-muted);">暂未实现</div>';
                }
            } else {
                rule = '请选择玩法'; html = '<div style="padding:40px;text-align:center;color:var(--text-muted);">请选择具体玩法</div>';
            }
            if (r) r.innerHTML = '<span style="color:var(--primary);margin-right:4px;">✦</span> ' + rule + ' <span onclick="BetPage.showPlayRule()" style="color:var(--primary);cursor:pointer;margin-left:8px;font-size:13px;border:1px solid var(--primary);border-radius:4px;padding:2px 8px;white-space:nowrap;">📖 玩法说明</span>';
            c.innerHTML = html;
            bindBzpEvents(c, play);
            updateGlobalOddsDisplay();
            return;
        }

        // ===== 双面盘：保持原有switch逻辑 =====
        switch (state.playType) {
            case 'kuaijie':
                rule = '选择大小单双或号码0-9投注';
                var pt = state.subTab || 'baiwei';
                html = renderDxdszh(pt) + renderNumbers09(null, pt);
                break;
            case 'shuangmian':
                rule = '对百位/十位/个位及组合的大小单双进行投注';
                html = renderShuangmianAll();
                break;
            case 'zhenghe':
                rule = '总和大小单双 + 龙虎和 + 前中后三特殊形态';
                html = renderZhenghe();
                break;
            case 'yizi_dingwei':
                rule = '百位/十位/个位各选号码0-9，该位号码一致即中奖（奖金<span id="ruleOddsValue">' + fmtOdds(ODDS.number * (state.betAmount||1)) + '</span>）';
                html = renderYiziDingweiFc3d();
                break;
            case 'erzi_dingwei':
                rule = '在指定两个位置各选号码，两位全对即中奖（奖金<span id="ruleOddsValue">' + fmtOdds(ODDS.erzi_dingwei * (state.betAmount||1)) + '</span>）';
                html = renderPositionSelector(2);
                break;
            case 'sanzi_dingwei':
                rule = '在指定三个位置各选号码，三位全对即中奖（奖金<span id="ruleOddsValue">' + fmtOdds(ODDS.sanzi_dingwei * (state.betAmount||1)) + '</span>）';
                html = renderPositionSelector(3);
                break;
            case 'yizi_zuhe':
                rule = '选1个号码，开奖号码任意一位包含该号即中奖（奖金<span id="ruleOddsValue">' + fmtOdds(ODDS.yizi_zuhe * (state.betAmount||1)) + '</span>）';
                html = renderNumbers09(ODDS.yizi_zuhe);
                break;
            case 'sanzi_zuhe':
                rule = '选择3个号码组合，与开奖号码一致即中奖（不分位置）';
                html = renderSanziZuhe();
                break;
            case 'erzi_zuhe':
                rule = '选择2个号码组合，开奖号码任意两位包含即中奖';
                html = renderErziZuhe();
                break;
            case 'erzi_heshu':
                rule = '选择指定两位数字的和（0-18）投注，不同和值赔率不同';
                html = renderErziHeshu();
                break;
            case 'zusan':
                rule = '组三：选1个重号和1个不重号，重号出现2次不重号出现1次（不论顺序）即中奖（奖金<span id="ruleOddsValue">' + fmtOdds(ODDS.zusan * (state.betAmount||1)) + '</span>）';
                html = renderZusan();
                break;
            case 'zuliu':
                rule = '组六：选择3个号码，与开奖号码相同（不论顺序）即中奖（奖金<span id="ruleOddsValue">' + fmtOdds(ODDS.zuliu * (state.betAmount||1)) + '</span>）';
                html = '<p style="font-size:13px;color:var(--text-muted);margin-bottom:12px;">至少选择3项</p>';
                html += '<div class="combo-input-area"><div class="combo-row"><div class="combo-numbers" style="justify-content:center;">';
                for (var zi = 0; zi <= 9; zi++) html += '<div class="combo-num-btn" data-pos="0" data-num="' + zi + '">' + zi + '</div>';
                html += '</div></div></div>';
                break;
            case 'kuadu':
                rule = '选择1个数值，与开奖号码最大值和最小值之差相同即中奖';
                html = renderKuadu();
                break;
            case 'hezhi':
                rule = '选择三位数之和（0-27）的值投注，不同和值赔率不同';
                html = renderHezhi();
                break;
            default:
                rule = '请选择玩法'; html = '<div style="padding:40px;text-align:center;color:var(--text-muted);">请选择具体玩法</div>';
        }
        if (r) r.innerHTML = '<span style="color:var(--primary);margin-right:4px;">✦</span> ' + rule + ' <span onclick="BetPage.showPlayRule()" style="color:var(--primary);cursor:pointer;margin-left:8px;font-size:13px;border:1px solid var(--primary);border-radius:4px;padding:2px 8px;white-space:nowrap;">📖 玩法说明</span>';
        c.innerHTML = html;
        bindBetOptions(c);
        updateGlobalOddsDisplay();
    }

    /* ===============================================================
       全局赔率显示
       =============================================================== */
    function updateGlobalOddsDisplay() {
        var oddsDisp = document.getElementById('currentOddsDisplay');
        if (!oddsDisp) return;

        var betCount = calcRealBetCount();
        if (betCount <= 0) { oddsDisp.style.display = 'none'; return; }

        var globalOdds = 0;
        if (state.panelType === 'biaozhun') {
            var play = findBzpPlay(state.playType);
            if (play && play.prize) globalOdds = play.prize;
        } else if (state.panelType === 'shuangmian') {
            switch (state.playType) {
                case 'yizi_zuhe': globalOdds = ODDS.yizi_zuhe; break;
                case 'yizi_dingwei': globalOdds = ODDS.number; break;
                case 'erzi_dingwei': globalOdds = ODDS.erzi_dingwei; break;
                case 'sanzi_dingwei': globalOdds = ODDS.sanzi_dingwei; break;
                case 'zusan': globalOdds = ODDS.zusan; break;
                case 'zuliu': globalOdds = ODDS.zuliu; break;
            }
        }
        if (!globalOdds) {
            var area = document.getElementById('betArea');
            if (area) {
                var oddsArr = [];
                area.querySelectorAll('[data-odds]').forEach(function(el) {
                    var o = parseFloat(el.dataset.odds);
                    if (!isNaN(o) && o > 0) oddsArr.push(o);
                });
                if (oddsArr.length > 0) {
                    var minO = Math.min.apply(null, oddsArr);
                    var maxO = Math.max.apply(null, oddsArr);
                    globalOdds = minO === maxO ? minO : minO + '~' + maxO;
                }
            }
        }
        if (globalOdds) {
            // 标准盘用 bzpUnit*bzpMultiple，双面盘用 betAmount
            var amt = (state.panelType === 'biaozhun')
                ? (state.bzpUnit || 1) * (state.bzpMultiple || 1)
                : (state.betAmount || 1);
            var prizeStr;
            if (typeof globalOdds === 'number') {
                prizeStr = fmtOdds(globalOdds * amt);
            } else {
                // 范围赔率如 "1.97~9.85"
                var parts = String(globalOdds).split('~');
                if (parts.length === 2) {
                    prizeStr = fmtOdds(parseFloat(parts[0]) * amt) + '~' + fmtOdds(parseFloat(parts[1]) * amt);
                } else {
                    prizeStr = globalOdds;
                }
            }
            oddsDisp.innerHTML = '奖金: <span id="currentOddsValue" style="font-weight:700;">' + prizeStr + '</span>';
            oddsDisp.style.display = 'inline-block';
            // 同步更新上面赔率说明中的数值
            var ruleOddsEl = document.getElementById('ruleOddsValue');
            if (ruleOddsEl) ruleOddsEl.textContent = prizeStr;
        } else {
            oddsDisp.style.display = 'none';
        }
    }

    /* ===============================================================
       标准盘渲染组件 (圆形号码球 + 快捷按钮)
       =============================================================== */

    // 三行选号器 (百位/十位/个位)
    function renderBzpRow3(play) {
        var labels = ['百位','十位','个位'];
        return renderBzpBallRows(labels, play);
    }

    // 两行选号器 (前二/后二)
    function renderBzpRow2(labels, play) {
        return renderBzpBallRows(labels, play);
    }

    // 单行选号器 (组选/不定胆)
    function renderBzpRow1(play) {
        var html = '<div class="bzp-selector">';
        html += '<div class="bzp-ball-row">';
        for (var i = 0; i <= 9; i++) {
            html += '<div class="bzp-ball" data-pos="0" data-num="' + i + '">' + i + '</div>';
        }
        if (!play.max || play.max > 1) {
            html += renderBzpQuick(0);
        }
        html += '</div></div>';
        return html;
    }

    // 通用多行号码球渲染
    function renderBzpBallRows(labels, play) {
        var html = '<div class="bzp-selector">';
        // 顶部全局快捷工具栏
        html += '<div class="bzp-global-quick" style="display:flex;gap:6px;margin-bottom:12px;flex-wrap:wrap;">';
        [['all_all','全选'],['da_all','大'],['xiao_all','小'],['dan_all','单'],['shuang_all','双'],['clear_all','清除']].forEach(function(b) {
            html += '<span class="bzp-quick-btn" data-action="' + b[0] + '" data-pos="all" style="padding:4px 12px;font-size:13px;">' + b[1] + '</span>';
        });
        html += '</div>';
        labels.forEach(function(lbl, idx) {
            html += '<div class="bzp-ball-row">';
            html += '<span class="bzp-pos-label">' + lbl + '</span>';
            for (var i = 0; i <= 9; i++) {
                html += '<div class="bzp-ball" data-pos="' + idx + '" data-num="' + i + '">' + i + '</div>';
            }
            html += renderBzpQuick(idx);
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    // 快捷按钮: 全 大 小 奇 偶 清
    function renderBzpQuick(pos) {
        var btns = [['all','全'],['da','大'],['xiao','小'],['ji','奇'],['ou','偶'],['clear','清']];
        var html = '<span class="bzp-quick-btns">';
        btns.forEach(function(b) {
            html += '<span class="bzp-quick-btn" data-action="' + b[0] + '" data-pos="' + pos + '">' + b[1] + '</span>';
        });
        return html + '</span>';
    }

    // 文本输入 (单式/混合)
    function renderBzpText(play) {
        var ph = '请输入号码，多注用逗号或换行分隔';
        if (play.key.indexOf('sx_') === 0 || play.key === 'sx_hunhe') ph = '请输入3位数号码，如 123,456,789';
        else if (play.key.indexOf('qe_') === 0) ph = '请输入2位数号码，如 12,34,56';
        else if (play.key.indexOf('he_') === 0) ph = '请输入2位数号码，如 12,34,56';
        return '<div class="bzp-text-input">' +
            '<textarea class="bzp-textarea" id="bzpTextInput" rows="5" placeholder="' + ph + '"></textarea>' +
            '<div style="display:flex;gap:8px;margin-top:8px;">' +
            '<button class="bzp-parse-btn" onclick="BetPage.parseBzpText()">解析号码</button>' +
            '<button class="bzp-parse-btn bzp-clear-btn" onclick="document.getElementById(\'bzpTextInput\').value=\'\'">清空</button>' +
            '</div></div>';
    }

    // 和值网格 (0-27 或 0-18)
    function renderBzpHz(max) {
        // 直选组合数 (和值=i 时有多少种排列)
        var combos27 = [1,3,6,10,15,21,28,36,45,55,63,69,73,75,75,73,69,63,55,45,36,28,21,15,10,6,3,1];
        var combos18 = [1,2,3,4,5,6,7,8,9,10,9,8,7,6,5,4,3,2,1];
        // 组选组合数 (和值=i 时有多少种不重复组合，排除和值0和27因为是豹子)
        var zxCombos27 = [0,0,0,1,1,2,3,4,5,7,8,9,10,10,11,10,10,9,8,7,5,4,3,2,1,0,0,0];

        var isZuxuan = state.playType.indexOf('hezhi2') > -1 || state.playType.indexOf('zuxuan') > -1;
        var combos, total;

        // 组选和值：使用硬编码赔率表（与参考站一致）
        var zxOddsMap = null;
        if (isZuxuan && max === 27) {
            zxOddsMap = {1:328.3333,2:328.3333,3:164.1666,4:164.1666,5:164.1666,6:164.1666,7:164.1666,8:164.1666,
                9:164.1666,10:164.1666,11:164.1666,12:164.1666,13:164.1666,14:164.1666,15:164.1666,16:164.1666,
                17:164.1666,18:164.1666,19:164.1666,20:164.1666,21:164.1666,22:164.1666,23:164.1666,
                24:328.3333,25:328.3333,26:328.3333};
        }

        if (!zxOddsMap) {
            combos = (max === 27) ? combos27 : combos18;
            total = (max === 27) ? 1000 : 100;
        }

        var start = isZuxuan ? 1 : 0;
        var end = isZuxuan ? max - 1 : max;
        var daThreshold = (max === 27) ? 14 : 10;

        var html = '<div class="bzp-selector" style="flex-direction:row;align-items:flex-start;"><div class="bzp-hz-grid" style="flex:1;">';
        for (var i = start; i <= end; i++) {
            var odds;
            if (zxOddsMap) {
                odds = zxOddsMap[i] || 999;
            } else {
                var prob = combos[i] / total;
                odds = prob > 0 ? Math.round((0.97 / prob) * 10000) / 10000 : 999;
            }
            var isDa = i >= daThreshold ? 'true' : 'false';
            html += '<div class="bzp-hz-item" data-num="' + i + '" data-odds="' + odds + '" data-da="' + isDa + '">' + i + '</div>';
        }
        html += '</div>';
        html += renderBzpQuick('hz');
        html += '</div>';
        return html;
    }

    // 跨度网格 (0-9)
    function renderBzpKd() {
        var html = '<div class="bzp-selector" style="flex-direction:row;align-items:flex-start;"><div class="bzp-hz-grid" style="flex:1;">';
        for (var i = 0; i <= 9; i++) {
            var isDa = i >= 5 ? 'true' : 'false';
            html += '<div class="bzp-hz-item" data-num="' + i + '" data-odds="0" data-da="' + isDa + '">' + i + '</div>';
        }
        html += '</div>';
        html += renderBzpQuick('hz');
        html += '</div>';
        return html;
    }

    // 龙虎和
    function renderBzpLhh() {
        var pairs = [
            { label: '万千', items: [{k:'wq_long',n:'龙',o:2.1888},{k:'wq_hu',n:'虎',o:2.1888},{k:'wq_he',n:'和',o:9.85}] },
            { label: '万百', items: [{k:'wb_long',n:'龙',o:2.1888},{k:'wb_hu',n:'虎',o:2.1888},{k:'wb_he',n:'和',o:9.85}] },
            { label: '万十', items: [{k:'ws_long',n:'龙',o:2.1888},{k:'ws_hu',n:'虎',o:2.1888},{k:'ws_he',n:'和',o:9.85}] },
            { label: '万个', items: [{k:'wg_long',n:'龙',o:2.1888},{k:'wg_hu',n:'虎',o:2.1888},{k:'wg_he',n:'和',o:9.85}] },
            { label: '千百', items: [{k:'qb_long',n:'龙',o:2.1888},{k:'qb_hu',n:'虎',o:2.1888},{k:'qb_he',n:'和',o:9.85}] },
            { label: '千十', items: [{k:'qs_long',n:'龙',o:2.1888},{k:'qs_hu',n:'虎',o:2.1888},{k:'qs_he',n:'和',o:9.85}] },
            { label: '千个', items: [{k:'qg_long',n:'龙',o:2.1888},{k:'qg_hu',n:'虎',o:2.1888},{k:'qg_he',n:'和',o:9.85}] },
            { label: '百十', items: [{k:'bs_long',n:'龙',o:2.1888},{k:'bs_hu',n:'虎',o:2.1888},{k:'bs_he',n:'和',o:9.85}] },
            { label: '百个', items: [{k:'bg_long',n:'龙',o:2.1888},{k:'bg_hu',n:'虎',o:2.1888},{k:'bg_he',n:'和',o:9.85}] },
            { label: '十个', items: [{k:'sg_long',n:'龙',o:2.1888},{k:'sg_hu',n:'虎',o:2.1888},{k:'sg_he',n:'和',o:9.85}] }
        ];
        var html = '<div class="bzp-lhh-grid bzp-lhh-grid-col2">';
        pairs.forEach(function(pair) {
            html += '<div class="bzp-lhh-row">';
            html += '<span class="bzp-lhh-label">' + pair.label + '</span>';
            pair.items.forEach(function(item) {
                html += '<div class="bzp-lhh-item" data-key="' + item.k + '" data-odds="' + item.o + '">';
                html += '<span class="bzp-lhh-name">' + item.n + '</span>';
                html += '<span class="bzp-lhh-odds">' + item.o + '</span>';
                html += '</div>';
            });
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    // 大小单双（按位置）
    function renderBzpDxds(positions) {
        positions = positions || ['百位','十位','个位'];
        var items = [
            {k:'da',n:'大',o:1.97}, {k:'xiao',n:'小',o:1.97},
            {k:'dan',n:'单',o:1.97}, {k:'shuang',n:'双',o:1.97}
        ];
        var html = '<div class="bzp-lhh-grid">';
        positions.forEach(function(pos, idx) {
            html += '<div class="bzp-lhh-row">';
            html += '<span class="bzp-lhh-label">' + pos + '</span>';
            items.forEach(function(item) {
                html += '<div class="bzp-lhh-item" data-key="' + pos + '_' + item.k + '" data-odds="' + item.o + '">';
                html += '<span class="bzp-lhh-name">' + item.n + '</span>';
                html += '<span class="bzp-lhh-odds">' + item.o + '</span>';
                html += '</div>';
            });
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    // ===== 和值大小单双（单行4选） =====
    function renderBzpDxdsHz() {
        var items = [
            {k:'hz_da',n:'大',o:1.96}, {k:'hz_xiao',n:'小',o:1.96},
            {k:'hz_dan',n:'单',o:1.96}, {k:'hz_shuang',n:'双',o:1.96}
        ];
        var html = '<div class="bzp-lhh-grid" style="display:flex;flex-wrap:wrap;gap:12px;padding:20px;">';
        items.forEach(function(item) {
            html += '<div class="bzp-lhh-item" data-key="' + item.k + '" data-odds="' + item.o + '" style="min-width:100px;flex:1;">';
            html += '<span class="bzp-lhh-name">' + item.n + '</span>';
            html += '<span class="bzp-lhh-odds">' + item.o + '</span>';
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    // ===== 梭哈 =====
    function renderPokerSuoha() {
        var items = [
            {k:'sh_sitiao',  n:'四条',  o:218.8888},
            {k:'sh_hulu',    n:'葫芦',  o:109.4444},
            {k:'sh_shunzi',  n:'顺子',  o:82.0833},
            {k:'sh_santiao', n:'三条',  o:13.6804},
            {k:'sh_liangdui',n:'两对',  o:9.1203},
            {k:'sh_yidui',   n:'一对',  o:1.9543},
            {k:'sh_danpai',  n:'单牌',  o:3.3918}
        ];
        var html = '<div class="bzp-lhh-grid" style="display:flex;flex-wrap:wrap;gap:12px;padding:20px;">';
        items.forEach(function(item) {
            html += '<div class="bzp-lhh-item" data-key="' + item.k + '" data-odds="' + item.o + '" style="min-width:120px;flex:1;">';
            html += '<span class="bzp-lhh-name">' + item.n + '</span>';
            html += '<span class="bzp-lhh-odds">' + item.o + '</span>';
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    // ===== 三星炸金花 =====
    function renderPokerZjh() {
        var rows = [
            { label:'前三', prefix:'q3' },
            { label:'中三', prefix:'z3' },
            { label:'后三', prefix:'h3' }
        ];
        var types = [
            {k:'baozi', n:'豹子', o:98.5},
            {k:'shunzi',n:'顺子', o:16.4166},
            {k:'duizi', n:'对子', o:3.6481},
            {k:'zaliu',  n:'杂六', o:3.2833},
            {k:'banshun',n:'半顺', o:2.736}
        ];
        var html = '<div class="bzp-lhh-grid">';
        rows.forEach(function(row) {
            html += '<div class="bzp-lhh-row">';
            html += '<span class="bzp-lhh-label">' + row.label + '</span>';
            types.forEach(function(t) {
                var key = row.prefix + '_' + t.k;
                html += '<div class="bzp-lhh-item" data-key="' + key + '" data-odds="' + t.o + '">';
                html += '<span class="bzp-lhh-name">' + t.n + '</span>';
                html += '<span class="bzp-lhh-odds">' + t.o + '</span>';
                html += '</div>';
            });
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    // ===== 牛牛 =====
    function renderPokerNiuniu() {
        var items = [
            {k:'nn_nuda',   n:'牛大',  o:3.0574},
            {k:'nn_nuxiao',  n:'牛小',  o:3.0651},
            {k:'nn_nudan',   n:'牛单',  o:3.0901},
            {k:'nn_nushuang',n:'牛双',  o:3.033},
            {k:'nn_wuniu',   n:'无牛',  o:2.763},
            {k:'nn_niu1',    n:'牛一',  o:15.4509},
            {k:'nn_niu2',    n:'牛二',  o:15.1421},
            {k:'nn_niu3',    n:'牛三',  o:15.4509},
            {k:'nn_niu4',    n:'牛四',  o:15.1421},
            {k:'nn_niu5',    n:'牛五',  o:15.4509},
            {k:'nn_niu6',    n:'牛六',  o:15.1421},
            {k:'nn_niu7',    n:'牛七',  o:15.4509},
            {k:'nn_niu8',    n:'牛八',  o:15.1421},
            {k:'nn_niu9',    n:'牛九',  o:15.4509},
            {k:'nn_niuniu',  n:'牛牛',  o:15.257}
        ];
        var html = '<div class="bzp-lhh-grid" style="display:flex;flex-wrap:wrap;gap:12px;padding:20px;">';
        items.forEach(function(item) {
            html += '<div class="bzp-lhh-item" data-key="' + item.k + '" data-odds="' + item.o + '" style="min-width:110px;flex:1;">';
            html += '<span class="bzp-lhh-name">' + item.n + '</span>';
            html += '<span class="bzp-lhh-odds">' + item.o + '</span>';
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    // 标准盘事件绑定
    function bindBzpEvents(c, play) {
        // 号码球点选
        c.querySelectorAll('.bzp-ball').forEach(function(ball) {
            ball.addEventListener('click', function() {
                if (play && play.max === 1) {
                    var pos = ball.dataset.pos;
                    c.querySelectorAll('.bzp-ball[data-pos="' + pos + '"]').forEach(function(b) {
                        if (b !== ball) b.classList.remove('selected');
                    });
                }
                ball.classList.toggle('selected');
                updateBzpSel();
            });
        });
        // 快捷按钮
        c.querySelectorAll('.bzp-quick-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var action = btn.dataset.action;
                var pos = btn.dataset.pos;
                var realAction = action;
                if (action.indexOf('_all') > -1) {
                    realAction = action.replace('_all', '');
                }
                
                var selector = pos === 'all' ? '.bzp-ball' : '.bzp-ball[data-pos="' + pos + '"]';
                if (pos === 'hz') selector = '.bzp-hz-item';
                
                c.querySelectorAll(selector).forEach(function(b) {
                    var n = parseInt(b.dataset.num);
                    switch(realAction) {
                        case 'all': b.classList.add('selected'); break;
                        case 'da': 
                            if (pos === 'hz') {
                                b.dataset.da === 'true' ? b.classList.add('selected') : b.classList.remove('selected');
                            } else {
                                n >= 5 ? b.classList.add('selected') : b.classList.remove('selected'); 
                            }
                            break;
                        case 'xiao': 
                            if (pos === 'hz') {
                                b.dataset.da === 'false' ? b.classList.add('selected') : b.classList.remove('selected');
                            } else {
                                n <= 4 ? b.classList.add('selected') : b.classList.remove('selected'); 
                            }
                            break;
                        case 'ji': n % 2 === 1 ? b.classList.add('selected') : b.classList.remove('selected'); break;
                        case 'ou': n % 2 === 0 ? b.classList.add('selected') : b.classList.remove('selected'); break;
                        case 'dan': n % 2 === 1 ? b.classList.add('selected') : b.classList.remove('selected'); break;
                        case 'shuang': n % 2 === 0 ? b.classList.add('selected') : b.classList.remove('selected'); break;
                        case 'clear': b.classList.remove('selected'); break;
                    }
                });
                updateBzpSel();
            });
        });
        // 和值/跨度点选
        c.querySelectorAll('.bzp-hz-item').forEach(function(item) {
            item.addEventListener('click', function() {
                item.classList.toggle('selected');
                updateBzpSel();
            });
        });
        // 龙虎和点选
        c.querySelectorAll('.bzp-lhh-item').forEach(function(item) {
            item.addEventListener('click', function() {
                item.classList.toggle('selected');
                updateBzpSel();
            });
        });
    }

    function updateBzpSel() {
        state.selectedBets = [];
        var c = document.getElementById('betArea');
        // 号码球 - 按位分组收集
        var positions = {};
        c.querySelectorAll('.bzp-ball.selected').forEach(function(b) {
            var pos = b.dataset.pos;
            if (!positions[pos]) positions[pos] = [];
            positions[pos].push(parseInt(b.dataset.num));
        });
        // 将选中的号码加入selectedBets
        Object.keys(positions).forEach(function(pos) {
            positions[pos].forEach(function(num) {
                state.selectedBets.push({ key: 'p' + pos + '_' + num, odds: 1, pos: parseInt(pos), num: num });
            });
        });
        // 和值/跨度选中
        c.querySelectorAll('.bzp-hz-item.selected').forEach(function(item) {
            state.selectedBets.push({ key: 'hz_' + item.dataset.num, odds: parseFloat(item.dataset.odds) || 1 });
        });
        // 龙虎和选中
        c.querySelectorAll('.bzp-lhh-item.selected').forEach(function(item) {
            state.selectedBets.push({ key: item.dataset.key, odds: parseFloat(item.dataset.odds) || 1 });
        });
        updateBetCount();
    }

    function parseBzpText() {
        var inp = document.getElementById('bzpTextInput'); if (!inp) return;
        var play = findBzpPlay(state.playType);
        var len = 3; // default
        if (play && play.key.indexOf('sx4_') === 0) len = 4;
        else if (play && (play.key.indexOf('qe_') === 0 || play.key.indexOf('he_') === 0 || play.key.indexOf('rx2_') === 0)) len = 2;
        else if (play && (play.key === 'sx_ermabuding' || play.key === 'q3_ermabuding')) len = 2;
        var nums = inp.value.trim().split(/[,，\n\r\s]+/).filter(function(n){ return n.length > 0; });
        var re = new RegExp('^\\d{' + len + '}$');
        var valid = nums.filter(function(n){ return re.test(n); });
        // 组选单式：排除对子号（如11, 22, 33...）
        var isZuxuanDanshi = play && play.key.indexOf('zuxuan_danshi') > -1;
        if (isZuxuanDanshi && len === 2) {
            var beforeCount = valid.length;
            valid = valid.filter(function(n){ return n[0] !== n[1]; });
            var removed = beforeCount - valid.length;
            if (removed > 0) showToast('已自动过滤 ' + removed + ' 个对子号（组选不可输入对子）','warning');
        }
        if (!valid.length) { showToast('请输入有效的' + len + '位数号码' + (isZuxuanDanshi ? '（不可输入对子号）' : ''),'error'); return; }
        state.selectedBets = valid.map(function(n){ return { key: n, odds: 1 }; });
        updateBetCount();
        showToast('成功解析 ' + valid.length + ' 注号码','success');
    }

    /* ===============================================================
       PL3/5 整合面板渲染
       =============================================================== */
    function renderZhenghe() {
        var odds = ODDS;
        var html = '';

        // === 1. 总和龙虎和 ===
        html += '<h4 style="font-size:14px;color:var(--text-secondary);margin:0 0 12px;border-left:3px solid var(--primary);padding-left:8px;">总和龙虎和</h4>';
        html += '<div class="bet-category-grid" style="grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:8px;">';
        html += betRow('zhda', '大', getItemOdds('zhenghe', 'da', odds.da));
        html += betRow('zhxiao', '小', getItemOdds('zhenghe', 'xiao', odds.xiao));
        html += betRow('zhdan', '单', getItemOdds('zhenghe', 'dan', odds.dan));
        html += betRow('zhshuang', '双', getItemOdds('zhenghe', 'shuang', odds.shuang));
        html += '</div>';
        html += '<div class="bet-category-grid" style="grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:20px;">';
        html += betRow('long', '龙', getItemOdds('longhu', 'long', odds.long));
        html += betRow('hu', '虎', getItemOdds('longhu', 'hu', odds.hu));
        html += betRow('longhu_he', '和', getItemOdds('longhu', 'longhu_he', odds.longhu_he));
        html += '</div>';

        // === 2. 第一球~第五球 ===
        var ballNames = ['第一球','第二球','第三球','第四球','第五球'];
        var ballKeys = ['b1','b2','b3','b4','b5'];
        var ballDbKeys = ['ball1','ball2','ball3','ball4','ball5'];
        ballNames.forEach(function(name, idx) {
            var bk = ballKeys[idx];
            var dbPt = ballDbKeys[idx];
            html += '<h4 style="font-size:14px;color:var(--text-secondary);margin:0 0 12px;border-left:3px solid var(--primary);padding-left:8px;">' + name + '</h4>';
            // 大小单双质合
            html += '<div class="bet-category-grid" style="grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:8px;">';
            html += betRow(bk + '_da', '大', getItemOdds(dbPt, [dbPt + '_da', 'da'], odds.da));
            html += betRow(bk + '_xiao', '小', getItemOdds(dbPt, [dbPt + '_xiao', 'xiao'], odds.xiao));
            html += betRow(bk + '_dan', '单', getItemOdds(dbPt, [dbPt + '_dan', 'dan'], odds.dan));
            html += betRow(bk + '_shuang', '双', getItemOdds(dbPt, [dbPt + '_shuang', 'shuang'], odds.shuang));
            html += betRow(bk + '_zhi', '质', getItemOdds(dbPt, [dbPt + '_zhi', 'zhi'], odds.zhi));
            html += betRow(bk + '_he', '合', getItemOdds(dbPt, [dbPt + '_he', 'he'], odds.he));
            html += '</div>';
            // 0-9号码
            html += '<div class="bet-category-grid" style="grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:20px;">';
            for (var i = 0; i <= 9; i++) {
                html += betRow(bk + '_' + i, '' + i, getItemOdds(dbPt, ['num_' + i, '' + i], odds.number));
            }
            html += '</div>';
        });

        // === 3. 前三/中三/后三 ===
        var groups = [
            { prefix: 'q3', name: '前三' },
            { prefix: 'z3', name: '中三' },
            { prefix: 'h3', name: '后三' }
        ];
        groups.forEach(function(g) {
            html += '<h4 style="font-size:14px;color:var(--text-secondary);margin:0 0 12px;border-left:3px solid var(--primary);padding-left:8px;">' + g.name + '</h4>';
            html += '<div class="bet-category-grid" style="grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:20px;">';
            html += betRow(g.prefix + '_baozi', '豹子', getItemOdds('xingtai', 'baozi', odds.baozi));
            html += betRow(g.prefix + '_shunzi', '顺子', getItemOdds('xingtai', 'shunzi', odds.shunzi));
            html += betRow(g.prefix + '_duizi', '对子', getItemOdds('xingtai', 'duizi', odds.duizi));
            html += betRow(g.prefix + '_banshun', '半顺', getItemOdds('xingtai', 'banshun', odds.banshun));
            html += betRow(g.prefix + '_zaliu', '杂六', getItemOdds('xingtai', 'zaliu', odds.zaliu));
            html += '</div>';
        });
        return html;
    }

    /* ===============================================================
       PL3/5 一字定位 - 万千百十个全部展示
       =============================================================== */
    function renderYiziDingweiPl3() {
        var yzdw_odds = 9.925;
        var positions = ['万位','千位','百位','十位','个位'];
        var posKeys = ['wan','qian','bai','shi','ge'];
        var posDbKeys = ['ball1','ball2','ball3','ball4','ball5'];
        var html = '';
        positions.forEach(function(name, idx) {
            var pk = posKeys[idx];
            var dbPt = posDbKeys[idx];
            html += '<h4 style="font-size:14px;color:var(--text-secondary);margin:0 0 12px;border-left:3px solid var(--primary);padding-left:8px;">' + name + '</h4>';
            html += '<div class="bet-category-grid" style="grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:20px;">';
            for (var i = 0; i <= 9; i++) {
                var o = getItemOdds(dbPt, ['num_' + i, '' + i], yzdw_odds);
                html += betRow(pk + '_' + i, '' + i, o);
            }
            html += '</div>';
        });
        return html;
    }

    /* FC3D 一字定位 - 百十个三列同时展示 */
    function renderYiziDingweiFc3d() {
        var positions = ['百位','十位','个位'];
        var posKeys = ['bai','shi','ge'];
        var posDbKeys = ['baiwei','shiwei','gewei'];
        var html = '';
        positions.forEach(function(name, idx) {
            var pk = posKeys[idx];
            var dbPt = posDbKeys[idx];
            html += '<h4 style="font-size:14px;color:var(--text-secondary);margin:0 0 12px;border-left:3px solid var(--primary);padding-left:8px;">' + name + '</h4>';
            html += '<div class="bet-category-grid" style="grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:20px;">';
            for (var i = 0; i <= 9; i++) {
                var o = getItemOdds(dbPt, ['num_' + i, '' + i], ODDS.number);
                html += betRow(pk + '_' + i, '' + i, o);
            }
            html += '</div>';
        });
        return html;
    }

    /* ===============================================================
       双面玩法 - 全面板一体渲染 (对标参考站截图)
       =============================================================== */
    function renderShuangmianAll() {
        var odds = ODDS;
        var html = '';

        // ===== 第一区: 百位/十位/个位 三列并排 =====
        var positions = [
            { key: 'bai', name: '百位', db: 'baiwei' },
            { key: 'shi', name: '十位', db: 'shiwei' },
            { key: 'ge', name: '个位', db: 'gewei' }
        ];

        html += '<div class="sm-three-col">';
        positions.forEach(function(pos) {
            var posItems = [
                { k: 'da', n: '大', o: getItemOdds(pos.db, [pos.db + '_da', 'da'], odds.da) },
                { k: 'xiao', n: '小', o: getItemOdds(pos.db, [pos.db + '_xiao', 'xiao'], odds.xiao) },
                { k: 'dan', n: '单', o: getItemOdds(pos.db, [pos.db + '_dan', 'dan'], odds.dan) },
                { k: 'shuang', n: '双', o: getItemOdds(pos.db, [pos.db + '_shuang', 'shuang'], odds.shuang) }
            ];
            html += '<div class="sm-col">';
            html += '<div class="sm-col-title">' + pos.name + '</div>';
            html += '<div class="sm-col-body">';
            posItems.forEach(function(item) {
                html += '<div class="sm-row-item" data-key="' + pos.key + '_' + item.k + '" data-odds="' + item.o + '">';
                html += '<span class="sm-row-name">' + item.n + '</span>';
                html += '<span class="sm-row-odds">' + fmtOdds(item.o) + '</span>';
                html += '<input type="number" class="row-amount" placeholder="" min="0" step="1" data-key="' + pos.key + '_' + item.k + '">';
                html += '</div>';
            });
            // 号码0-9用逐项赔率
            for (var i = 0; i <= 9; i++) {
                var numOdds = getItemOdds(pos.db, ['num_' + i, '' + i], odds.number);
                html += '<div class="sm-row-item" data-key="' + pos.key + '_' + i + '" data-odds="' + numOdds + '">';
                html += '<span class="sm-row-name">' + i + '</span>';
                html += '<span class="sm-row-odds">' + fmtOdds(numOdds) + '</span>';
                html += '<input type="number" class="row-amount" placeholder="" min="0" step="1" data-key="' + pos.key + '_' + i + '">';
                html += '</div>';
            }
            html += '</div></div>';
        });
        html += '</div>';

        // ===== 第二区: 两两组合 (百十/百个/十个) =====
        var comboPairs = [
            { key: 'baishi', name: '百十' },
            { key: 'baige', name: '百个' },
            { key: 'shige', name: '十个' }
        ];
        var comboAttrs = [
            { k: 'hedan', n: '和单' },
            { k: 'heshuang', n: '和双' },
            { k: 'heweida', n: '尾大' },
            { k: 'heweixiao', n: '尾小' }
        ];

        comboPairs.forEach(function(pair) {
            html += '<div class="sm-combo-section">';
            html += '<div class="sm-combo-title">' + pair.name + '</div>';
            html += '<div class="sm-combo-grid">';
            comboAttrs.forEach(function(item) {
                var dbKey = pair.key + '_' + item.k;
                var o = getItemOdds('shuangmian_combo', dbKey, odds.da);
                html += '<div class="sm-combo-item" data-key="' + dbKey + '" data-odds="' + o + '">';
                html += '<span class="sm-row-name">' + item.n + '</span>';
                html += '<span class="sm-row-odds">' + fmtOdds(o) + '</span>';
                html += '<input type="number" class="row-amount" placeholder="" min="0" step="1" data-key="' + dbKey + '">';
                html += '</div>';
            });
            html += '</div></div>';
        });

        // ===== 第三区: 百十个总和 =====
        var totalAttrs = [
            { k: 'heda', n: '和大' },
            { k: 'hedan', n: '和单' },
            { k: 'heweida', n: '和尾大' },
            { k: 'hexiao', n: '和小' },
            { k: 'heshuang', n: '和双' },
            { k: 'heweixiao', n: '和尾小' }
        ];

        html += '<div class="sm-combo-section">';
        html += '<div class="sm-combo-title">百十个</div>';
        html += '<div class="sm-combo-grid sm-combo-grid-4">';
        totalAttrs.forEach(function(item) {
            var dbKey = 'zonghe_' + item.k;
            var o = getItemOdds('shuangmian_total', dbKey, getItemOdds('shuangmian', dbKey, odds.da));
            html += '<div class="sm-combo-item" data-key="' + dbKey + '" data-odds="' + o + '">';
            html += '<span class="sm-row-name">' + item.n + '</span>';
            html += '<span class="sm-row-odds">' + fmtOdds(o) + '</span>';
            html += '<input type="number" class="row-amount" placeholder="" min="0" step="1" data-key="' + dbKey + '">';
            html += '</div>';
        });
        html += '</div></div>';

        return html;
    }

    /* ===============================================================
       通用渲染组件
       =============================================================== */

    // 大小单双质合 (参考站: 大/小/单/双 一行4列, 质/合 一行2列)
    function renderDxdszh(playType) {
        var pt = playType || 'shuangmian';
        var html = '<div class="bet-row-grid bet-row-grid-4">';
        html += betRow('da','大',getItemOdds(pt, [pt + '_da', 'da'], ODDS.da)) + betRow('xiao','小',getItemOdds(pt, [pt + '_xiao', 'xiao'], ODDS.xiao));
        html += betRow('dan','单',getItemOdds(pt, [pt + '_dan', 'dan'], ODDS.dan)) + betRow('shuang','双',getItemOdds(pt, [pt + '_shuang', 'shuang'], ODDS.shuang));
        html += '</div>';
        return html;
    }

    // 大小单双 (参考站: 4列一行)
    function renderDxds(prefix, playType) {
        prefix = prefix || '';
        var pt = playType || 'shuangmian';
        var html = '<div class="bet-row-grid bet-row-grid-4">';
        html += betRow(prefix+'da','大',getItemOdds(pt, [pt + '_da', 'da'], ODDS.da)) + betRow(prefix+'xiao','小',getItemOdds(pt, [pt + '_xiao', 'xiao'], ODDS.xiao));
        html += betRow(prefix+'dan','单',getItemOdds(pt, [pt + '_dan', 'dan'], ODDS.dan)) + betRow(prefix+'shuang','双',getItemOdds(pt, [pt + '_shuang', 'shuang'], ODDS.shuang));
        html += '</div>';
        return html;
    }

    // 号码 0-9
    function renderNumbers09(customOdds, playType) {
        var html = '<div class="bet-row-grid bet-row-grid-5">';
        for (var i = 0; i <= 9; i++) {
            var o = playType ? getItemOdds(playType, ['num_'+i, ''+i], customOdds || ODDS.number) : (customOdds || ODDS.number);
            html += betRow('num_'+i, i, o);
            if (i === 4) html += '</div><div class="bet-row-grid bet-row-grid-5">';
        }
        return html + '</div>';
    }

    // 位置选号器
    function renderPositionSelector(count) {
        var labels;
        if (count === 3) {
            labels = ['百位','十位','个位'];
        } else {
            if (state.subTab==='baishi') labels = ['百位','十位'];
            else if (state.subTab==='baige') labels = ['百位','个位'];
            else labels = ['十位','个位'];
        }

        var html = '<div class="combo-input-area">';
        if (count === 3) {
            html += '<div class="quick-tools">';
            [['all','全选'],['da','大'],['xiao','小'],['dan','单'],['shuang','双'],['clear','清除']].forEach(function(a) {
                html += '<div class="quick-tool-btn" data-action="'+a[0]+'">'+a[1]+'</div>';
            });
            html += '</div>';
        }
        labels.forEach(function(pos, idx) {
            html += '<div class="combo-row"><div class="combo-position"><div class="combo-position-label">'+pos+'</div></div><div class="combo-numbers">';
            for (var i = 0; i <= 9; i++) html += '<div class="combo-num-btn" data-pos="'+idx+'" data-num="'+i+'">'+i+'</div>';
            html += '</div></div>';
        });
        return html + '</div>';
    }

    // 单式输入
    function renderDanshiInput() {
        return '<div class="direct-input-area">' +
            '<textarea class="direct-input-field" id="danshiInput" rows="5" placeholder="请输入3位数号码，如 123,456,789，多注用逗号或换行分隔" style="resize:vertical;"></textarea>' +
            '<div style="display:flex;gap:8px;margin-top:12px;">' +
            '<div class="quick-tool-btn" onclick="BetPage.parseDanshi()">解析号码</div>' +
            '<div class="quick-tool-btn" onclick="document.getElementById(\'danshiInput\').value=\'\'">清空</div>' +
            '</div></div>';
    }

    // 和值 0-27 (三字和数) - 含和数 + 和数尾数
    function renderHezhi() {
        // 默认赔率数据
        var hzDefaults = {
            '0-6': 11.726, 7: 27.361, 8: 21.888, 9: 17.909, 10: 15.634,
            11: 14.275, 12: 13.493, 13: 13.133, 14: 13.133, 15: 13.493,
            16: 14.275, 17: 15.634, 18: 17.909, 19: 21.888, 20: 27.361,
            '21-27': 11.726
        };
        var html = '<h4 style="font-size:14px;color:var(--text-secondary);margin-bottom:12px;">三字和数</h4>';
        html += '<div class="bet-row-grid bet-row-grid-5">';
        var keys = ['0-6',7,8,9,10,11,12,13,14,15,16,17,18,19,20,'21-27'];
        keys.forEach(function(k, i) {
            var bk = (k === '0-6') ? 0 : (k === '21-27' ? 21 : k);
            var o = getItemOdds('hezhi', 'hz_' + bk, hzDefaults[k]);
            html += betRow('hz_'+k, k, o);
            if (i === 4 || i === 9 || i === 14) html += '</div><div class="bet-row-grid bet-row-grid-5">';
        });
        html += '</div>';
        // 和数尾数
        html += '<h4 style="font-size:14px;color:var(--text-secondary);margin:20px 0 12px;">三字和数尾数</h4>';
        html += '<div class="bet-row-grid bet-row-grid-5">';
        for (var i = 0; i <= 9; i++) {
            var wsO = getItemOdds('hezhi_ws', 'hzw_' + i, 9.85);
            html += betRow('hzws_'+i, i, wsO);
            if (i === 4) html += '</div><div class="bet-row-grid bet-row-grid-5">';
        }
        html += '</div>';
        return html;
    }

    // 二字和数 0-18 - 含和数 + 和数尾数
    function renderErziHeshu() {
        // 默认赔率数据
        var ehzDefaults = {
            '0-4': 6.566, 5: 16.416, 6: 14.071, 7: 12.312, 8: 10.944,
            9: 9.85, 10: 10.944, 11: 12.312, 12: 14.071, 13: 16.416,
            '14-18': 6.566
        };
        // 子标签标题 + 对应数据库play_type
        var subLabels = { baishi: '百十', baige: '百个', shige: '十个' };
        var subDbKeys = { baishi: 'erzi_heshu_baishi', baige: 'erzi_heshu_baige', shige: 'erzi_heshu_shige' };
        var subDbWsKeys = { baishi: 'erzi_heshu_baishi_ws', baige: 'erzi_heshu_baige_ws', shige: 'erzi_heshu_shige_ws' };
        var subLabel = subLabels[state.subTab] || '百十';
        var dbKey = subDbKeys[state.subTab] || 'erzi_heshu_baishi';
        var dbWsKey = subDbWsKeys[state.subTab] || 'erzi_heshu_baishi_ws';

        var html = '<h4 style="font-size:14px;color:var(--text-secondary);margin-bottom:12px;">' + subLabel + '和数</h4>';
        html += '<div class="bet-row-grid bet-row-grid-5">';
        var keys = ['0-4',5,6,7,8,9,10,11,12,13,'14-18'];
        keys.forEach(function(k, i) {
            var bk = (k === '0-4') ? 0 : (k === '14-18' ? 14 : k);
            var o = getItemOdds(dbKey, 'ehs_' + state.subTab + '_' + bk, ehzDefaults[k]);
            html += betRow('ehz_'+k, k, o);
            if (i === 4 || i === 9) html += '</div><div class="bet-row-grid bet-row-grid-5">';
        });
        html += '</div>';
        // 和数尾数
        html += '<h4 style="font-size:14px;color:var(--text-secondary);margin:20px 0 12px;">' + subLabel + '和数尾数</h4>';
        html += '<div class="bet-row-grid bet-row-grid-5">';
        for (var i = 0; i <= 9; i++) {
            var wsO = getItemOdds(dbWsKey, 'ehsw_' + state.subTab + '_' + i, 9.85);
            html += betRow('ehzws_'+i, i, wsO);
            if (i === 4) html += '</div><div class="bet-row-grid bet-row-grid-5">';
        }
        html += '</div>';
        return html;
    }

    // 通用投注选项(旧版，保留兼容)
    function betOption(key, name, odds) {
        return '<div class="bet-option" data-key="'+key+'" data-odds="'+odds+'">' +
            '<span class="option-name">'+name+'</span><span class="option-odds">'+fmtOdds(odds)+'</span></div>';
    }

    // 行式投注选项(参考站样式: 选项名 + 赔率 + 金额输入框)
    function betRow(key, name, odds) {
        return '<div class="bet-row-item" data-key="'+key+'" data-odds="'+odds+'">' +
            '<span class="row-name">'+name+'</span>' +
            '<span class="row-odds">'+fmtOdds(odds)+'</span>' +
            '<input type="number" class="row-amount" placeholder="" min="0" step="1" data-key="'+key+'">' +
            '</div>';
    }

    // 二字组合渲染：55个两位组合(00-99)，赔率从ODDS_MAP逐项获取
    function renderErziZuhe() {
        var combos = [];
        for (var i = 0; i <= 9; i++) {
            for (var j = i; j <= 9; j++) {
                var num = '' + i + j;
                var isDui = (i === j);
                var defaultOdds = isDui ? (ODDS.erzi_zuhe_dui || 35.178) : (ODDS.erzi_zuhe || 18.24);
                // 尝试从ODDS_MAP取：key格式可能是 ez_00 或 00
                var itemOdds = getItemOdds('erzi_zuhe', 'ez_' + num,
                               getItemOdds('erzi_zuhe', num, defaultOdds));
                combos.push({ num: num, odds: itemOdds });
            }
        }
        var html = '<div class="bet-row-grid bet-row-grid-combo">';
        combos.forEach(function(c) {
            html += betRow(c.num, c.num, c.odds);
        });
        html += '</div>';
        return html;
    }

    // 三字组合渲染：220个三位组合(000-999 sorted)，赔率从ODDS_MAP逐项获取
    function renderSanziZuhe() {
        var combos = [];
        for (var i = 0; i <= 9; i++) {
            for (var j = i; j <= 9; j++) {
                for (var k = j; k <= 9; k++) {
                    var num = '' + i + j + k;
                    var defaultOdds;
                    if (i === j && j === k) {
                        defaultOdds = ODDS.sanzi_zuhe_bao || 985;
                    } else if (i === j || j === k) {
                        defaultOdds = ODDS.sanzi_zuhe_dui || 328.33;
                    } else {
                        defaultOdds = ODDS.sanzi_zuhe || 164.166;
                    }
                    var itemOdds = getItemOdds('sanzi_zuhe', 'sz_' + num,
                                   getItemOdds('sanzi_zuhe', num, defaultOdds));
                    combos.push({ num: num, odds: itemOdds });
                }
            }
        }
        var html = '<div class="bet-row-grid bet-row-grid-combo">';
        combos.forEach(function(c) {
            html += betRow(c.num, c.num, c.odds);
        });
        html += '</div>';
        return html;
    }

    // 组三渲染：重号 + 不重号 双行选号
    function renderZusan() {
        var html = '<p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">每个位置至少选择1项</p>';
        html += '<div class="combo-input-area">';
        // 重号行
        html += '<div class="combo-row"><div class="combo-position"><div class="combo-position-label">重号</div></div><div class="combo-numbers">';
        for (var i = 0; i <= 9; i++) html += '<div class="combo-num-btn" data-pos="0" data-num="' + i + '">' + i + '</div>';
        html += '</div></div>';
        // 不重号行
        html += '<div class="combo-row"><div class="combo-position"><div class="combo-position-label">不重号</div></div><div class="combo-numbers">';
        for (var i = 0; i <= 9; i++) html += '<div class="combo-num-btn" data-pos="1" data-num="' + i + '">' + i + '</div>';
        html += '</div></div>';
        html += '</div>';
        return html;
    }

    // 跨度渲染：0-9各值精确赔率
    // 跨度渲染：0-9各值从数据库获取赔率
    function renderKuadu() {
        var kdDefaults = [98.5, 18.24, 10.26, 7.817, 6.84, 6.566, 6.84, 7.817, 10.26, 18.24];
        var html = '<div class="bet-row-grid bet-row-grid-5">';
        for (var i = 0; i <= 9; i++) {
            var o = getItemOdds('kuadu', ['kd_'+i, ''+i], kdDefaults[i]);
            html += betRow('kd_' + i, i, o);
            if (i === 4) html += '</div><div class="bet-row-grid bet-row-grid-5">';
        }
        html += '</div>';
        return html;
    }

    /* ===============================================================
       事件绑定
       =============================================================== */
    function bindBetOptions(c) {
        c.querySelectorAll('.bet-option').forEach(function(e){e.addEventListener('click',function(){e.classList.toggle('selected');updateSel();});});
        c.querySelectorAll('.bet-number-item').forEach(function(e){e.addEventListener('click',function(){e.classList.toggle('selected');updateSel();});});
        c.querySelectorAll('.sum-bet-item').forEach(function(e){e.addEventListener('click',function(){e.classList.toggle('selected');updateSel();});});
        c.querySelectorAll('.combo-num-btn').forEach(function(e){e.addEventListener('click',function(){e.classList.toggle('selected');updateSel();});});

        // 双面盘互斥对映射（后缀 → 对立后缀）
        var exclusionPairs = {
            '_da':'_xiao', '_xiao':'_da',
            '_dan':'_shuang', '_shuang':'_dan',
            '_zhi':'_he', '_he':'_zhi',
            '_heda':'_hexiao', '_hexiao':'_heda',
            '_hedan':'_heshuang', '_heshuang':'_hedan',
            '_heweida':'_heweixiao', '_heweixiao':'_heweida',
            '_heweizhi':'_heweihe', '_heweihe':'_heweizhi'
        };

        var exactExclusions = {
            'zhda': ['zhxiao'], 'zhxiao': ['zhda'],
            'zhdan': ['zhshuang'], 'zhshuang': ['zhdan'],
            'long': ['hu', 'longhu_he'], 'hu': ['long', 'longhu_he'], 'longhu_he': ['long', 'hu']
        };

        function clearOpposite(key) {
            if (!key) return;
            
            // 1. 处理精确匹配的互斥（如 zhda, zhxiao, 龙虎和）
            if (exactExclusions[key]) {
                exactExclusions[key].forEach(function(oppKey) {
                    var oppRow = c.querySelector('[data-key="' + oppKey + '"]');
                    if (oppRow) {
                        var oppInp = oppRow.querySelector('.row-amount');
                        if (oppInp && parseInt(oppInp.value) > 0) {
                            oppInp.value = '';
                            oppRow.classList.remove('selected');
                        }
                    }
                });
            }

            // 2. 处理带前缀的互斥（如 b1_da, b1_xiao）
            var suffixes = Object.keys(exclusionPairs);
            for (var i = 0; i < suffixes.length; i++) {
                var suf = suffixes[i];
                if (key.length > suf.length && key.substring(key.length - suf.length) === suf) {
                    var prefix = key.substring(0, key.length - suf.length);
                    var oppositeKey = prefix + exclusionPairs[suf];
                    var oppositeRow = c.querySelector('[data-key="' + oppositeKey + '"]');
                    if (oppositeRow) {
                        var oppositeInp = oppositeRow.querySelector('.row-amount');
                        if (oppositeInp && parseInt(oppositeInp.value) > 0) {
                            oppositeInp.value = '';
                            oppositeRow.classList.remove('selected');
                        }
                    }
                    break;
                }
            }
        }

        // 行式输入框事件 — 输入金额时自动标记选中（含双面全面板）
        c.querySelectorAll('.row-amount').forEach(function(inp){
            inp.addEventListener('input', function(){
                var row = inp.closest('.bet-row-item, .sm-row-item, .sm-combo-item');
                if (row) {
                    if (parseInt(inp.value) > 0) {
                        row.classList.add('selected');
                        clearOpposite(row.dataset.key);
                    } else {
                        row.classList.remove('selected');
                    }
                }
                updateSel();
            });
            inp.addEventListener('focus', function(){ inp.select(); });
        });

        // 行式：点击整行自动填入筹码金额（点击即下注）
        c.querySelectorAll('.bet-row-item, .sm-row-item, .sm-combo-item').forEach(function(row){
            row.addEventListener('click', function(e) {
                // 如果点击的是输入框则不处理
                if (e.target.classList.contains('row-amount')) return;
                var inp = row.querySelector('.row-amount');
                if (!inp) return;
                if (parseInt(inp.value) > 0) {
                    // 已有金额，清零
                    inp.value = '';
                    row.classList.remove('selected');
                } else {
                    // 填入当前筹码金额
                    inp.value = state.betAmount;
                    row.classList.add('selected');
                    clearOpposite(row.dataset.key);
                }
                updateSel();
            });
        });

        c.querySelectorAll('.quick-tool-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var a = btn.dataset.action; if (!a) return;
                c.querySelectorAll('.combo-num-btn').forEach(function(cb) {
                    var n = parseInt(cb.dataset.num);
                    switch(a) {
                        case 'all': cb.classList.add('selected'); break;
                        case 'da': n>=5?cb.classList.add('selected'):cb.classList.remove('selected'); break;
                        case 'xiao': n<=4?cb.classList.add('selected'):cb.classList.remove('selected'); break;
                        case 'dan': n%2===1?cb.classList.add('selected'):cb.classList.remove('selected'); break;
                        case 'shuang': n%2===0?cb.classList.add('selected'):cb.classList.remove('selected'); break;
                        case 'clear': cb.classList.remove('selected'); break;
                    }
                });
                updateSel();
            });
        });
    }

    function updateSel() {
        state.selectedBets = [];
        var area = document.getElementById('betArea');

        // 旧版点击选中模式
        area.querySelectorAll('.bet-option.selected, .bet-number-item.selected, .sum-bet-item.selected').forEach(function(e) {
            var k = e.dataset.key || e.dataset.num || '';
            // 自动对组合玩法加上对应的数据库前缀键，防止赔率覆盖失效
            if (state.playType === 'yizi_zuhe' && k.indexOf('yz_') !== 0) {
                k = 'yz_' + k.replace(/^num_/, '');
            } else if (state.playType === 'erzi_zuhe' && k.indexOf('ez_') !== 0) {
                k = 'ez_' + k.replace(/^num_/, '');
            } else if (state.playType === 'sanzi_zuhe' && k.indexOf('sz_') !== 0) {
                k = 'sz_' + k.replace(/^num_/, '');
            }
            state.selectedBets.push({key: k, odds: parseFloat(e.dataset.odds) || 1});
        });

        // 新版行式输入框模式（含双面全面板的sm-row-item/sm-combo-item）
        var posMultiplier = 1;
        if (state.playType === 'kuaijie' && state.subTabs && state.subTabs.length > 1) {
            posMultiplier = state.subTabs.length;
        }
        area.querySelectorAll('.bet-row-item, .sm-row-item, .sm-combo-item').forEach(function(row){
            var inp = row.querySelector('.row-amount');
            var amt = inp ? parseInt(inp.value) : 0;
            if (amt > 0) {
                var rk = row.dataset.key || '';
                // 组合玩法key前缀映射
                if (state.playType === 'yizi_zuhe' && rk.indexOf('yz_') !== 0) {
                    rk = 'yz_' + rk.replace(/^num_/, '');
                } else if (state.playType === 'erzi_zuhe' && rk.indexOf('ez_') !== 0) {
                    rk = 'ez_' + rk.replace(/^num_/, '');
                } else if (state.playType === 'sanzi_zuhe' && rk.indexOf('sz_') !== 0) {
                    rk = 'sz_' + rk.replace(/^num_/, '');
                }
                for (var pi = 0; pi < posMultiplier; pi++) {
                    var betItem = {
                        key: rk,
                        odds: parseFloat(row.dataset.odds) || 1,
                        amount: amt
                    };
                    // 快捷多选位置时，标记每个注对应的位置
                    if (posMultiplier > 1 && state.subTabs && state.subTabs[pi]) {
                        betItem.sub = state.subTabs[pi];
                    }
                    state.selectedBets.push(betItem);
                }
            }
        });

        // combo选号器
        area.querySelectorAll('.combo-num-btn.selected').forEach(function(e){
            state.selectedBets.push({
                key: e.dataset.num || '',
                odds: 1,
                pos: e.dataset.pos !== undefined ? e.dataset.pos : ''
            });
        });

        updateBetCount();
        if (typeof updateGlobalOddsDisplay === 'function') updateGlobalOddsDisplay();
    }

    /* 组合数 C(n, r) */
    function comb(n, r) {
        if (n < r || r < 0) return 0;
        if (r === 0 || r === n) return 1;
        var result = 1;
        for (var i = 0; i < r; i++) { result = result * (n - i) / (i + 1); }
        return Math.round(result);
    }

    function getSubsets(arr, k) {
        var result = [];
        if (!arr || arr.length < k) return result;
        function backtrack(start, current) {
            if (current.length === k) {
                result.push(current.slice());
                return;
            }
            for (var i = start; i < arr.length; i++) {
                current.push(arr[i]);
                backtrack(i + 1, current);
                current.pop();
            }
        }
        backtrack(0, []);
        return result;
    }

    function calcZxRows(A, B, pickA, pickB) {
        if (!A || !B || A.length < pickA || B.length < pickB) return 0;
        var total = 0;
        var subA = getSubsets(A, pickA);
        subA.forEach(function(subset) {
            var validB = B.filter(function(x) { return subset.indexOf(x) === -1; });
            total += comb(validB.length, pickB);
        });
        return total;
    }

    /* 真实注数计算（标准盘+双面盘通用） */
    function calcRealBetCount() {
        // 行式输入模式
        var hasAmt = state.selectedBets.some(function(b){ return b.amount > 0; });
        if (hasAmt) {
            var sum = 0;
            state.selectedBets.forEach(function(b){ sum += (b.amount || 0); });
            return sum;
        }

        // === 标准盘 ===
        if (state.panelType === 'biaozhun') {
            var play = findBzpPlay(state.playType);
            if (!play) return state.selectedBets.length;

            // 按位分组统计并记录具体选项
            var posElements = {};
            state.selectedBets.forEach(function(b) {
                if (b.pos !== undefined) {
                    if (!posElements[b.pos]) posElements[b.pos] = [];
                    posElements[b.pos].push(b.num); // 存数字本身，用于跨行互斥比较
                }
            });
            var counts = [];
            var posArrays = [];
            Object.keys(posElements).sort().forEach(function(k){ 
                posArrays.push(posElements[k]);
                counts.push(posElements[k].length); 
            });

            switch (play.ui) {
                case 'row2_zx60': return calcZxRows(posArrays[0], posArrays[1], 1, 3);
                case 'row2_zx30': return calcZxRows(posArrays[0], posArrays[1], 2, 1);
                case 'row2_zx20': return calcZxRows(posArrays[0], posArrays[1], 1, 2);
                case 'row2_zx10': return calcZxRows(posArrays[0], posArrays[1], 1, 1);
                case 'row2_zx5':  return calcZxRows(posArrays[0], posArrays[1], 1, 1);
                case 'row2_zx12': return calcZxRows(posArrays[0], posArrays[1], 1, 2);
                case 'row2_zx4':  return calcZxRows(posArrays[0], posArrays[1], 1, 1);
                case 'row5':
                    if (play.key === 'dingweidan') {
                        var sum = 0;
                        for (var i = 0; i < counts.length; i++) sum += counts[i];
                        return sum;
                    }
                    if (counts.length < 5) return 0;
                    var baseCount = counts[0] * counts[1] * counts[2] * counts[3] * counts[4];
                    if (play.key === 'wx_zx_zuhe') return baseCount * 5;
                    return baseCount;
                case 'row4':
                case 'row4_q4':
                    if (counts.length < 4) return 0;
                    var base4 = counts[0] * counts[1] * counts[2] * counts[3];
                    if (play.key.indexOf('_zx_zuhe') > -1) return base4 * 4; // 四星直选组合×4
                    return base4;
                case 'row5_pick2':
                case 'row5_pick3':
                case 'row5_pick4': {
                    // 任选k直选复式：从选中的位置中任取k个，各取一号，求所有组合之积的总和
                    var pickK = play.ui === 'row5_pick2' ? 2 : (play.ui === 'row5_pick3' ? 3 : 4);
                    var rxTotal = 0;
                    function rxBacktrack(start, remaining, prod) {
                        if (remaining === 0) { rxTotal += prod; return; }
                        for (var ri = start; ri < counts.length; ri++) {
                            if (counts[ri] > 0) rxBacktrack(ri + 1, remaining - 1, prod * counts[ri]);
                        }
                    }
                    rxBacktrack(0, pickK, 1);
                    return rxTotal;
                }
                case 'row3':
                case 'row3_q3':
                case 'row3_z3':
                case 'row3_h3':
                    // 定位胆：各位独立投注，注数=各位相加 (5+5+5=15)
                    if (play.key === 'dingweidan') {
                        var sum = 0;
                        for (var i = 0; i < counts.length; i++) sum += counts[i];
                        return sum;
                    }
                    // 三星直选复式等：各位相乘 (4×4×4=64)
                    if (counts.length < 3) return 0;
                    return counts[0] * counts[1] * counts[2];
                case 'row2_qe':
                case 'row2_he':
                    // 二位选号：各位相乘 (4×4=16)
                    if (counts.length < 2) return 0;
                    return counts[0] * counts[1];
                case 'row1':
                    // 单行选号
                    var n = counts.length > 0 ? counts[0] : state.selectedBets.length;
                    if (play.key.indexOf('baodan') > -1) {
                        if (play.key.indexOf('sx_') > -1 || play.key.indexOf('q3_') > -1 || play.key.indexOf('z3_') > -1 || play.key.indexOf('h3_') > -1) {
                            return n * 54; // 三星包胆：包含该数字的组三(18)+组六(36) = 54注
                        } else {
                            return n * 9; // 二星包胆：包含该数字的二星组选 = 9注
                        }
                    }
                    // 不定胆：C(n,k) 组合数
                    if (play.key.indexOf('mabuding') > -1) {
                        if (play.key.indexOf('sanma') > -1) return comb(n, 3); // 三码: C(n,3)
                        if (play.key.indexOf('erma')  > -1) return comb(n, 2); // 二码: C(n,2)
                        return n;                                                // 一码: n
                    }
                    if (play.key.indexOf('zx120') > -1) return comb(n, 5); // 五星组选120: C(n,5)
                    if (play.key.indexOf('zx24') > -1) return comb(n, 4); // 四星组选24/任选四组选24: C(n,4)
                    if (play.key.indexOf('zx3') > -1) return n * (n - 1);  // 组选三: n×(n-1)，每对可产生aab和abb
                    if (play.key.indexOf('zx6') > -1) {
                        if (play.key.indexOf('sx4_') > -1 || play.key.indexOf('rx4_') > -1) return comb(n, 2); // 四星组选6: 选2个做2个二重号 C(n,2)
                        return comb(n, 3);  // 三星组选六: C(n,3)
                    }
                    if (play.key.indexOf('zuxuan_fushi') > -1) return comb(n, 2); // 组选复式: C(n,2)
                    return n;
                default:
                    // 和值/跨度/尾数：每个选项对应不同注数，需查表累加
                    var HZ27_ZX = [1,3,6,10,15,21,28,36,45,55,63,69,73,75,75,73,69,63,55,45,36,28,21,15,10,6,3,1]; // 三星直选和值 sum=1000
                    var HZ27_ZUX = [0,1,2,2,4,5,6,8,10,11,13,14,14,15,15,14,14,13,11,10,8,6,5,4,2,2,1,0]; // 三星组选和值 sum=210
                    var HZ18 = [1,2,3,4,5,6,7,8,9,10,9,8,7,6,5,4,3,2,1]; // 二星直选和值 sum=100
                    var HZ18_ZUX = [0,1,1,2,2,3,3,4,4,5,4,4,3,3,2,2,1,1,0]; // 二星组选和值 sum=45
                    var KD3 = [10,54,96,126,144,150,144,126,96,54]; // 三星跨度 sum=1000
                    var KD2 = [10,18,16,14,12,10,8,6,4,2]; // 二星跨度 sum=100
                    var WEISHU = [100,100,100,100,100,100,100,100,100,100]; // 和值尾数 每个100

                    var table = null;
                    if (play.ui === 'hz27') {
                        table = (play.key.indexOf('zx_hezhi2') > -1 || play.key.indexOf('zuxuan') > -1) ? HZ27_ZUX : HZ27_ZX;
                    } else if (play.ui === 'hz18') {
                        table = (play.key.indexOf('zuxuan') > -1) ? HZ18_ZUX : HZ18;
                    } else if (play.ui === 'kd9') {
                        if (play.key.indexOf('hzweishu') > -1) {
                            // 和值尾数：每选一个数字 = 1注
                            return state.selectedBets.length;
                        } else if (play.key.indexOf('sx_') > -1 || play.key.indexOf('q3_') > -1 || play.key.indexOf('z3_') > -1 || play.key.indexOf('h3_') > -1 || play.key.indexOf('rx3_') > -1) {
                            table = KD3;
                        } else {
                            table = KD2;
                        }
                    }

                    if (table) {
                        var hzTotal = 0;
                        state.selectedBets.forEach(function(b) {
                            var idx = parseInt(b.key.replace('hz_', ''));
                            if (!isNaN(idx) && idx >= 0 && idx < table.length) hzTotal += table[idx];
                        });
                        return hzTotal;
                    }
                    return state.selectedBets.length;
                case 'dxds_pos':
                case 'dxds_pos3':
                case 'dxds_pos2_q':
                case 'dxds_pos3_q':
                case 'dxds_pos2_h':
                case 'dxds_pos3_h':
                    // 大小单双：按位置分组后各位置注数相乘 (2行4×4=16, 3行4×4×4=64)
                    var dxdsPos = {};
                    state.selectedBets.forEach(function(b) {
                        var underIdx = b.key ? b.key.indexOf('_') : -1;
                        var posKey = underIdx > -1 ? b.key.substring(0, underIdx) : b.key;
                        if (!dxdsPos[posKey]) dxdsPos[posKey] = 0;
                        dxdsPos[posKey]++;
                    });
                    var dxdsCts = Object.values(dxdsPos).filter(function(v){ return v > 0; });
                    if (dxdsCts.length === 0) return 0;
                    return dxdsCts.reduce(function(a, b){ return a * b; }, 1);
            }
        }

        // === 双面盘 ===
        // 定位类玩法：按位相乘
        if (state.playType === 'sanzi_dingwei') {
            var area = document.getElementById('betArea');
            var pos = {};
            area.querySelectorAll('.combo-num-btn.selected').forEach(function(b) {
                var p = b.dataset.pos;
                if (!pos[p]) pos[p] = 0;
                pos[p]++;
            });
            var cts = []; Object.keys(pos).sort().forEach(function(k){ cts.push(pos[k]); });
            return cts.length >= 3 ? cts[0] * cts[1] * cts[2] : 0;
        }
        if (state.playType === 'erzi_dingwei') {
            var area = document.getElementById('betArea');
            var pos = {};
            area.querySelectorAll('.combo-num-btn.selected').forEach(function(b) {
                var p = b.dataset.pos;
                if (!pos[p]) pos[p] = 0;
                pos[p]++;
            });
            var cts = []; Object.keys(pos).sort().forEach(function(k){ cts.push(pos[k]); });
            return cts.length >= 2 ? cts[0] * cts[1] : 0;
        }
        // 组三: 重号数 × 不重号数 - 重叠数
        if (state.playType === 'zusan') {
            var area = document.getElementById('betArea');
            var chonghao = [], buchonghao = [];
            area.querySelectorAll('.combo-num-btn.selected').forEach(function(b) {
                var num = parseInt(b.dataset.num);
                if (b.dataset.pos === '0') chonghao.push(num);
                else buchonghao.push(num);
            });
            var overlap = 0;
            chonghao.forEach(function(n) { if (buchonghao.indexOf(n) !== -1) overlap++; });
            return chonghao.length * buchonghao.length - overlap;
        }
        // 组六: C(n,3)
        if (state.playType === 'zuliu') return comb(state.selectedBets.length, 3);

        return state.selectedBets.length;
    }

    function updateBetCount() {
        var b = document.getElementById('btnSubmitBet');
        var count = calcRealBetCount();
        var total = 0;

        if (state.panelType === 'biaozhun') {
            // 标准盘: 总金额 = 注数 × 每注单价 × 倍数
            total = count * state.bzpUnit * state.bzpMultiple;
        } else {
            var hasAmt = state.selectedBets.some(function(bet){ return bet.amount > 0; });
            if (hasAmt) {
                total = count; // 行式输入: 总金额=各行金额之和
            } else {
                total = count * state.betAmount;
            }
        }

        var exceedsSanxingZhixuanLimit = isSanxingZhixuanFushiPerBetOverLimit();
        if (b) b.disabled = count === 0 || exceedsSanxingZhixuanLimit;

        // 更新底部栏
        var countEl = document.getElementById('mBetCount');
        var totalEl = document.getElementById('mBetTotal');
        if (state.panelType === 'biaozhun') {
            if (countEl) countEl.textContent = '';
            if (totalEl) {
                totalEl.textContent = count > 0 ? '您选择了' + count + '注，共计' + total.toFixed(4).replace(/\.?0+$/, '') + '元' + (exceedsSanxingZhixuanLimit ? '（每注不能超过500元）' : '') : '';
                totalEl.style.color = exceedsSanxingZhixuanLimit ? '#e53935' : '';
            }
        } else {
            if (countEl) countEl.textContent = count + ' 单';
            if (totalEl) {
                totalEl.textContent = total.toFixed(4).replace(/\.?0+$/, '') + '元';
                totalEl.style.color = '';
            }
        }

        // 余额 = 当前余额 - 本次消费
        var balEl = document.getElementById('betBalanceShow');
        if (balEl) {
            var cnyTotal = (typeof LotteryAuth !== 'undefined') ? LotteryAuth.toCny(total) : total;
            var remain = state.userBalance - cnyTotal;
            balEl.textContent = remain < 0 ? '0.00' : remain.toFixed(2);
            balEl.style.color = remain < 0 ? '#e53935' : 'var(--danger)';
        }

        // 更新投注按钮文字
        if (b && exceedsSanxingZhixuanLimit) {
            b.textContent = '每注不能超过500元';
        } else if (b && count > 0) {
            b.textContent = '投注(' + count + '注)';
        } else if (b) {
            b.textContent = '投注';
        }

        updateGlobalOddsDisplay();
    }

    function isSanxingZhixuanFushiPerBetOverLimit() {
        return state.panelType === 'biaozhun' &&
            state.playType === 'sx_zx_fushi' &&
            state.bzpUnit * state.bzpMultiple > 500;
    }

    /* ===============================================================
       金额 & 操作
       =============================================================== */
    function initAmountChips() {
        document.querySelectorAll('.amount-chip, .m-chip').forEach(function(ch){
            ch.addEventListener('click',function(){
                document.querySelectorAll('.amount-chip, .m-chip').forEach(function(c){c.classList.remove('active');});
                ch.classList.add('active');
                state.betAmount = parseInt(ch.dataset.amount);
                var ci = document.getElementById('customAmountInput'); if(ci) ci.style.display='none';
                // 把所有已选行的金额统一更新为新筹码金额
                var area = document.getElementById('betArea');
                if (area) {
                    area.querySelectorAll('.bet-row-item.selected, .sm-row-item.selected, .sm-combo-item.selected').forEach(function(row){
                        var inp = row.querySelector('.row-amount');
                        if (inp) inp.value = state.betAmount;
                    });
                }
                updateSel();
            });
        });
        var eb=document.getElementById('amountEditBtn');
        if(eb) eb.addEventListener('click',function(){
            var ci=document.getElementById('customAmountInput');
            if(ci){ci.style.display=ci.style.display==='none'?'inline-block':'none';if(ci.style.display!=='none')ci.focus();}
        });
        var ci=document.getElementById('customAmountInput');
        if(ci) ci.addEventListener('change',function(){
            var v=parseInt(ci.value);
            if(v>0){
                state.betAmount=v;
                document.querySelectorAll('.amount-chip, .m-chip').forEach(function(c){c.classList.remove('active');});
                // 同步更新已选行
                var area = document.getElementById('betArea');
                if (area) {
                    area.querySelectorAll('.bet-row-item.selected, .sm-row-item.selected, .sm-combo-item.selected').forEach(function(row){
                        var inp = row.querySelector('.row-amount');
                        if (inp) inp.value = v;
                    });
                }
                updateSel();
            }
        });
    }

    function initBottomActions() {
        var sb=document.getElementById('btnSubmitBet');
        if(sb)sb.addEventListener('click',function(){if(state.selectedBets.length===0){showToast('请先选择投注内容','warning');return;}showConfirm();});
        var rb=document.getElementById('btnRandomBet');
        if(rb)rb.addEventListener('click',randomSelect);
        var cb=document.getElementById('btnConfirmBet');
        if(cb)cb.addEventListener('click',submitBet);
        var xb=document.getElementById('btnCancelBet');
        if(xb)xb.addEventListener('click',hideConfirm);
    }

    function randomSelect() {
        var c=document.getElementById('betArea');
        c.querySelectorAll('.selected').forEach(function(e){e.classList.remove('selected');});
        var items=c.querySelectorAll('.bet-option,.bet-number-item,.sum-bet-item');
        if(!items.length)items=c.querySelectorAll('.combo-num-btn');
        var n=Math.min(3,items.length),idx=[];
        while(idx.length<n){var i=Math.floor(Math.random()*items.length);if(idx.indexOf(i)===-1)idx.push(i);}
        idx.forEach(function(i){items[i].classList.add('selected');});
        updateSel();
        showToast('已随机选择 '+n+' 注','success');
    }

    function showConfirm() {
        // 登录检查
        if (typeof LotteryAuth !== 'undefined' && !LotteryAuth.isLoggedIn()) {
            if (confirm('您尚未登录\uff0c是否前往登录页面\uff1f')) {
                window.location.href = '/index.php/index/lottery/login';
            }
            return;
        }
        var m=document.getElementById('confirmModal'),c=document.getElementById('confirmContent');
        // 计算注数和总金额（使用统一计算函数）
        var betCount = calcRealBetCount();
        var total = 0;
        if (state.panelType === 'biaozhun') {
            total = betCount * state.bzpUnit * state.bzpMultiple;
        } else {
            var hasAmt = state.selectedBets.some(function(b){ return b.amount > 0; });
            if (hasAmt) {
                total = betCount;
            } else {
                total = betCount * state.betAmount;
            }
        }
        if (isSanxingZhixuanFushiPerBetOverLimit()) {
            showToast('三星直选复式每注金额不能超过500元', 'error');
            return;
        }
        var name=state.lotteryType==='fc3d'?'福彩3D':'排列三';
        
        // 玩法中文名
        var playNameMap = {};
        var allPlays = (state.lotteryType === 'fc3d' ? FC3D_PLAYS : PL3_PLAYS);
        Object.keys(allPlays).forEach(function(panel) {
            allPlays[panel].forEach(function(p) { playNameMap[p.key] = p.name; });
        });
        var playLabel = playNameMap[state.playType] || state.playType;

        var dTotal = '¥ ' + total.toFixed(2);

        c.innerHTML='<div style="font-size:14px;line-height:2;">'+
            '<p><strong>彩种:</strong> '+name+'</p>'+
            '<p><strong>玩法:</strong> '+playLabel+'</p>'+
            '<p><strong>注数:</strong> <span style="color:var(--danger);font-weight:700;">'+betCount+'</span></p>'+
            '<p><strong>总金额:</strong> <span style="color:var(--danger);font-weight:700;font-size:18px;">'+dTotal+'</span></p></div>';
        m.classList.add('show');
    }

    function hideConfirm(){document.getElementById('confirmModal').classList.remove('show');}

    function submitBet() {
        hideConfirm();

        if (isSanxingZhixuanFushiPerBetOverLimit()) {
            showToast('三星直选复式每注金额不能超过500元', 'error');
            return;
        }

        // 获取当前期号
        var period = apiData.next ? apiData.next.period : '';
        if (!period) {
            showToast('当前无可投注期号', 'error');
            return;
        }

        var submitAmount = (typeof LotteryAuth !== 'undefined') ? LotteryAuth.toCny(state.betAmount) : state.betAmount;

        var postData = new FormData();
        postData.append('type', state.lotteryType);
        postData.append('period', period);
        postData.append('play_type', state.playType);
        // 快捷玩法多选位置时，传所有选中位置
        var playSub = state.subTab;
        if (state.playType === 'kuaijie' && state.subTabs && state.subTabs.length > 1) {
            playSub = state.subTabs.join(',');
        }
        postData.append('play_sub', playSub);
        postData.append('panel_type', state.panelType);
        postData.append('bets', JSON.stringify(state.selectedBets));
        postData.append('amount', submitAmount);
        if (state.panelType === 'biaozhun') {
            postData.append('bzp_unit', state.bzpUnit);
            postData.append('bzp_multiple', state.bzpMultiple);
        }

        var headers = {};
        if (typeof LotteryAuth !== 'undefined') headers['token'] = LotteryAuth.getToken();

        fetch('/index.php/api/lottery/placeBet', {
            method: 'POST',
            headers: headers,
            body: postData
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.code === 1) {
                showToast('投注成功！扣款 ¥' + parseFloat(res.data.total_amount).toFixed(2), 'success');
                // 更新余额显示
                var balEl = document.getElementById('userBalance');
                if (balEl && typeof LotteryAuth !== 'undefined') {
                    // Update UI format directly manually since fetchUserInfo is async
                    balEl.textContent = LotteryAuth.formatMoney(res.data.balance).replace(/^[^0-9]+/, ''); 
                }
                // 同步auth缓存
                if (typeof LotteryAuth !== 'undefined') LotteryAuth.fetchUserInfo(function(u){ if(u) LotteryAuth.updateUI(u); });
                // 清除选中
                document.getElementById('betArea').querySelectorAll('.selected').forEach(function(e){e.classList.remove('selected');});
                state.selectedBets = []; updateBetCount();
            } else {
                showToast(res.msg || '投注失败', 'error');
            }
        })
        .catch(function(err) {
            showToast('网络错误，请重试', 'error');
        });
    }

    function parseDanshi() {
        var inp=document.getElementById('danshiInput'); if(!inp) return;
        var nums=inp.value.trim().split(/[,，\n\r\s]+/).filter(function(n){return n.length>0;});
        var valid=nums.filter(function(n){return /^\d{3}$/.test(n);});
        if(!valid.length){showToast('请输入有效的3位数号码','error');return;}
        state.selectedBets=valid.map(function(n){return{key:n,odds:ODDS.zhixuan};});
        updateBetCount();
        showToast('成功解析 '+valid.length+' 注号码','success');
    }

    /* ===============================================================
       历史 & 走势
       =============================================================== */
    function renderHistory() {
        var body=document.getElementById('historyBody'); if(!body) return;
        var data = apiData.historyData;
        if (!data || !data.length) { body.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;padding:20px;">加载中...</td></tr>'; return; }
        var colors=state.lotteryType==='fc3d'?['purple','cyan','pink']:['blue','green','orange'];
        var html='';
        data.forEach(function(row){
            html+='<tr><td>'+row.period+'</td><td>';
            row.numbers.forEach(function(n,i){
                html+='<span class="number-ball '+colors[i]+'" style="width:22px;height:22px;font-size:11px;margin:0 1px;box-shadow:none;">'+n+'</span>';
            });
            var threshold=14;
            html+='</td><td>'+row.sum+'</td>';
            html+='<td>'+(row.sum>=threshold?'<span style="color:var(--danger);">大</span>':'<span style="color:var(--primary);">小</span>')+'</td>';
            html+='<td>'+(row.sum%2===1?'<span style="color:var(--danger);">单</span>':'<span style="color:var(--primary);">双</span>')+'</td></tr>';
        });
        body.innerHTML=html;

        // 近期开奖/近期投注 标签切换
        initViewTabs();
    }

    function initViewTabs() {
        var tabs = document.querySelectorAll('.draw-history-title .trend-tab[data-view]');
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                tabs.forEach(function(t) { t.classList.remove('active'); });
                tab.classList.add('active');
                var view = tab.dataset.view;
                var table = document.getElementById('historyTable');
                if (view === 'bet') {
                    renderBetRecords();
                } else {
                    // 恢复开奖表头
                    table.querySelector('thead').innerHTML = '<tr><th>期数</th><th>开奖号码</th><th>总和</th><th>大小</th><th>单双</th></tr>';
                    renderHistory();
                }
            });
        });
    }

    var betKeyMap = {zhi:'质',da:'大',xiao:'小',dan:'单',shuang:'双',long:'龙',hu:'虎',he:'合/和',baozi:'豹子',shunzi:'顺子',duizi:'对子',banshun:'半顺',zaliu:'杂六',zonghe_da:'总和大',zonghe_xiao:'总和小',zonghe_dan:'总和单',zonghe_shuang:'总和双'};
    function parseBetContent(raw) {
        if (!raw || raw === '-') return '-';
        try {
            var arr = typeof raw === 'string' ? JSON.parse(raw) : raw;
            if (!Array.isArray(arr)) return String(raw);
            var htmlParts = arr.map(function(item) {
                var k = item.key || item;
                var text = '';
                var isNum = false;
                if (/^num_\d$/.test(k)) { text = k.replace('num_', ''); isNum = true; }
                else if (/^\d+$/.test(k)) { text = k; isNum = true; }
                else { text = betKeyMap[k] || k; }
                
                if (isNum) {
                    var colors = ['radial-gradient(circle at 30% 30%, #60a5fa 0%, #2563eb 60%, #1e3a8a 100%)','radial-gradient(circle at 30% 30%, #f87171 0%, #dc2626 60%, #7f1d1d 100%)','radial-gradient(circle at 30% 30%, #34d399 0%, #059669 60%, #064e3b 100%)','radial-gradient(circle at 30% 30%, #fbbf24 0%, #d97706 60%, #78350f 100%)','radial-gradient(circle at 30% 30%, #c084fc 0%, #9333ea 60%, #4c1d95 100%)'];
                    var c = colors[(parseInt(text)||0) % colors.length];
                    return '<span style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;background:'+c+';color:#fff;font-weight:bold;margin:1px;box-shadow:inset -1px -1px 2px rgba(0,0,0,0.4), 1px 1px 2px rgba(0,0,0,0.3);font-size:10px;text-shadow:1px 1px 1px rgba(0,0,0,0.5);">'+text+'</span>';
                } else {
                    return '<span style="display:inline-block;padding:1px 4px;border-radius:6px;background:linear-gradient(135deg,#f1f5f9,#e2e8f0);color:#334155;margin:1px;font-size:10px;font-weight:600;border:1px solid #cbd5e1;">'+text+'</span>';
                }
            });
            return '<div style="display:flex;flex-wrap:wrap;align-items:center;gap:1px;">' + htmlParts.join('') + '</div>';
        } catch(e) {
            return String(raw).substring(0, 10);
        }
    }

    function renderBetRecords() {
        var table = document.getElementById('historyTable');
        var body = document.getElementById('historyBody');
        if (!table || !body) return;
        // 更新表头
        table.querySelector('thead').innerHTML = '<tr><th>期数</th><th style="max-width:80px">号码</th><th>金额</th><th>状态</th></tr>';
        body.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#999;padding:20px;">加载中...</td></tr>';

        if (typeof LotteryAuth === 'undefined' || !LotteryAuth.isLoggedIn()) {
            body.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#999;padding:20px;">请先登录</td></tr>';
            return;
        }

        LotteryAuth.request('/index.php/api/lottery/getBetHistory?type=' + state.lotteryType + '&limit=10', {method:'GET'}).then(function(res) {
            var list = (res.data && res.data.list) ? res.data.list : (res.data || []);
            if (res.code !== 1 || !list.length) {
                body.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#999;padding:20px;">暂无投注记录</td></tr>';
                return;
            }
            var html = '';
            var playMap = {kuaijie:'快捷',shuangmian:'双面',daxiao:'大小',danshuang:'单双',longhu:'龙虎',zonghe_daxiao:'总和大小',zonghe_danshuang:'总和单双',biaozhun:'标准',dingwei:'定位',yizi:'一字组合',erzi:'二字组合',sanzi:'三字组合',zusan:'组三',zuliu:'组六',hezhi:'和值',zhixuan:'直选',danshi:'单式'};
            list.forEach(function(r) {
                var statusText = '待开奖', statusColor = '#f59e0b';
                if (r.status == 1) { statusText = '已中奖'; statusColor = '#10b981'; }
                else if (r.status == 2) { statusText = '未中奖'; statusColor = '#999'; }
                else if (r.status == 3) { statusText = '已撤单'; statusColor = '#999'; }
                
                var betContent = parseBetContent(r.bet_content || r.bets || '');
                html += '<tr>';
                html += '<td style="font-size:11px;">' + String(r.period).substring(4) + '</td>';
                html += '<td style="font-size:11px;max-width:80px">' + betContent + '</td>';
                html += '<td style="font-size:11px;color:#ef4444;font-weight:600;">' + parseFloat(r.total_amount).toFixed(0) + '</td>';
                html += '<td style="font-size:11px;color:' + statusColor + ';">' + statusText + '</td>';
                html += '</tr>';
            });
            body.innerHTML = html;
        }).catch(function() {
            body.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#999;padding:20px;">加载失败</td></tr>';
        });
    }

    function renderTrendChart() {
        var c=document.getElementById('trendContent'); if(!c) return;
        var data = apiData.historyData;
        if (!data || !data.length) return;
        var heads=['百位','十位','个位'];
        renderTrend(c,data,heads,'daxiao');

        document.querySelectorAll('#trendTabs .trend-tab').forEach(function(tab){
            tab.addEventListener('click',function(){
                document.querySelectorAll('#trendTabs .trend-tab').forEach(function(t){t.classList.remove('active');});
                tab.classList.add('active');
                renderTrend(c,data,heads,tab.dataset.trend);
            });
        });
    }

    function renderTrend(c,data,heads,type) {
        var html='<table style="width:100%;border-collapse:collapse;font-size:11px;"><tr>';
        html+='<th style="padding:4px;border:1px solid #eee;background:#f8f8f8;">期</th>';
        heads.forEach(function(h){html+='<th style="padding:4px;border:1px solid #eee;background:#f8f8f8;">'+h+'</th>';});
        html+='</tr>';
        data.forEach(function(row){
            html+='<tr><td style="padding:3px 6px;border:1px solid #eee;text-align:center;">'+row.period+'</td>';
            row.numbers.forEach(function(n){
                var label,cls;
                if(type==='daxiao'){label=n>=5?'大':'小';cls=n>=5?'da':'xiao';}
                else{label=n%2===1?'单':'双';cls=n%2===1?'dan':'shuang';}
                html+='<td style="padding:3px 6px;border:1px solid #eee;text-align:center;"><span class="trend-dot '+cls+'" style="display:inline-flex;">'+label+'</span></td>';
            });
            html+='</tr>';
        });
        c.innerHTML=html+'</table>';
    }

    // 初始化热门/全部模式切换
    function initBzpModeToggle() {
        var wrap = document.getElementById('bzpModeWrap');
        if (!wrap) {
            // 动态创建热门/全部切换按钮（插入到playTypeTabs之前）
            var tabsEl = document.getElementById('playTypeTabs');
            if (!tabsEl) return;
            wrap = document.createElement('div');
            wrap.id = 'bzpModeWrap';
            wrap.className = 'bzp-mode-wrap';
            wrap.style.display = 'none';
            wrap.innerHTML = '<div class="bzp-mode-btn active" data-mode="remen">热门玩法</div>' +
                '<div class="bzp-mode-btn" data-mode="quanbu">全部玩法</div>';
            tabsEl.parentNode.insertBefore(wrap, tabsEl);
        }
        wrap.querySelectorAll('.bzp-mode-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                wrap.querySelectorAll('.bzp-mode-btn').forEach(function(b){b.classList.remove('active');});
                btn.classList.add('active');
                state.bzpMode = btn.dataset.mode;
                renderBzpTabs();
            });
        });
    }

    return { init: init, parseDanshi: parseDanshi, parseBzpText: parseBzpText, updateSel: updateSel, updateBetCount: updateBetCount, showPlayRule: showPlayRule };
})();
