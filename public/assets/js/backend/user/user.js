define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/user/index',
                    add_url: 'user/user/add',
                    edit_url: 'user/user/edit',
                    del_url: 'user/user/del',
                    multi_url: 'user/user/multi',
                    table: 'user',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'user.id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'), sortable: true},
                        {field: 'group.name', title: __('Group')},
                        {field: 'username', title: __('Username'), operate: 'LIKE'},
                        {field: 'nickname', title: __('Nickname'), operate: 'LIKE'},
                        {field: 'mobile', title: __('Mobile'), operate: 'LIKE'},
                        {field: 'avatar', title: __('Avatar'), events: Table.api.events.image, formatter: Table.api.formatter.image, operate: false},
                        {field: 'money', title: '余额', sortable: true, formatter: function(v){return '<b style="color:#e67e22;">¥'+parseFloat(v||0).toFixed(2)+'</b>';}},
                        {field: 'level', title: __('Level'), operate: 'BETWEEN', sortable: true},
                        {field: 'score', title: __('Score'), operate: 'BETWEEN', sortable: true, visible: false},
                        {field: 'logintime', title: __('Logintime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {field: 'jointime', title: __('Jointime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {field: 'status', title: __('Status'), formatter: function(value, row) {
                            if (value === 'normal') return '<span class="label label-success">正常</span>';
                            return '<span class="label label-danger">禁用</span>';
                        }, searchList: {normal: __('Normal'), hidden: __('Hidden')}},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'profile',
                                    text: '详情',
                                    title: '会员详情',
                                    classname: 'btn btn-xs btn-info btn-dialog',
                                    icon: 'fa fa-search-plus',
                                    url: 'user/user/profile',
                                    extend: 'data-area=\'["95%","90%"]\''
                                },
                                {
                                    name: 'toggle',
                                    text: '',
                                    title: '启用/禁用',
                                    classname: 'btn btn-xs btn-warning btn-toggle-status',
                                    icon: 'fa fa-power-off',
                                    dropdown: '',
                                }
                            ],
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ]
            });

            // 启用/禁用按钮事件
            $(document).on('click', '.btn-toggle-status', function() {
                var row = $(this).closest('tr');
                var index = row.data('index');
                var rowData = table.bootstrapTable('getData');
                if (index === undefined || !rowData[index]) return;
                rowData = rowData[index];
                var action = (rowData.status === 'normal') ? 'disable' : 'enable';
                var msg = (action === 'disable') ? '确定要禁用该会员吗？禁用后将无法登录。' : '确定要启用该会员吗？';
                Layer.confirm(msg, function(idx) {
                    $.ajax({
                        url: 'user/user/toggleStatus',
                        type: 'POST',
                        data: {id: rowData.id, action: action},
                        dataType: 'json',
                        success: function(res) {
                            Layer.close(idx);
                            if (res.code === 1) {
                                Layer.msg(res.msg, {icon: 1});
                                table.bootstrapTable('refresh');
                            } else {
                                Layer.msg(res.msg, {icon: 2});
                            }
                        }
                    });
                });
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        profile: function () {
            // profile 页面 JS 逻辑在视图内处理
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});