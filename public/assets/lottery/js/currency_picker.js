/**
 * 货币模式选择器 - 已禁用USDT，固定为人民币模式
 */
var CurrencyPicker = (function () {
    // 强制设为CNY
    if (typeof localStorage !== 'undefined') {
        localStorage.setItem('lottery_currency', 'cny');
    }
    function open() { /* 已禁用 */ }
    function close() { /* 已禁用 */ }
    return { open: open, close: close };
})();

function showCurrencyModal() { /* 已禁用 */ }
function hideCurrencyModal() { /* 已禁用 */ }
