<?php
namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 洗码 API
 */
class Xima extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    /**
     * 我的洗码概况
     * GET /api/xima/summary
     */
    public function summary()
    {
        $userId = $this->auth->id;
        $todayStart = strtotime('today'); // 今天00:00:00

        // 可领取洗码（隔天：createtime < 今天00:00）
        $claimableSelf = Db::name('xima_record')
            ->where('user_id', $userId)
            ->where('type', 'self')
            ->where('status', 0)
            ->where('createtime', '<', $todayStart)
            ->sum('xima_amount');

        $claimableParent = Db::name('xima_record')
            ->where('user_id', $userId)
            ->where('type', 'parent')
            ->where('status', 0)
            ->where('createtime', '<', $todayStart)
            ->sum('xima_amount');

        // 今日新增（不可领，展示用）
        $todaySelf = Db::name('xima_record')
            ->where('user_id', $userId)
            ->where('type', 'self')
            ->where('status', 0)
            ->where('createtime', '>=', $todayStart)
            ->sum('xima_amount');

        $todayParent = Db::name('xima_record')
            ->where('user_id', $userId)
            ->where('type', 'parent')
            ->where('status', 0)
            ->where('createtime', '>=', $todayStart)
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
            ->where('createtime', '<', $todayStart)
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
     * 一键领取全部洗码
     * POST /api/xima/claim
     */
    public function claim()
    {
        $userId = $this->auth->id;
        $todayStart = strtotime('today');

        Db::startTrans();
        try {
            // 锁定用户后再查询待领取记录，防止并发请求重复入账
            Db::name('user')->where('id', $userId)->lock(true)->field('id')->find();

            // 只领取隔天及之前的记录（当天投注次日才能领）
            $records = Db::name('xima_record')
                ->where('user_id', $userId)
                ->where('status', 0)
                ->where('createtime', '<', $todayStart)
                ->lock(true)
                ->select();

            if (empty($records)) {
                Db::rollback();
                $this->error('暂无可领取的洗码');
            }

            $totalAmount = 0;
            foreach ($records as $r) {
                $totalAmount += $r['xima_amount'];
            }
            $totalAmount = round($totalAmount, 2);

            if ($totalAmount <= 0) {
                Db::rollback();
                $this->error('洗码金额为0');
            }

            // 批量更新状态
            $ids = array_column($records, 'id');
            Db::name('xima_record')
                ->where('id', 'in', $ids)
                ->update([
                    'status'     => 1,
                    'claim_time' => time(),
                    'updatetime' => time(),
                ]);

            // 加余额
            Db::name('user')->where('id', $userId)->setInc('money', $totalAmount);

            $newBalance = Db::name('user')->where('id', $userId)->value('money');
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
        ]);
    }

    /**
     * 洗码记录
     * GET /api/xima/history?page=1&type=self
     */
    public function history()
    {
        $userId = $this->auth->id;
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
            $item['type_text'] = $item['type'] === 'self' ? '自身洗码' : '邀请人洗码';
            $item['status_text'] = ['待领取', '已领取', '已过期'][$item['status']] ?? '未知';
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
}
