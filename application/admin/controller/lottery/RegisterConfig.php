<?php
namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use think\Db;

/**
 * 注册配置信息（只读展示）
 * 仅用于查看当前注册相关的系统设置，不提供编辑
 */
class RegisterConfig extends Backend
{
    protected $noNeedRight = ['*'];

    /**
     * 展示页
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            // 默认邀请返水比例：与前台注册逻辑保持一致（User.php 中默认 0.015）
            $defaultRebate = 0.015;

            // 统计注册相关数据
            $totalUsers = Db::name('user')->count();
            $todayStart = strtotime(date('Y-m-d 00:00:00'));
            $todayUsers = Db::name('user')->where('createtime', '>=', $todayStart)->count();
            $invitedUsers = Db::name('user')->where('pid', '>', 0)->count();

            $data = [
                'items' => [
                    ['name' => '注册功能', 'value' => '已开放', 'desc' => '允许新用户通过前台注册'],
                    ['name' => '邀请码绑定', 'value' => '支持（选填）', 'desc' => '注册时可填写邀请码绑定上级'],
                    ['name' => '默认邀请返水比例', 'value' => ($defaultRebate * 100) . '%', 'desc' => '未指定时新用户的默认 invite_rebate_rate'],
                    ['name' => '手机号注册', 'value' => '启用', 'desc' => '以手机号作为登录账号'],
                ],
                'stats' => [
                    'total_users'   => $totalUsers,
                    'today_users'   => $todayUsers,
                    'invited_users' => $invitedUsers,
                ],
            ];
            return json(['code' => 1, 'msg' => 'ok', 'data' => $data]);
        }
        return $this->view->fetch();
    }
}
