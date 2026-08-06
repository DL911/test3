<?php

namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use think\Db;

/**
 * 会员登录日志
 */
class Loginlog extends Backend
{
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 登录日志列表
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $page    = $this->request->param('page/d', 1);
            $limit   = $this->request->param('limit/d', 20);
            $keyword = $this->request->param('keyword', '');
            $dateStart = $this->request->param('date_start', '');
            $dateEnd   = $this->request->param('date_end', '');

            $where = [];
            if ($keyword) {
                $where['l.username|l.nickname|l.login_ip'] = ['like', "%{$keyword}%"];
            }
            if ($dateStart && $dateEnd) {
                $where['l.createtime'] = ['between', [strtotime($dateStart), strtotime($dateEnd) + 86399]];
            } elseif ($dateStart) {
                $where['l.createtime'] = ['>=', strtotime($dateStart)];
            } elseif ($dateEnd) {
                $where['l.createtime'] = ['<=', strtotime($dateEnd) + 86399];
            }

            try {
                $list = Db::name('user_login_log')
                    ->alias('l')
                    ->where($where)
                    ->order('l.id', 'desc')
                    ->page($page, $limit)
                    ->select();

                $total = Db::name('user_login_log')->alias('l')->where($where)->count();
            } catch (\Exception $e) {
                $this->success('', '', ['list' => [], 'total' => 0]);
            }

            $typeMap = ['account' => '账号密码', 'mobile' => '手机验证码'];
            foreach ($list as &$item) {
                $item['create_date'] = $item['createtime'] ? date('Y-m-d H:i:s', $item['createtime']) : '-';
                $item['login_type_text'] = $typeMap[$item['login_type']] ?? $item['login_type'];
            }
            unset($item);

            $this->success('', '', ['list' => $list, 'total' => $total]);
        }

        return $this->view->fetch();
    }
}
