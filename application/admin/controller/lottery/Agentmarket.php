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
        $username = trim((string)$this->request->get('agent_username', ''));
        if ($username === '') return json(['code' => 0, 'msg' => '请输入代理账号']);
        $agent = Db::name('user')->where('username', $username)->where('agent_level', '>', 0)->where('is_agent', 1)->field('id')->find();
        if (!$agent) return json(['code' => 0, 'msg' => '未找到该新体系代理账号']);
        $agentId = intval($agent['id']);
        try {
            return json(['code' => 1, 'data' => AgentMarketReportService::report($agentId, $this->request->get())]);
        } catch (\InvalidArgumentException $e) {
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        } catch (\Exception $e) {
            \think\Log::error('后台代理市场报表查询失败，代理ID=' . $agentId . '：' . $e->getMessage());
            return json(['code' => 0, 'msg' => '报表查询失败，请查看服务端日志']);
        }
    }

}
