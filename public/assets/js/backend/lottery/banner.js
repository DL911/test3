define(['jquery', 'bootstrap', 'backend', 'table'], function ($, undefined, Backend, Table) {
    var Controller = {
        index: function () {
            // 初始化表格
            Table.api.init({});
            $('#table').bootstrapTable({});
        }
    };
    return Controller;
});
