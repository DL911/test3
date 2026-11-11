<?php

namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use app\common\service\AgentMarketReportService;
use think\Db;

/** 代理市场只读核算报表。 */
class Agentmarket extends Backend
{
    public function index()
    {
        if (!$this->request->isAjax()) return $this->view->fetch();
        $agentId = $this->request->get('agent_id/d', 0);
        if (!$agentId) return json(['code' => 0, 'msg' => '请选择代理']);
        try {
            return json(['code' => 1, 'data' => AgentMarketReportService::report($agentId, $this->request->get())]);
        } catch (\InvalidArgumentException $e) {
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        } catch (\Exception $e) {
            \think\Log::error('后台代理市场报表查询失败，代理ID=' . $agentId . '：' . $e->getMessage());
            return json(['code' => 0, 'msg' => '报表查询失败，请查看服务端日志']);
        }
    }

    public function agents()
    {
        $keyword = trim((string)$this->request->get('keyword', ''));
        if ($keyword === '') return json(['code' => 1, 'data' => []]);
        $list = Db::name('user')->where('agent_level', '>', 0)->where('is_agent', 1)
            ->where('username|nickname', 'like', '%' . addcslashes($keyword, '%_\\') . '%')
            ->field('id,username,nickname,agent_level')->order('id desc')->limit(20)->select();
        return json(['code' => 1, 'data' => $list]);
    }
}
