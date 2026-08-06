define(['jquery', 'bootstrap', 'backend', 'table'], function ($, undefined, Backend, Table) {
    var Controller = {
        index: function () {
            Table.api.init({});
            $('#table').bootstrapTable({});
        }
    };
    return Controller;
});
