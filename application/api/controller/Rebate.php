<?php
namespace app\api\controller;

use app\common\controller\Api;
/**
 * 旧流水返利 API（已停用）。
 *
 * 保留控制器是为了让旧客户端得到明确提示，避免路由不存在时误判为网络故障。
 * 新返水统一使用 /api/xima/*。
 */
class Rebate extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    /**
     * 获取返利配置（阶梯比例展示）
     * GET /api/rebate/config
     */
    public function config()
    {
        $this->disabled();
    }

    /**
     * 查询我的返利概况
     * GET /api/rebate/summary
     * 返回：今日流水、可领取返利、历史已领
     */
    public function summary()
    {
        $this->disabled();
    }

    /**
     * 领取昨日返利
     * POST /api/rebate/claim
     */
    public function claim()
    {
        $this->disabled();
    }

    /**
     * 返利领取历史
     * GET /api/rebate/history?page=1
     */
    public function history()
    {
        $this->disabled();
    }

    /**
     * 根据投注金额匹配返利比例
     */
    private function disabled()
    {
        $this->error('旧流水返利已停用，请使用新的逐笔返水中心', null, 410);
    }
}
