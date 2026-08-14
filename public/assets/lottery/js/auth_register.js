/**
 * 注册页面逻辑
 */
(function() {
    // 简易设备标识：同一浏览器配置只允许注册一次
    function getRegisterDeviceId() {
        var key = 'lottery_register_device_id';
        var id = '';
        try { id = localStorage.getItem(key) || ''; } catch (e) {}
        if (!id) {
            var match = document.cookie.match(new RegExp('(?:^|; )' + key + '=([^;]*)'));
            if (match) id = decodeURIComponent(match[1]);
        }
        if (!/^[a-f0-9]{32}$/.test(id)) {
            var bytes = new Uint8Array(16);
            if (window.crypto && window.crypto.getRandomValues) window.crypto.getRandomValues(bytes);
            else for (var i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
            id = Array.prototype.map.call(bytes, function(b){ return ('0' + b.toString(16)).slice(-2); }).join('');
            try { localStorage.setItem(key, id); } catch (e) {}
        }
        document.cookie = key + '=' + id + '; path=/; max-age=31536000; SameSite=Lax';
        return id;
    }
    var registerDeviceId = getRegisterDeviceId();

    // 检查URL是否带邀请码
    var urlParams = new URLSearchParams(window.location.search);
    var inviteCode = urlParams.get('invite') || '';
    if (inviteCode && document.getElementById('regInviteCode')) {
        document.getElementById('regInviteCode').value = inviteCode;
    }

    // 密码显隐
    var toggleBtn = document.getElementById('toggleRegPwd');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            var inp = document.getElementById('regPassword');
            var isPwd = inp.type === 'password';
            inp.type = isPwd ? 'text' : 'password';
            toggleBtn.innerHTML = '<i class="fa-solid fa-eye' + (isPwd ? '-slash' : '') + '"></i>';
        });
    }

    // 密码强度检测
    var pwdInput = document.getElementById('regPassword');
    if (pwdInput) {
        pwdInput.addEventListener('input', function() {
            var pwd = this.value;
            var score = 0;
            if (pwd.length >= 6) score++;
            if (pwd.length >= 10) score++;
            if (/[A-Z]/.test(pwd) && /[a-z]/.test(pwd)) score++;
            if (/\d/.test(pwd) && /[^a-zA-Z0-9]/.test(pwd)) score++;

            var bars = ['str1','str2','str3','str4'];
            var labels = ['', '\u5f31', '\u4e00\u822c', '\u8f83\u5f3a', '\u5f3a'];
            var levels = ['', 'weak', 'medium', 'medium', 'strong'];
            bars.forEach(function(id, i) {
                var el = document.getElementById(id);
                if (el) {
                    el.className = 'strength-bar' + (i < score ? ' active ' + levels[score] : '');
                }
            });
            var txt = document.getElementById('pwdStrengthText');
            if (txt) {
                txt.textContent = pwd.length > 0 ? '\u5bc6\u7801\u5f3a\u5ea6: ' + labels[score] : '';
            }
        });
    }

    // 表单提交
    var form = document.getElementById('registerForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var username = document.getElementById('regUsername').value.trim();
            var mobile = document.getElementById('regMobile').value.trim();
            var password = document.getElementById('regPassword').value;
            var password2 = document.getElementById('regPassword2').value;
            var agree = document.getElementById('agreeTerms').checked;
            var securityTeacher = document.getElementById('securityTeacher').value.trim();
            var securityHometown = document.getElementById('securityHometown').value.trim();
            var securityFriend = document.getElementById('securityFriend').value.trim();
            var valid = true;

            // 验证用户名
            if (!username || username.length < 4 || !/^[a-zA-Z0-9_]+$/.test(username)) {
                document.getElementById('fieldUsername').classList.add('error');
                var ue = document.getElementById('usernameError');
                if (ue) ue.textContent = '\u7528\u6237\u540d\u97004-20\u4f4d\uff0c\u4ec5\u5b57\u6bcd/\u6570\u5b57/\u4e0b\u5212\u7ebf';
                valid = false;
            } else {
                document.getElementById('fieldUsername').classList.remove('error');
            }

            // 验证手机号
            if (!mobile || !/^1[3-9]\d{9}$/.test(mobile)) {
                document.getElementById('fieldMobile').classList.add('error');
                valid = false;
            } else {
                document.getElementById('fieldMobile').classList.remove('error');
            }

            // 验证密码
            if (!password || password.length < 6) {
                document.getElementById('fieldRegPwd').classList.add('error');
                var pe = document.getElementById('regPwdError');
                if (pe) pe.textContent = '\u5bc6\u7801\u81f3\u5c116\u4f4d';
                valid = false;
            } else {
                document.getElementById('fieldRegPwd').classList.remove('error');
            }

            // 验证确认密码
            if (password !== password2) {
                document.getElementById('fieldRegPwd2').classList.add('error');
                valid = false;
            } else {
                document.getElementById('fieldRegPwd2').classList.remove('error');
            }

            // 协议
            if (!agree) {
                showToast('\u8bf7\u5148\u540c\u610f\u7528\u6237\u534f\u8bae', 'error');
                return;
            }

            if (!valid) return;
            if (securityTeacher.length < 2 || securityHometown.length < 2 || securityFriend.length < 2) {
                showToast('请完整填写三项安全问题答案，每项至少2个字符', 'error');
                return;
            }

            var btn = document.getElementById('btnRegister');
            btn.classList.add('auth-btn-loading');
            btn.disabled = true;

            var invite = document.getElementById('regInviteCode') ? document.getElementById('regInviteCode').value.trim() : '';
            
            var formData = new FormData();
            formData.append('username', username);
            formData.append('mobile', mobile);
            formData.append('password', password);
            formData.append('email', username + '_' + Date.now() + '@lottery.com');
            formData.append('device_id', registerDeviceId);
            formData.append('security_teacher', securityTeacher);
            formData.append('security_hometown', securityHometown);
            formData.append('security_friend', securityFriend);
            if (invite) {
                formData.append('invite', invite);
            }

            fetch('/index.php/api/user/register', {
                method: 'POST',
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                btn.classList.remove('auth-btn-loading');
                btn.disabled = false;
                if (res.code === 1) {
                    // 注册接口已自动登录，保存 token 和用户信息
                    if (res.data && res.data.userinfo) {
                        var ui = res.data.userinfo;
                        if (ui.token) {
                            localStorage.setItem('lottery_token', ui.token);
                            // 必须设置 cookie，PHP 端才能通过 Cookie::get('token') 识别登录状态
                            document.cookie = 'token=' + ui.token + '; path=/; max-age=2592000';
                        }
                        localStorage.setItem('lottery_user', JSON.stringify(ui));
                    }
                    showToast('\u6ce8\u518c\u6210\u529f\uff01\u6b63\u5728\u8fdb\u5165\u5927\u5385...', 'success');
                    setTimeout(function() {
                        window.location.href = '/index.php/index/lottery/index';
                    }, 1200);
                } else {
                    showToast(res.msg || '\u6ce8\u518c\u5931\u8d25\uff0c\u8bf7\u7a0d\u540e\u91cd\u8bd5', 'error');
                }
            })
            .catch(function() {
                btn.classList.remove('auth-btn-loading');
                btn.disabled = false;
                showToast('\u7f51\u7edc\u9519\u8bef\uff0c\u8bf7\u7a0d\u540e\u91cd\u8bd5', 'error');
            });
        });
    }

    // 输入清除错误
    document.querySelectorAll('.auth-field-input').forEach(function(inp) {
        inp.addEventListener('input', function() {
            inp.closest('.auth-field').classList.remove('error');
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
