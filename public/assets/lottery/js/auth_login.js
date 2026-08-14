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

    var forgot=document.getElementById('forgotPassword'),mask=document.getElementById('securityMask');
    if(forgot) forgot.addEventListener('click',function(){mask.classList.add('show')});
    var close=document.getElementById('securityClose');if(close)close.addEventListener('click',function(){mask.classList.remove('show')});
    if(mask)mask.addEventListener('click',function(e){if(e.target===mask)mask.classList.remove('show')});
    var securitySubmit=document.getElementById('securitySubmit');
    var securityFeedback=document.getElementById('securityFeedback');
    function resetFeedback(message,type){
        if(!securityFeedback)return;
        securityFeedback.textContent=message||'';
        securityFeedback.className='security-feedback'+(message?' show '+(type||'error'):'');
    }
    if(securitySubmit)securitySubmit.addEventListener('click',function(){
        var account=document.getElementById('securityAccount').value.trim(),teacher=document.getElementById('resetTeacher').value.trim();
        var hometown=document.getElementById('resetHometown').value.trim(),friend=document.getElementById('resetFriend').value.trim();
        var pwd=document.getElementById('securityNewPassword').value,pwd2=document.getElementById('securityNewPassword2').value;
        resetFeedback('');
        if(!account||teacher.length<2||hometown.length<2||friend.length<2){resetFeedback('请完整填写账号和三项安全问题答案','error');return}
        if(pwd.length<6||pwd.length>30){resetFeedback('新密码长度需要6-30位','error');return}if(pwd!==pwd2){resetFeedback('两次输入的密码不一致','error');return}
        var fd=new FormData();fd.append('account',account);fd.append('security_teacher',teacher);fd.append('security_hometown',hometown);fd.append('security_friend',friend);fd.append('newpassword',pwd);
        securitySubmit.disabled=true;securitySubmit.textContent='正在重置...';
        fetch('/index.php/api/user/resetBySecurity',{method:'POST',body:fd}).then(function(response){return response.text().then(function(text){try{return JSON.parse(text)}catch(e){throw new Error('服务器返回异常，请稍后重试')}})}).then(function(result){if(result.code===1){resetFeedback('密码重置成功，请使用新密码登录','success');document.getElementById('loginAccount').value=account;setTimeout(function(){mask.classList.remove('show')},900)}else{resetFeedback(result.msg||'密码重置失败','error')}}).catch(function(error){resetFeedback(error.message||'网络错误，请稍后重试','error')}).then(function(){securitySubmit.disabled=false;securitySubmit.textContent='确认重置密码'});
    });

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
