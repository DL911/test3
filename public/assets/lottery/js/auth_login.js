/**
 * 登录页面逻辑
 */
(function() {
    // 密码显隐
    var toggle = document.getElementById('togglePwd');
    var pwdInput = document.getElementById('loginPassword');
    if (toggle && pwdInput) {
        toggle.addEventListener('click', function() {
            var isPwd = pwdInput.type === 'password';
            pwdInput.type = isPwd ? 'text' : 'password';
            toggle.innerHTML = '<i class="fa-solid fa-eye' + (isPwd ? '-slash' : '') + '"></i>';
        });
    }

    // 表单提交
    var form = document.getElementById('loginForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var account = document.getElementById('loginAccount').value.trim();
            var password = document.getElementById('loginPassword').value;
            var valid = true;

            if (!account) {
                document.getElementById('fieldAccount').classList.add('error');
                valid = false;
            } else {
                document.getElementById('fieldAccount').classList.remove('error');
            }
            if (!password) {
                document.getElementById('fieldPassword').classList.add('error');
                valid = false;
            } else {
                document.getElementById('fieldPassword').classList.remove('error');
            }
            if (!valid) return;

            var btn = document.getElementById('btnLogin');
            btn.classList.add('auth-btn-loading');
            btn.disabled = true;

            var formData = new FormData();
            formData.append('account', account);
            formData.append('password', password);

            fetch('/index.php/api/user/login', {
                method: 'POST',
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                btn.classList.remove('auth-btn-loading');
                btn.disabled = false;
                if (res.code === 1) {
                    showToast('登录成功，正在跳转...', 'success');
                    if (res.data) {
                        if (typeof LotteryAuth !== 'undefined') LotteryAuth.saveLogin(res.data);
                        if (res.data.userinfo && res.data.userinfo.token) {
                            localStorage.setItem('lottery_token', res.data.userinfo.token);
                            // 必须设置 cookie，PHP 端才能通过 Cookie::get('token') 识别登录状态
                            document.cookie = 'token=' + res.data.userinfo.token + '; path=/; max-age=2592000';
                        }
                    }
                    setTimeout(function() {
                        window.location.href = '/index.php/index/lottery/index';
                    }, 800);
                } else {
                    showToast(res.msg || '登录失败，请检查用户名和密码', 'error');
                }
            })
            .catch(function() {
                btn.classList.remove('auth-btn-loading');
                btn.disabled = false;
                showToast('网络错误，请稍后重试', 'error');
            });
        });
    }

    // 输入时清除错误
    document.querySelectorAll('.auth-field-input').forEach(function(inp) {
        inp.addEventListener('input', function() {
            inp.closest('.auth-field').classList.remove('error');
        });
    });

    // Enter 提交
    document.querySelectorAll('.auth-field-input').forEach(function(inp) {
        inp.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                var frm = document.getElementById('loginForm');
                if (frm) frm.dispatchEvent(new Event('submit'));
            }
        });
    });

    // Toast
    window.showToast = function(msg, type) {
        var c = document.getElementById('toastContainer');
        if (!c) return;
        var t = document.createElement('div');
        t.className = 'toast-item ' + (type || 'info');
        var icon = type === 'success' ? '\u2705' : type === 'error' ? '\u274c' : '\u2139\ufe0f';
        t.innerHTML = '<span>' + icon + '</span><span>' + msg + '</span>';
        c.appendChild(t);
        setTimeout(function() {
            t.style.opacity = '0';
            t.style.transition = 'opacity 0.3s';
            setTimeout(function() { t.remove(); }, 300);
        }, 3000);
    };
})();
