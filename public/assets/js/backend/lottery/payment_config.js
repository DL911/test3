define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // Index logic is handled inline in HTML, so no need here unless using Table.api
        },
        edit: function () {
            Controller.api.bindevent();
            // on window close, refresh parent list
            $('form').on('success.form.validator', function(){
                if(parent && parent.refreshList) {
                    parent.refreshList();
                }
            });
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
