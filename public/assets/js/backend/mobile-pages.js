/* 后台所有业务页：为非 Bootstrap Table 的宽表格提供独立横向触摸滚动。 */
(function () {
    'use strict';
    var media = window.matchMedia('(max-width: 767px)');
    function wrapTables() {
        if (!media.matches) return;
        document.querySelectorAll('#content table').forEach(function (table) {
            // Bootstrap Table 会自行创建横向滚动层，不能在其初始化前移动原始节点。
            if (table.id === 'table' || table.hasAttribute('data-toggle')) return;
            if (table.closest('.admin-mobile-table-scroll, .table-responsive, .fixed-table-container, .fixed-table-body')) return;
            var wrapper = document.createElement('div');
            wrapper.className = 'admin-mobile-table-scroll';
            wrapper.setAttribute('role', 'region');
            wrapper.setAttribute('aria-label', '表格，可左右滑动查看更多列');
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        });
    }
    function start() {
        wrapTables();
        var pending = false;
        new MutationObserver(function () {
            if (pending) return;
            pending = true;
            requestAnimationFrame(function () { pending = false; wrapTables(); });
        }).observe(document.getElementById('content') || document.body, {childList: true, subtree: true});
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
