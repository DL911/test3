<?php

namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use think\Db;

/**
 * 转账记录管理
 */
class Transfer extends Backend
{
    protected $model = null;
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
    }

    public function index()
    {
        if ($this->request->isAjax()) {
            $page  = $this->request->param('page/d', 1);
            $limit = $this->request->param('limit/d', 20);
            $keyword = $this->request->param('keyword', '');

            $where = [];
            if ($keyword) {
                $where['t.order_no|fu.username|tu.username'] = ['like', "%{$keyword}%"];
            }

            $list = Db::name('transfer_order')
                ->alias('t')
                ->join('user fu', 'fu.id = t.from_user_id', 'LEFT')
                ->join('user tu', 'tu.id = t.to_user_id', 'LEFT')
                ->where($where)
                ->field('t.*, fu.username as from_username, fu.nickname as from_nickname, tu.username as to_username, tu.nickname as to_nickname')
                ->order('t.createtime', 'desc')
                ->page($page, $limit)
                ->select();

            $total = Db::name('transfer_order')
                ->alias('t')
                ->join('user fu', 'fu.id = t.from_user_id', 'LEFT')
                ->join('user tu', 'tu.id = t.to_user_id', 'LEFT')
                ->where($where)
                ->count();

            foreach ($list as &$item) {
                $item['create_date'] = date('Y-m-d H:i:s', $item['createtime']);
            }

            $this->success('', '', ['list' => $list, 'total' => $total]);
        }

        return $this->view->fetch();
    }
}
