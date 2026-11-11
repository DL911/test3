(function () {
    'use strict';
    var admin = !!document.querySelector('[data-market-admin="1"]');
    var endpoint = admin ? '/index.php/admin/lottery.agentmarket/index' : '/index.php/api/agent/marketReport';
    var page = 1, total = 0, maxPage = 1;
    function el(id) { return document.getElementById(id); }
    function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
    function money(v) { return '¥' + Number(v || 0).toFixed(2); }
    function request(url) { return admin ? fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function (r) { return r.json(); }) : LotteryAuth.request(url, {method:'GET'}); }
    var now = new Date(), start = new Date(now.getTime() - 6 * 86400000);
    function date(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
    el('mrStart').value = date(start); el('mrEnd').value = date(now);
    if (admin) {
        el('mrAgentSearch').addEventListener('input', function () {
            var v = this.value.trim(); if (!v) { el('mrAgentOptions').innerHTML = ''; return; }
            request('/index.php/admin/lottery.agentmarket/agents?keyword=' + encodeURIComponent(v)).then(function (r) {
                el('mrAgentId').innerHTML = '<option value="">选择代理</option>' + (r.data || []).map(function (a) { return '<option value="' + esc(a.id) + '">' + esc(a.username) + '（ID ' + esc(a.id) + '）</option>'; }).join('');
            });
        });
    }
    function params(p) {
        var q = new URLSearchParams({page:p, date_start:el('mrStart').value, date_end:el('mrEnd').value, direct_id:el('mrDirect').value, user_id:el('mrUserId').value, user_keyword:el('mrUserSearch').value, lottery_type:el('mrType').value});
        if (admin) { q.set('ajax','1'); q.set('agent_id',el('mrAgentId').value); }
        return q;
    }
    function row(label, value) { return '<div class="mr-stat"><small>' + label + '</small><strong>' + money(value) + '</strong></div>'; }
    function render(d) {
        var s = d.summary || {}, scope = d.scope === 'user' ? '单用户' : '代理市场';
        el('mrScope').textContent = scope + ' · ' + d.agent.username + ' · 投注日期 ' + d.date_start + ' 至 ' + d.date_end + ' · 生成于 ' + d.generated_at;
        el('mrSummary').innerHTML = row('有效投注总额',s.bet_amount) + row('实际派奖总额',s.win_amount) + row('未中奖投注额',s.lost_amount) + row('中奖订单投注额',s.winning_bet_amount) + row('待开奖投注额',s.pending_amount) + row('已结算差额（参考）',s.settled_difference) + row('有效返水',s.rebate_total) + row('待领取返水',s.rebate_pending) + row('已领取返水',s.rebate_claimed) + row('已作废返水',s.rebate_void);
        el('mrCount').textContent = '有效订单 ' + s.order_count + ' 笔；订单明细 ' + d.bet_total + ' 笔（含撤单）';
        el('mrUsers').innerHTML = (d.users || []).map(function (u) { return '<tr><td><button type="button" class="mr-link" data-user="' + esc(u.user_id) + '">' + esc(u.username) + '（' + esc(u.user_id) + '）</button></td><td>' + esc(u.order_count) + '</td><td>' + money(u.bet_amount) + '</td><td>' + money(u.win_amount) + '</td><td>' + money(u.lost_amount) + '</td></tr>'; }).join('') || '<tr><td colspan="5">暂无用户数据</td></tr>';
        el('mrBets').innerHTML = (d.bets || []).map(function (b) { var names=['待开奖','已中奖','未中奖','已撤单']; return '<tr><td>' + esc(b.date) + '<br>' + esc(b.order_no) + '</td><td>' + esc(b.username) + '</td><td>' + (b.lottery_type == 1 ? '福彩3D' : '排列三') + ' ' + esc(b.period) + '<br>' + esc(b.play_type === 'quick' ? '快译模式' : b.play_type) + '</td><td>' + esc(names[b.status] || b.status) + '</td><td>' + money(b.total_amount) + '</td><td>' + money(b.win_amount) + '</td><td><details><summary>查看</summary><pre>' + esc(b.content_display) + '</pre></details></td></tr>'; }).join('') || '<tr><td colspan="7">暂无订单</td></tr>';
        maxPage = Math.max(1, Math.ceil(d.bet_total / d.limit), Math.ceil(d.money_log_total / d.limit), Math.ceil(d.rebate_total_count / d.limit));
        el('mrPager').textContent = '第 ' + page + ' / ' + maxPage + ' 页'; total = d.bet_total;
        el('mrUserOnly').style.display = d.scope === 'user' ? '' : 'none';
        el('mrMoneyLogs').innerHTML = (d.money_logs || []).map(function (x) { return '<tr><td>' + esc(new Date(x.createtime * 1000).toLocaleString()) + '</td><td>' + money(x.money) + '</td><td>' + money(x.before) + ' → ' + money(x.after) + '</td><td>' + esc(x.memo) + '</td></tr>'; }).join('') || '<tr><td colspan="4">当前范围无已记录的资金流水</td></tr>';
        el('mrRebates').innerHTML = (d.rebates || []).map(function (x) { return '<tr><td>' + esc(new Date(x.createtime * 1000).toLocaleString()) + '</td><td>' + esc(x.bet_order_no) + '</td><td>' + esc(x.type === 'self' ? '自身返水' : '团队级差') + '</td><td>' + money(x.xima_amount) + '</td><td>' + esc(x.status == 1 ? '已领取' : x.status == 2 ? '已作废' : '待领取') + '</td></tr>'; }).join('') || '<tr><td colspan="5">当前范围无返水记录</td></tr>';
        el('mrFlowPager').textContent = '资金流水 ' + d.money_log_total + ' 条，返水记录 ' + d.rebate_total_count + ' 条；均显示第 ' + page + ' 页';
    }
    function load(p) { if (p < 1 || (p > 1 && p > maxPage)) return; if (admin && !el('mrAgentId').value) { el('mrMessage').textContent='请先选择代理'; return; } el('mrMessage').textContent='查询中…'; request(endpoint + '?' + params(p)).then(function (r) { if (r.code !== 1) throw new Error(r.msg || '查询失败'); page=p; render(r.data); el('mrMessage').textContent=''; }).catch(function (e) { el('mrMessage').textContent=e.message || '查询失败'; }); }
    el('mrSearch').onclick = function () { total=0; maxPage=1; load(1); };
    el('mrPrev').onclick = function () { load(page - 1); };
    el('mrNext').onclick = function () { load(page + 1); };
    el('mrClearUser').onclick = function () { el('mrUserId').value=''; el('mrUserSearch').value=''; total=0; maxPage=1; load(1); };
    el('mrUsers').onclick = function (event) { var t=event.target.closest('[data-user]'); if (t) { el('mrUserId').value=t.getAttribute('data-user'); el('mrUserSearch').value=''; total=0; maxPage=1; load(1); } };
    if (!admin) load(1);
})();
