<?php
namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use think\Db;

/** 代理余额划转审计，只读。 */
class Agenttransfer extends Backend
{
    protected $noNeedRight = ['*'];

    public function index()
    {
        if (!$this->request->isAjax()) return $this->view->fetch();
        $page = max(1, $this->request->param('page/d', 1));
        $limit = min(50, max(1, $this->request->param('limit/d', 20)));
        $keyword = trim((string)$this->request->param('keyword', ''));
        $query = Db::name('agent_balance_transfer')->alias('t')
            ->join('user a', 'a.id=t.agent_id', 'LEFT')->join('user s', 's.id=t.sub_id', 'LEFT');
        if ($keyword !== '') $query->where('t.order_no|a.username|s.username', 'like', '%' . addcslashes($keyword, '%_\\') . '%');
        $total = (clone $query)->count();
        $list = $query->field('t.*,a.username as agent_name,s.username as sub_name')->order('t.id', 'desc')->page($page, $limit)->select();
        foreach ($list as &$item) $item['date'] = date('Y-m-d H:i:s', $item['createtime']);
        return json(['code' => 1, 'data' => ['list' => $list, 'total' => $total]]);
    }
}
