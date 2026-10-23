<?php
namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use app\common\service\QuickBetService;
use think\Db;

/** 快译玩法赔率：只有服务端实现中奖规则的玩法可在此启用。 */
class Quickodds extends Backend
{
    public function index()
    {
        if (!$this->request->isAjax()) return $this->view->fetch();
        $rows = Db::name('quick_bet_odds')->order('lottery_type,id')->select();
        return json(['code' => 1, 'data' => ['rows' => $rows, 'catalog' => QuickBetService::catalog()]]);
    }

    public function save()
    {
        if (!$this->request->isPost()) return json(['code' => 0, 'msg' => '请求方式错误']);
        $id = $this->request->post('id/d', 0);
        $oddsText = trim((string)$this->request->post('odds', ''));
        $description = trim((string)$this->request->post('description', ''));
        $status = $this->request->post('status/d', 0);
        if (!$id || !preg_match('/^\d{1,7}(\.\d{1,4})?$/', $oddsText) || floatval($oddsText) <= 0 || !in_array($status, [0,1], true) || mb_strlen($description) > 255) return json(['code' => 0, 'msg' => '赔率、状态或说明格式错误']);
        $new = ['odds' => number_format(floatval($oddsText), 4, '.', ''), 'description' => $description, 'status' => $status, 'updatetime' => time()];
        Db::startTrans();
        try {
            $row = Db::name('quick_bet_odds')->where('id', $id)->lock(true)->find();
            if (!$row || !isset(QuickBetService::catalog()[$row['play_code']])) throw new \RuntimeException('不支持该玩法');
            Db::name('quick_bet_odds')->where('id', $id)->update($new);
            Db::name('quick_bet_odds_log')->insert(['odds_id' => $id, 'admin_id' => $this->auth->id, 'before_data' => json_encode($row, JSON_UNESCAPED_UNICODE), 'after_data' => json_encode($new, JSON_UNESCAPED_UNICODE), 'createtime' => time()]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return json(['code' => 0, 'msg' => '保存失败：' . $e->getMessage()]);
        }
        return json(['code' => 1, 'msg' => '保存成功，未提交的快译注单需要重新核对']);
    }
}
