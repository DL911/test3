(function () {
    window.transferBalance = function (userId, direction) {
        var label = direction === 'give' ? '加款' : '扣款';
        var amount = prompt('请输入给直属账号' + label + '的金额（元，最多两位小数）：');
        if (amount === null) return;
        if (!/^\d{1,10}(\.\d{1,2})?$/.test(amount) || Number(amount) <= 0) return alert('请输入有效的正数金额');
        var remark = prompt(direction === 'take' ? '请输入扣款原因（必填）：' : '请输入备注（选填）：', '');
        if (remark === null) return;
        if (direction === 'take' && !remark.trim()) return alert('扣款原因不能为空');
        if (!confirm('确认' + label + ' ¥' + Number(amount).toFixed(2) + '？操作后双方余额立即变化。')) return;
        var form = new FormData();
        form.append('user_id', userId);
        form.append('direction', direction);
        form.append('amount', amount);
        form.append('remark', remark.trim());
        form.append('request_id', 'R' + Date.now() + '-' + Math.random().toString(36).slice(2, 14));
        LotteryAuth.request('/index.php/api/agent/transferBalance', {method:'POST', body:form}).then(function (res) {
            alert(res.msg || '操作失败');
            if (res.code === 1) location.reload();
        }).catch(function () { alert('请求失败，请稍后查看划转记录再重试'); });
    };
})();
