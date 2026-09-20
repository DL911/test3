/* 手机端让后台外层页面滚动，避免固定高度 iframe 吞掉触摸滑动。 */
(function () {
    'use strict';
    var media = window.matchMedia('(max-width: 767px)');
    var container = document.querySelector('.content-wrapper.tab-addtabs');
    if (!container) return;

    function closeMenu() {
        document.body.classList.remove('sidebar-open');
    }

    var overlay = document.getElementById('adminMobileMenuOverlay');
    if (overlay) overlay.addEventListener('click', closeMenu);
    var sidebar = document.querySelector('.main-sidebar');
    if (sidebar) sidebar.addEventListener('click', function (event) {
        if (!media.matches) return;
        var link = event.target.closest('a');
        if (link && !link.closest('.mobilenav') && !link.closest('.sidebar-form') &&
            !link.parentElement.classList.contains('treeview')) {
            window.setTimeout(closeMenu, 0);
        }
    });

    var observed = new WeakSet();
    function fitFrame(frame) {
        if (!media.matches) {
            frame.style.height = '';
            return;
        }
        try {
            var doc = frame.contentDocument;
            if (!doc || !doc.body) return;
            var height = Math.max(600, doc.body.scrollHeight, doc.documentElement.scrollHeight);
            if (Math.abs(frame.offsetHeight - height) > 4) frame.style.height = height + 'px';
        } catch (e) {
            // 非同源的扩展页保留原本的 iframe 内部滚动。
            frame.style.height = 'calc(100dvh - 50px)';
        }
    }

    function connect(frame) {
        if (observed.has(frame)) return;
        observed.add(frame);
        var scheduled = false;
        var watchedDocument = null;
        function schedule() {
            if (scheduled) return;
            scheduled = true;
            window.requestAnimationFrame(function () {
                scheduled = false;
                fitFrame(frame);
            });
        }
        function watchDocument() {
            try {
                var doc = frame.contentDocument;
                if (doc && doc.body && doc !== watchedDocument && window.ResizeObserver) {
                    watchedDocument = doc;
                    new ResizeObserver(schedule).observe(doc.body);
                }
            } catch (e) { /* 非同源页由 iframe 自行滚动 */ }
        }
        frame.addEventListener('load', function () {
            if (media.matches) frame.style.height = '600px';
            watchDocument();
            schedule();
        });
        watchDocument();
        schedule();
    }

    function syncFrames() {
        container.querySelectorAll('iframe').forEach(connect);
        if (!media.matches) container.querySelectorAll('iframe').forEach(fitFrame);
    }
    new MutationObserver(syncFrames).observe(container, {childList: true, subtree: true});
    window.addEventListener('resize', function () {
        container.querySelectorAll('iframe').forEach(fitFrame);
    });
    syncFrames();
})();
