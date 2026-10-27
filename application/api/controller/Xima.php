<?php
namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 逐笔返水 API
 */
class Xima extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    /**
     * 我的返水概况
     * GET /api/xima/summary
     */
    public function summary()
    {
        $userId = $this->auth->id;
        $this->ensureNewAgentMember($userId);
        $today = date('Y-m-d');

        // 可领取返水（每笔投注次日解锁）
        $claimableSelf = Db::name('xima_record')
            ->where('user_id', $userId)
            ->where('type', 'self')
            ->where('status', 0)
            ->where('unlock_date', '<=', $today)
            ->sum('xima_amount');

        $claimableParent = Db::name('xima_record')
            ->where('user_id', $userId)
            ->where('type', 'parent')
            ->where('status', 0)
            ->where('unlock_date', '<=', $today)
            ->sum('xima_amount');

        // 今日新增（不可领，展示用）
        $todaySelf = Db::name('xima_record')
            ->where('user_id', $userId)
            ->where('type', 'self')
            ->where('status', 0)
            ->where('unlock_date', '>', $today)
            ->sum('xima_amount');

        $todayParent = Db::name('xima_record')
            ->where('user_id', $userId)
            ->where('type', 'parent')
            ->where('status', 0)
            ->where('unlock_date', '>', $today)
            ->sum('xima_amount');

        // 已领取总额
        $claimedTotal = Db::name('xima_record')
            ->where('user_id', $userId)
            ->where('status', 1)
            ->sum('xima_amount');

        // 可领取条数
        $claimableCount = Db::name('xima_record')
            ->where('user_id', $userId)
            ->where('status', 0)
            ->where('unlock_date', '<=', $today)
            ->count();

        $this->success('OK', [
            'pending_self'   => round($claimableSelf, 2),
            'pending_parent' => round($claimableParent, 2),
            'pending_total'  => round($claimableSelf + $claimableParent, 2),
            'pending_count'  => $claimableCount,
            'claimed_total'  => round($claimedTotal, 2),
            'today_self'     => round($todaySelf, 2),
            'today_parent'   => round($todayParent, 2),
            'today_total'    => round($todaySelf + $todayParent, 2),
        ]);
    }

    /**
     * 一键领取全部返水
     * POST /api/xima/claim
     */
    public function claim()
    {
        $userId = $this->auth->id;
        $this->ensureNewAgentMember($userId);
        $today = date('Y-m-d');

        Db::startTrans();
        try {
            // 锁定用户后再查询待领取记录，防止并发请求重复入账
            $lockedUser = Db::name('user')->where('id', $userId)->lock(true)->field('id,money')->find();

            // 只领取隔天及之前的记录（当天投注次日才能领）
            $records = Db::name('xima_record')
                ->where('user_id', $userId)
                ->where('status', 0)
                ->where('unlock_date', '<=', $today)
                ->lock(true)
                ->select();

            if (empty($records)) {
                Db::rollback();
                $this->error('暂无可领取的返水');
            }

            $totalAmount = 0;
            foreach ($records as $r) {
                $totalAmount += $r['xima_amount'];
            }
            $totalAmount = round($totalAmount, 2);

            if ($totalAmount <= 0) {
                Db::rollback();
                $this->error('返水金额为0');
            }

            $batchNo = 'XR' . date('YmdHis') . str_pad($userId, 6, '0', STR_PAD_LEFT) . mt_rand(1000, 9999);
            // 批量更新状态
            $ids = array_column($records, 'id');
            $updatedCount = Db::name('xima_record')
                ->where('id', 'in', $ids)
                ->where('user_id', $userId)
                ->where('status', 0)
                ->update([
                    'status'     => 1,
                    'claim_batch_no' => $batchNo,
                    'claim_time' => time(),
                    'updatetime' => time(),
                ]);
            if ($updatedCount !== count($records)) {
                throw new \RuntimeException('奖励记录状态已变更，请刷新后重试');
            }

            $balanceBefore = floatval($lockedUser['money']);
            Db::name('user')->where('id', $userId)->setInc('money', $totalAmount);
            $newBalance = Db::name('user')->where('id', $userId)->value('money');
            Db::name('user_money_log')->insert([
                'user_id' => $userId,
                'money' => $totalAmount,
                'before' => $balanceBefore,
                'after' => $newBalance,
                'memo' => '领取返水：' . $batchNo,
                'createtime' => time(),
            ]);
            Db::name('xima_claim_batch')->insert([
                'batch_no' => $batchNo,
                'user_id' => $userId,
                'record_count' => count($records),
                'claim_amount' => $totalAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $newBalance,
                'client_ip' => $this->request->ip(),
                'createtime' => time(),
            ]);
            Db::commit();
        } catch (\think\exception\HttpResponseException $e) {
            throw $e;
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('领取失败: ' . $e->getMessage());
        }
        $this->success('领取成功，¥' . $totalAmount . ' 已到账', [
            'claimed_amount' => $totalAmount,
            'claimed_count'  => count($records),
            'balance'        => $newBalance,
            'batch_no'       => $batchNo,
        ]);
    }

    /**
     * 返水记录
     * GET /api/xima/history?page=1&type=self
     */
    public function history()
    {
        $userId = $this->auth->id;
        $this->ensureNewAgentMember($userId);
        $page   = $this->request->param('page/d', 1);
        $limit  = $this->request->param('limit/d', 20);
        $type   = $this->request->param('type', '');

        $where = ['user_id' => $userId];
        if ($type) $where['type'] = $type;

        $list = Db::name('xima_record')
            ->where($where)
            ->order('createtime', 'desc')
            ->page($page, $limit)
            ->select();

        foreach ($list as &$item) {
            $item['create_date'] = date('Y-m-d H:i', $item['createtime']);
            $item['claim_date'] = $item['claim_time'] ? date('Y-m-d H:i', $item['claim_time']) : '--';
            $item['type_text'] = $item['type'] === 'self' ? '自身返水' : '团队级差';
            $item['status_text'] = $item['status'] == 1 ? '已领取' : ($item['status'] == 2 ? '已作废' : ($item['unlock_date'] <= date('Y-m-d') ? '可领取' : '今日待解锁'));
            // 来源用户名
            $sourceUserId = isset($item['source_user_id']) ? $item['source_user_id'] : 0;
            if ($sourceUserId && $sourceUserId != $userId) {
                $item['source_name'] = Db::name('user')->where('id', $sourceUserId)->value('nickname') ?: '--';
            } else {
                $item['source_name'] = '自己';
            }
        }

        $total = Db::name('xima_record')->where($where)->count();
        $this->success('OK', ['list' => $list, 'total' => $total]);
    }

    private function ensureNewAgentMember($userId)
    {
        $level = intval(Db::name('user')->where('id', $userId)->value('agent_level'));
        if ($level <= 0) $this->error('当前账号不属于新代理返水体系');
    }
}
