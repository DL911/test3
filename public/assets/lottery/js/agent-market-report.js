(function () {
    'use strict';
    var admin = !!document.querySelector('[data-market-admin="1"]');
    var endpoint = admin ? document.querySelector('[data-market-admin="1"]').getAttribute('data-market-endpoint') : '/index.php/api/agent/marketReport';
    var page = 1, maxPage = 1, userPage = 1, userMaxPage = 1, requestSerial = 0;
    function el(id) { return document.getElementById(id); }
    function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
    function money(v) { return v == null ? '—' : '¥' + Number(v || 0).toFixed(2); }
    function request(url) { return admin ? fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function (r) { return r.text().then(function (body) { try { return JSON.parse(body); } catch (e) { throw new Error('后台接口返回了网页而非报表数据，请检查后台登录状态和访问地址'); } }); }) : LotteryAuth.request(url, {method:'GET'}); }
    var now = new Date(), start = new Date(now.getTime() - 6 * 86400000);
    function date(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
    el('mrStart').value = date(start); el('mrEnd').value = date(now);
    function params(p) {
        var q = new URLSearchParams({page:p, user_page:userPage, date_start:el('mrStart').value, date_end:el('mrEnd').value, user_id:el('mrUserId').value, user_keyword:el('mrUserSearch').value, lottery_type:el('mrType').value});
        if (admin) { q.set('ajax','1'); q.set('agent_username',el('mrAgentSearch').value.trim()); q.set('direct_id',el('mrDirect').value); }
        return q;
    }
    function row(label, value) { return '<div class="mr-stat"><small>' + label + '</small><strong>' + money(value) + '</strong></div>'; }
    function render(d) {
        var s = d.summary || {}, scope = d.scope === 'user' ? '单用户' : '代理市场';
        el('mrScope').textContent = scope + ' · ' + d.agent.username + ' · 投注日期 ' + d.date_start + ' 至 ' + d.date_end + ' · 生成于 ' + d.generated_at;
        el('mrSummary').innerHTML = row('有效投注总额',s.bet_amount) + row('实际派奖总额',s.win_amount) + row('未中奖投注额',s.lost_amount) + row('中奖订单投注额',s.winning_bet_amount) + row('待开奖投注额',s.pending_amount) + row('已结算差额（参考）',s.settled_difference);
        el('mrCount').textContent = '有效订单 ' + s.order_count + ' 笔';
        el('mrMessage').textContent = '';
        el('mrUsers').innerHTML = (d.users || []).map(function (u) { return '<tr><td><button type="button" class="mr-link" data-user="' + esc(u.user_id) + '">' + esc(u.username) + '（' + esc(u.user_id) + '）</button></td><td>' + esc(u.order_count) + '</td><td>' + money(u.bet_amount) + '</td><td>' + money(u.win_amount) + '</td><td>' + money(u.lost_amount) + '</td></tr>'; }).join('') || '<tr><td colspan="5">暂无用户数据</td></tr>';
        userMaxPage = Math.max(1, Math.ceil(d.user_total / 20)); el('mrUserPager').textContent = '共 ' + d.user_total + ' 人，第 ' + userPage + ' / ' + userMaxPage + ' 页';
        maxPage = Math.max(1, Math.ceil(d.money_log_total / d.limit));
        el('mrPager').textContent = '资金流水共 ' + d.money_log_total + ' 条，第 ' + page + ' / ' + maxPage + ' 页';
        el('mrUserOnly').style.display = d.scope === 'user' ? '' : 'none';
        el('mrMoneyLogs').innerHTML = (d.money_logs || []).map(function (x) { return '<tr><td>' + esc(new Date(x.createtime * 1000).toLocaleString()) + '</td><td>' + money(x.money) + '</td><td>' + money(x.before) + ' → ' + money(x.after) + '</td><td>' + esc(x.memo) + '</td></tr>'; }).join('') || '<tr><td colspan="4">当前范围无已记录的资金流水</td></tr>';
    }
    function load(p) { if (p < 1 || (p > 1 && p > maxPage)) return; if (admin && !el('mrAgentSearch').value.trim()) { el('mrMessage').textContent='请输入代理账号'; return; } var serial=++requestSerial; el('mrMessage').textContent='查询中…'; request(endpoint + '?' + params(p)).then(function (r) { if (serial !== requestSerial) return; if (r.code !== 1) throw new Error(r.msg || '查询失败'); page=p; render(r.data); }).catch(function (e) { if (serial === requestSerial) el('mrMessage').textContent=e.message || '查询失败'; }); }
    el('mrSearch').onclick = function () { maxPage=1; userPage=1; load(1); };
    el('mrUserPrev').onclick = function () { if (userPage > 1) { userPage--; load(page); } };
    el('mrUserNext').onclick = function () { if (userPage < userMaxPage) { userPage++; load(page); } };
    el('mrPrev').onclick = function () { load(page - 1); };
    el('mrNext').onclick = function () { load(page + 1); };
    el('mrReset').onclick = function () { requestSerial++; el('mrStart').value=date(start); el('mrEnd').value=date(now); if(admin) el('mrDirect').value=''; el('mrUserId').value=''; el('mrUserSearch').value=''; el('mrType').value=''; if(admin) el('mrAgentSearch').value=''; page=1; maxPage=1; userPage=1; userMaxPage=1; el('mrScope').textContent='市场汇总'; el('mrSummary').innerHTML=''; el('mrCount').textContent=''; el('mrUsers').innerHTML=''; el('mrUserPager').textContent=''; el('mrMoneyLogs').innerHTML=''; el('mrPager').textContent=''; el('mrUserOnly').style.display='none'; el('mrMessage').textContent=admin?'请输入代理账号后查询':'筛选条件已重置，请点击查询'; };
    el('mrUsers').onclick = function (event) { var t=event.target.closest('[data-user]'); if (t) { el('mrUserId').value=t.getAttribute('data-user'); el('mrUserSearch').value=''; maxPage=1; userPage=1; load(1); } };
    if (!admin) load(1);
})();
