<?php
namespace app\api\controller;

use app\common\controller\Api;
use app\common\service\AgentRebateService;
use app\common\service\QuickBetService;
use think\Db;

/** 快译预览与整批真实投注。客户端的玩法、赔率和金额均重新校验。 */
class Quickbet extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    public function settings()
    {
        $type = $this->request->get('type', 'fc3d');
        $lotteryType = $type === 'fc3d' ? 1 : ($type === 'pl3' ? 2 : 0);
        if (!$lotteryType) $this->error('彩种无效');
        $rows = QuickBetService::settings($lotteryType);
        $this->success('success', ['version' => $this->version($rows), 'rows' => $rows, 'catalog' => QuickBetService::catalog()]);
    }

    public function place()
    {
        $userId = intval($this->auth->id);
        $type = $this->request->post('type', '');
        $lotteryType = $type === 'fc3d' ? 1 : ($type === 'pl3' ? 2 : 0);
        $period = trim((string)$this->request->post('period', ''));
        $version = trim((string)$this->request->post('version', ''));
        $raw = trim((string)$this->request->post('raw', ''));
        $requestId = trim((string)$this->request->post('request_id', ''));
        $items = json_decode((string)$this->request->post('items', ''), true);
        if (!$lotteryType) $this->error('快译彩种参数无效');
        if (!preg_match('/^\d{5,20}$/', $period)) $this->error('快译期号参数无效，请刷新页面');
        if (!preg_match('/^[A-Za-z0-9-]{16,64}$/', $requestId)) $this->error('快译请求编号无效，请刷新页面');
        if (!is_array($items) || !$items || count($items) > 100) $this->error('快译注单内容无效，请重新快译核对');
        if (mb_strlen($raw) > 10000) $this->error('快译原文过长，请分批投注');
        $config = Db::name('lottery_config')->where('code', $type)->where('status', 1)->find();
        if (!$config) $this->error('彩种未开放');
        Db::startTrans();
        try {
            $user = Db::name('user')->where('id', $userId)->lock(true)->field('money')->find();
            $previous = Db::name('quick_bet_audit')->where('user_id', $userId)->where('request_id', $requestId)->find();
            if ($previous) {
                Db::rollback();
                $this->success('该批注单已投注成功', ['order_no' => $previous['order_no']]);
            }
            $draw = Db::name('lottery_draw')->where('lottery_type', $lotteryType)->where('period', $period)->where('status', 0)->find();
            $latestSettledPeriod = Db::name('lottery_draw')->where('lottery_type', $lotteryType)->where('status', 1)->max('period');
            if ($latestSettledPeriod && (int)$period <= (int)$latestSettledPeriod) throw new \RuntimeException('该期号已过期，请刷新期号');
            if (!$draw || strtotime($draw['draw_time']) - time() <= intval($config['bet_close_seconds'])) throw new \RuntimeException('该期已封盘，请重新选择期号');
            $rows = Db::name('quick_bet_odds')->where('lottery_type', 0)->order('id')->lock(true)->select();
            if (!$version || $version !== $this->version($rows)) throw new \RuntimeException('玩法赔率已调整，请重新核对注单');
            $odds = []; foreach ($rows as $row) if (intval($row['status']) === 1) $odds[$row['play_code']] = floatval($row['odds']);
            $normalized = []; $totalCents = 0;
            foreach ($items as $item) {
                $entry = QuickBetService::validateItem($item);
                if (!isset($odds[$entry['play']]) || $odds[$entry['play']] <= 0) throw new \RuntimeException('有玩法未启用，请重新核对');
                $entry['odds'] = $odds[$entry['play']];
                $cents = intval(round(floatval($entry['amount']) * 100));
                if ($cents < intval(round(floatval($config['min_bet']) * 100)) || $cents > intval(round(floatval($config['max_bet']) * 100))) throw new \RuntimeException('单注金额不在允许范围内');
                $totalCents += $cents; $normalized[] = $entry;
            }
            $total = number_format($totalCents / 100, 2, '.', '');
            if (!$user || intval(round(floatval($user['money']) * 100)) < $totalCents) throw new \RuntimeException('余额不足');
            $orderNo = 'Q' . date('YmdHis') . str_pad($userId, 6, '0', STR_PAD_LEFT) . mt_rand(100000, 999999);
            $now = time();
            Db::name('lottery_bet')->insert(['order_no' => $orderNo, 'user_id' => $userId, 'lottery_type' => $lotteryType, 'period' => $period, 'play_type' => 'quick', 'play_sub' => '', 'panel_type' => 'quick', 'bet_content' => json_encode($normalized, JSON_UNESCAPED_UNICODE), 'bet_count' => count($normalized), 'bet_amount' => $total, 'total_amount' => $total, 'odds' => 0, 'status' => 0, 'createtime' => $now, 'updatetime' => $now]);
            Db::name('user')->where('id', $userId)->setDec('money', $total);
            AgentRebateService::createForBet($userId, $orderNo, floatval($total), $lotteryType);
            Db::name('quick_bet_audit')->insert(['order_no' => $orderNo, 'request_id' => $requestId, 'user_id' => $userId, 'lottery_type' => $lotteryType, 'period' => $period, 'raw_text' => $raw, 'confirmed_items' => json_encode($normalized, JSON_UNESCAPED_UNICODE), 'settings_version' => $version, 'createtime' => $now]);
            $balance = Db::name('user')->where('id', $userId)->value('money');
            Db::commit();
        } catch (\think\exception\HttpResponseException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            Db::rollback();
            $this->error('投注失败：' . $e->getMessage());
        } catch (\Throwable $e) {
            Db::rollback();
            $errorId = 'QB-' . date('YmdHis') . '-' . $userId;
            \think\Log::error($errorId . ' 快译下注失败：' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->error('投注失败，请联系管理员并提供编号 ' . $errorId);
        }
        $this->success('投注成功', ['order_no' => $orderNo, 'total_amount' => $total, 'balance' => $balance]);
    }

    private function version($rows)
    {
        return hash('sha256', json_encode($rows));
    }
}
