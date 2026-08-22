/**
 * 全局认证模块 - 管理登录状态、用户信息、余额显示
 * 所有页面通过 <script src="/assets/lottery/js/auth.js"> 引入
 */
var LotteryAuth = (function() {
    var TOKEN_KEY = 'lottery_token';
    var USER_KEY  = 'lottery_user';
    var CURRENCY_KEY = 'lottery_currency'; // 'cny' or 'usdt'
    var API_BASE  = '/index.php/api';
    var userInfo  = null;
    var usdtRate  = 7.0; // 1 USDT = 7 CNY

    /** 获取 Token */
    function getToken() {
        return localStorage.getItem(TOKEN_KEY) || '';
    }

    /** 保存 Token + 用户信息 */
    function saveLogin(data) {
        if (data && data.userinfo) {
            var t = data.userinfo.token || '';
            localStorage.setItem(TOKEN_KEY, t);
            localStorage.setItem(USER_KEY, JSON.stringify(data.userinfo));
            // 同步设置 cookie，PHP 端通过 Cookie::get('token') 识别登录状态
            if (t) document.cookie = 'token=' + t + '; path=/; max-age=2592000';
            userInfo = data.userinfo;
        }
    }

    /** 清除登录态 */
    function clearLogin() {
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
        // 清除 cookie
        document.cookie = 'token=; path=/; max-age=0';
        userInfo = null;
    }

    /** 是否已登录 */
    function isLoggedIn() {
        return !!getToken();
    }

    /** 获取缓存的用户信息 */
    function getCachedUser() {
        if (userInfo) return userInfo;
        try {
            var s = localStorage.getItem(USER_KEY);
            if (s) { userInfo = JSON.parse(s); return userInfo; }
        } catch(e) {}
        return null;
    }

    /** 从服务器刷新用户信息 */
    function fetchUserInfo(callback) {
        var token = getToken();
        if (!token) { if (callback) callback(null); return; }

        fetch(API_BASE + '/user/index', {
            method: 'GET',
            headers: { 'token': token }
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.code === 1 && res.data) {
                userInfo = res.data;
                localStorage.setItem(USER_KEY, JSON.stringify(userInfo));
                if (callback) callback(userInfo);
            } else {
                // Token 失效
                clearLogin();
                if (callback) callback(null);
            }
        })
        .catch(function() {
            if (callback) callback(getCachedUser());
        });
    }

    /** 获取当前货币模式 - 固定人民币 */
    function getCurrency() {
        return 'cny';
    }

    /** 切换货币模式 - 已禁用 */
    function setCurrency(mode) {
        localStorage.setItem(CURRENCY_KEY, 'cny');
    }
    
    /** 切换当前的模式 - 已禁用 */
    function toggleCurrency() {
        // 固定人民币
    }

    /** 将 CNY 金额转为当前货币值 - 固定人民币 */
    function toDisplayAmount(cnyAmount) {
        return parseFloat(cnyAmount) || 0;
    }

    /** 将前端输入的模式金额转换为 CNY - 固定人民币 */
    function toCny(displayAmount) {
        return parseFloat(displayAmount) || 0;
    }

    /** 格式化金额用于显示 - 固定人民币 */
    function formatMoney(cnyAmount) {
        var num = parseFloat(cnyAmount) || 0;
        return '¥ ' + num.toFixed(2);
    }

    /** 更新页面上的用户信息显示 */
    function updateUI(info) {
        var u = info || getCachedUser();

        // 余额
        var money = u ? (parseFloat(u.money) || 0) : 0;
        var formatted = '¥ ' + money.toFixed(2);

        var balEl = document.getElementById('userBalance');
        if (balEl && u) balEl.textContent = money.toFixed(2);
        
        var headerBalEl = document.getElementById('headerBalance');
        if (headerBalEl && u) headerBalEl.textContent = formatted;

        var pcBal2 = document.getElementById('pcBalance2');
        if (pcBal2 && u) pcBal2.textContent = formatted;
        
        var pcBal1 = document.getElementById('pcBalance');
        if (pcBal1 && u) pcBal1.textContent = formatted;

        // 更新符号标签
        var symEl = document.getElementById('currencySymbol');
        if (symEl && u) symEl.textContent = '¥';

        // 用户名
        var nameText = u ? (u.nickname || u.username || '用户') : '用户';
        var nameEl = document.getElementById('userName');
        if (nameEl) nameEl.textContent = nameText;
        
        var headerNameEl = document.getElementById('headerUserName');
        if (headerNameEl) headerNameEl.textContent = nameText;

        var pcNameEl = document.getElementById('pcUserName');
        if (pcNameEl) pcNameEl.textContent = nameText;

        // ID
        var pcIdEl = document.getElementById('pcUserId');
        if (pcIdEl && u) pcIdEl.textContent = 'ID: ' + (u.id || '--');

        // 头像（如果有）
        if (u && u.avatar) {
            var avatarEl = document.getElementById('userAvatar');
            if (avatarEl) avatarEl.style.backgroundImage = 'url(' + u.avatar + ')';
            
            var pcAvatar = document.getElementById('pcAvatar');
            var pcAvatarIcon = document.getElementById('pcAvatarIcon');
            if (pcAvatar) {
                pcAvatar.style.backgroundImage = 'url(' + u.avatar + ')';
                if (pcAvatarIcon) pcAvatarIcon.style.display = 'none';
            }
        }
        
        // 返水比例
        if (u) {
            var selfRate = parseFloat(u.self_rebate_rate) || 0;
            var inviteRate = parseFloat(u.invite_rebate_rate) || 0;
            var selfRateStr = (selfRate * 100).toFixed(2).replace(/\.?0+$/, '') + '%';
            var inviteRateStr = (inviteRate * 100).toFixed(2).replace(/\.?0+$/, '') + '%';
            
            var userSelfRebate = document.getElementById('userSelfRebate');
            if (userSelfRebate) userSelfRebate.textContent = selfRateStr;
            
            var userInviteRebate = document.getElementById('userInviteRebate');
            if (userInviteRebate) userInviteRebate.textContent = inviteRateStr;

            var pcUserSelfRebate = document.getElementById('pcUserSelfRebate');
            if (pcUserSelfRebate) pcUserSelfRebate.textContent = selfRateStr;
            
            var pcUserInviteRebate = document.getElementById('pcUserInviteRebate');
            if (pcUserInviteRebate) pcUserInviteRebate.textContent = inviteRateStr;
        }

        // 未登录状态 - 显示登录按钮
        var loginBtnWrap = document.getElementById('authBtnWrap');
        var userInfoWrap = document.getElementById('userInfoWrap');
        if (loginBtnWrap && userInfoWrap) {
            if (u) {
                loginBtnWrap.style.display = 'none';
                userInfoWrap.style.display = 'flex';
                userInfoWrap.style.alignItems = 'center';
                userInfoWrap.style.gap = '8px';
            } else {
                loginBtnWrap.style.display = '';
                userInfoWrap.style.display = 'none';
            }
        }
    }

    /** 初始化 - 页面加载时调用 */
    function init() {
        if (isLoggedIn()) {
            // 先用缓存显示，再异步刷新
            updateUI(getCachedUser());
            fetchUserInfo(function(info) {
                if (info) updateUI(info);
            });
        } else {
            updateUI(null);
        }
    }

    /** 检查登录态，未登录则跳转 */
    function requireLogin(msg) {
        if (!isLoggedIn()) {
            if (msg) alert(msg);
            window.location.href = '/index.php/index/lottery/login';
            return false;
        }
        return true;
    }

    /** 退出登录 */
    function logout() {
        var token = getToken();
        if (token) {
            fetch(API_BASE + '/user/logout', {
                method: 'POST',
                headers: { 'token': token }
            }).catch(function() {});
        }
        clearLogin();
        window.location.href = '/index.php/index/lottery/login';
    }

    /**
     * 通用带 Token 请求方法
     * @param {string} url
     * @param {object} options  { method, body, headers }
     * @returns {Promise}
     */
    function request(url, options) {
        options = options || {};
        var method = (options.method || 'GET').toUpperCase();
        var headers = options.headers || {};
        headers['token'] = getToken();

        var fetchOpts = { method: method, headers: headers };

        if (method === 'POST') {
            // 如果传入的 body 是字符串(JSON)，则自动转成 form 格式
            if (typeof options.body === 'string') {
                try {
                    var jsonData = JSON.parse(options.body);
                    var formParts = [];
                    for (var k in jsonData) {
                        if (jsonData.hasOwnProperty(k)) {
                            formParts.push(encodeURIComponent(k) + '=' + encodeURIComponent(jsonData[k]));
                        }
                    }
                    fetchOpts.body = formParts.join('&');
                    headers['Content-Type'] = 'application/x-www-form-urlencoded';
                } catch(e) {
                    fetchOpts.body = options.body;
                }
            } else if (options.body) {
                fetchOpts.body = options.body;
            }
        }

        fetchOpts.headers = headers;

        return fetch(url, fetchOpts).then(function(r) { return r.json(); });
    }

    /** 更新前台客服入口的分通道未读红点。 */
    function refreshKefuUnread() {
        var entries = document.querySelectorAll('[data-kefu-channel]');
        var totalEntries = document.querySelectorAll('[data-kefu-unread-total]');
        if ((!entries.length && !totalEntries.length) || !isLoggedIn()) return;
        request(API_BASE + '/kefu/unreadCount', {method: 'GET'}).then(function(res) {
            if (res.code !== 1 || !res.data) return;
            var channels = res.data.channels || {};
            entries.forEach(function(entry) {
                var badge = entry.querySelector('.kefu-unread-badge');
                if (!badge) return;
                var count = parseInt(channels[entry.getAttribute('data-kefu-channel')] || 0, 10);
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = count > 0 ? 'inline-block' : 'none';
            });
            var total = parseInt(res.data.count || 0, 10);
            var firstUnreadChannel = '';
            Object.keys(channels).some(function(channel) {
                if (parseInt(channels[channel] || 0, 10) > 0) {
                    firstUnreadChannel = channel;
                    return true;
                }
                return false;
            });
            totalEntries.forEach(function(entry) {
                if (entry.getAttribute('data-kefu-unread-total') === 'text') {
                    entry.innerHTML = total > 0
                        ? '<span style="color:#e53935;font-weight:700;">您有 ' + total + ' 条未读消息</span>'
                        : '联系官方客服';
                } else {
                    entry.textContent = total > 99 ? '99+' : total;
                    entry.style.display = total > 0 ? 'inline-flex' : 'none';
                }
                var link = entry.closest ? entry.closest('a') : null;
                if (link) {
                    link.href = firstUnreadChannel
                        ? '/index.php/index/lottery/kefu?channel=' + encodeURIComponent(firstUnreadChannel)
                        : '/index.php/index/lottery/kefu';
                }
            });
        }).catch(function() {});
    }

    // 页面加载自动初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { init(); refreshKefuUnread(); });
    } else {
        init();
        refreshKefuUnread();
    }
    setInterval(refreshKefuUnread, 5000);

    // 公开接口
    return {
        getToken: getToken,
        saveLogin: saveLogin,
        clearLogin: clearLogin,
        isLoggedIn: isLoggedIn,
        getCachedUser: getCachedUser,
        fetchUserInfo: fetchUserInfo,
        updateUI: updateUI,
        requireLogin: requireLogin,
        logout: logout,
        init: init,
        request: request,
        // 货币相关
        getCurrency: getCurrency,
        setCurrency: setCurrency,
        toggleCurrency: toggleCurrency,
        toDisplayAmount: toDisplayAmount,
        toCny: toCny,
        formatMoney: formatMoney,
        usdtRate: usdtRate
    };
})();

/** 用户中心安全问题密码重置（PC/手机共用）。 */
(function () {
    var mask = document.getElementById('userResetMask');
    if (!mask) return;
    var feedback = document.getElementById('userResetFeedback');
    var submit = document.getElementById('userResetSubmit');

    function setFeedback(message, type) {
        feedback.textContent = message || '';
        feedback.className = 'user-reset-feedback' + (message ? ' show ' + (type || 'error') : '');
    }

    window.openUserPasswordReset = function () {
        var user = LotteryAuth.getCachedUser();
        document.getElementById('userResetAccount').value = user ? (user.username || user.mobile || '') : '';
        setFeedback('');
        mask.classList.add('show');
    };
    window.closeUserPasswordReset = function () { mask.classList.remove('show'); };

    mask.addEventListener('click', function (event) {
        if (event.target === mask) window.closeUserPasswordReset();
    });
    document.getElementById('userResetClose').addEventListener('click', window.closeUserPasswordReset);

    submit.addEventListener('click', function () {
        var account = document.getElementById('userResetAccount').value.trim();
        var teacher = document.getElementById('userResetTeacher').value.trim();
        var hometown = document.getElementById('userResetHometown').value.trim();
        var friend = document.getElementById('userResetFriend').value.trim();
        var password = document.getElementById('userResetPassword').value;
        var password2 = document.getElementById('userResetPassword2').value;
        setFeedback('');
        if (!account || teacher.length < 2 || hometown.length < 2 || friend.length < 2) {
            setFeedback('请完整填写账号和三项安全问题答案', 'error'); return;
        }
        if (password.length < 6 || password.length > 30) {
            setFeedback('新密码长度需要6-30位', 'error'); return;
        }
        if (password !== password2) {
            setFeedback('两次输入的密码不一致', 'error'); return;
        }

        var data = new FormData();
        data.append('account', account);
        data.append('security_teacher', teacher);
        data.append('security_hometown', hometown);
        data.append('security_friend', friend);
        data.append('newpassword', password);
        submit.disabled = true;
        submit.textContent = '正在重置...';
        fetch('/index.php/api/user/resetBySecurity', {method: 'POST', body: data})
            .then(function (response) {
                return response.text().then(function (text) {
                    try { return JSON.parse(text); }
                    catch (e) { throw new Error('服务器返回异常，请稍后重试'); }
                });
            })
            .then(function (result) {
                if (result.code !== 1) { setFeedback(result.msg || '密码重置失败', 'error'); return; }
                setFeedback('密码重置成功，请使用新密码重新登录', 'success');
                LotteryAuth.clearLogin();
                setTimeout(function () { location.href = '/index.php/index/lottery/login'; }, 1200);
            })
            .catch(function (error) { setFeedback(error.message || '网络错误，请稍后重试', 'error'); })
            .then(function () { submit.disabled = false; submit.textContent = '确认重置密码'; });
    });
})();
