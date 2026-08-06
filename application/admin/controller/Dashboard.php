<?php

namespace app\admin\controller;

use app\admin\model\Admin;
use app\admin\model\User;
use app\common\controller\Backend;
use app\common\model\Attachment;
use fast\Date;
use think\Db;

/**
 * 控制台
 *
 * @icon   fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Dashboard extends Backend
{

    // 这些方法仅需登录态即可访问（免权限节点校验），供前端AJAX轮询调用
    protected $noNeedRight = ['pendingCheck', 'rangeStats'];

    /**
     * 查看
     */
    public function index()
    {
        try {
            \think\Db::execute("SET @@sql_mode='';");
        } catch (\Exception $e) {

        }
        $column = [];
        $starttime = Date::unixtime('day', -6);
        $endtime = Date::unixtime('day', 0, 'end');
        $joinlist = Db("user")->where('jointime', 'between time', [$starttime, $endtime])
            ->field('jointime, status, COUNT(*) AS nums, DATE_FORMAT(FROM_UNIXTIME(jointime), "%Y-%m-%d") AS join_date')
            ->group('join_date')
            ->select();
        for ($time = $starttime; $time <= $endtime;) {
            $column[] = date("Y-m-d", $time);
            $time += 86400;
        }
        $userlist = array_fill_keys($column, 0);
        foreach ($joinlist as $k => $v) {
            $userlist[$v['join_date']] = $v['nums'];
        }

        $dbTableList = Db::query("SHOW TABLE STATUS");
        $addonList = get_addon_list();
        $totalworkingaddon = 0;
        $totaladdon = count($addonList);
        foreach ($addonList as $index => $item) {
            if ($item['state']) {
                $totalworkingaddon += 1;
            }
        }
        // ========== 彩票业务统计（容错：表不存在则为0）==========
        $todayStart = strtotime(date('Y-m-d'));
        $todayEnd   = $todayStart + 86400;
        $yesterdayStart = $todayStart - 86400;
        $sevenStart = $todayStart - 86400 * 7;

        try {
            $todayRecharge = Db::name('recharge_order')->where('status', 1)->where('createtime', '>=', $todayStart)->where('createtime', '<', $todayEnd)->sum('amount');
            $todayBet = Db::name('lottery_bet')->where('createtime', '>=', $todayStart)->where('createtime', '<', $todayEnd)->where('status', '<>', 3)->sum('total_amount');
            $todayWin = Db::name('lottery_bet')->where('createtime', '>=', $todayStart)->where('createtime', '<', $todayEnd)->where('status', 1)->sum('win_amount');
        } catch (\Exception $e) {
            $todayRecharge = $todayBet = $todayWin = 0;
        }

        try {
            $todayXimaSelf = Db::name('xima_record')->where('type', 'self')->where('createtime', '>=', $todayStart)->where('createtime', '<', $todayEnd)->sum('xima_amount');
            $todayXimaParent = Db::name('xima_record')->where('type', 'parent')->where('createtime', '>=', $todayStart)->where('createtime', '<', $todayEnd)->sum('xima_amount');
        } catch (\Exception $e) { $todayXimaSelf = $todayXimaParent = 0; }
        $todayXima = round($todayXimaSelf + $todayXimaParent, 2);

        try { $todayCommission = Db::name('xima_record')->where('type', 'parent')->where('createtime', '>=', $todayStart)->where('createtime', '<', $todayEnd)->sum('xima_amount'); } catch (\Exception $e) { $todayCommission = 0; }
        try { $todayWithdraw = Db::name('withdraw_order')->where('status', 1)->where('createtime', '>=', $todayStart)->where('createtime', '<', $todayEnd)->sum('amount'); } catch (\Exception $e) { $todayWithdraw = 0; }
        try { $todayRebate = Db::name('rebate_record')->where('status', 1)->where('createtime', '>=', $todayStart)->where('createtime', '<', $todayEnd)->sum('rebate_amount'); } catch (\Exception $e) { $todayRebate = 0; }

        // 昨日数据
        try {
            $yesterdayRecharge = Db::name('recharge_order')->where('status', 1)->where('createtime', '>=', $yesterdayStart)->where('createtime', '<', $todayStart)->sum('amount');
            $yesterdayBet = Db::name('lottery_bet')->where('createtime', '>=', $yesterdayStart)->where('createtime', '<', $todayStart)->where('status', '<>', 3)->sum('total_amount');
            $yesterdayWin = Db::name('lottery_bet')->where('createtime', '>=', $yesterdayStart)->where('createtime', '<', $todayStart)->where('status', 1)->sum('win_amount');
        } catch (\Exception $e) { $yesterdayRecharge = $yesterdayBet = $yesterdayWin = 0; }

        // 7日数据
        try { $sevenRecharge = Db::name('recharge_order')->where('status', 1)->where('createtime', '>=', $sevenStart)->sum('amount'); } catch (\Exception $e) { $sevenRecharge = 0; }
        try { $sevenBet = Db::name('lottery_bet')->where('createtime', '>=', $sevenStart)->where('status', '<>', 3)->sum('total_amount'); } catch (\Exception $e) { $sevenBet = 0; }
        try { $sevenWin = Db::name('lottery_bet')->where('createtime', '>=', $sevenStart)->where('status', 1)->sum('win_amount'); } catch (\Exception $e) { $sevenWin = 0; }
        try { $sevenXima = Db::name('xima_record')->where('createtime', '>=', $sevenStart)->sum('xima_amount'); } catch (\Exception $e) { $sevenXima = 0; }
        try { $sevenCommission = Db::name('xima_record')->where('type', 'parent')->where('createtime', '>=', $sevenStart)->sum('xima_amount'); } catch (\Exception $e) { $sevenCommission = 0; }
        try { $sevenWithdraw = Db::name('withdraw_order')->where('status', 1)->where('createtime', '>=', $sevenStart)->sum('amount'); } catch (\Exception $e) { $sevenWithdraw = 0; }

        // 总计
        try { $totalRecharge = Db::name('recharge_order')->where('status', 1)->sum('amount'); } catch (\Exception $e) { $totalRecharge = 0; }
        try { $totalBet = Db::name('lottery_bet')->where('status', '<>', 3)->sum('total_amount'); } catch (\Exception $e) { $totalBet = 0; }
        try { $totalWin = Db::name('lottery_bet')->where('status', 1)->sum('win_amount'); } catch (\Exception $e) { $totalWin = 0; }
        try { $totalXima = Db::name('xima_record')->sum('xima_amount'); } catch (\Exception $e) { $totalXima = 0; }
        try { $totalCommission = Db::name('xima_record')->where('type', 'parent')->sum('xima_amount'); } catch (\Exception $e) { $totalCommission = 0; }
        try { $totalWithdraw = Db::name('withdraw_order')->where('status', 1)->sum('amount'); } catch (\Exception $e) { $totalWithdraw = 0; }
        try { $totalRebate = Db::name('rebate_record')->where('status', 1)->sum('rebate_amount'); } catch (\Exception $e) { $totalRebate = 0; }

        // 待处理订单
        try { $pendingRecharge = Db::name('recharge_order')->where('status', 0)->count(); } catch (\Exception $e) { $pendingRecharge = 0; }
        try { $pendingWithdraw = Db::name('withdraw_order')->where('status', 0)->count(); } catch (\Exception $e) { $pendingWithdraw = 0; }

        $todayProfit = round($todayBet - $todayWin - $todayCommission - $todayRebate - $todayXimaSelf, 2);

        // 7日趋势
        $trendDays = $trendRecharge = $trendBet = $trendWin = $trendProfit = [];
        for ($i = 6; $i >= 0; $i--) {
            $ds = $todayStart - 86400 * $i;
            $de = $ds + 86400;
            $trendDays[] = date('m-d', $ds);
            try { $dR = Db::name('recharge_order')->where('status', 1)->where('createtime', '>=', $ds)->where('createtime', '<', $de)->sum('amount'); } catch (\Exception $e) { $dR = 0; }
            try { $dB = Db::name('lottery_bet')->where('createtime', '>=', $ds)->where('createtime', '<', $de)->where('status', '<>', 3)->sum('total_amount'); } catch (\Exception $e) { $dB = 0; }
            try { $dW = Db::name('lottery_bet')->where('createtime', '>=', $ds)->where('createtime', '<', $de)->where('status', 1)->sum('win_amount'); } catch (\Exception $e) { $dW = 0; }
            try { $dC = Db::name('xima_record')->where('type', 'parent')->where('createtime', '>=', $ds)->where('createtime', '<', $de)->sum('xima_amount'); } catch (\Exception $e) { $dC = 0; }
            $trendRecharge[] = round($dR, 2);
            $trendBet[] = round($dB, 2);
            $trendWin[] = round($dW, 2);
            try { $dRb = Db::name('rebate_record')->where('status', 1)->where('createtime', '>=', $ds)->where('createtime', '<', $de)->sum('rebate_amount'); } catch (\Exception $e) { $dRb = 0; }
            try { $dXs = Db::name('xima_record')->where('type', 'self')->where('createtime', '>=', $ds)->where('createtime', '<', $de)->sum('xima_amount'); } catch (\Exception $e) { $dXs = 0; }
            $trendProfit[] = round($dB - $dW - $dC - $dRb - $dXs, 2);
        }

        $this->view->assign([
            'totaluser'         => User::count(),
            'totaladdon'        => $totaladdon,
            'totaladmin'        => Admin::count(),
            'totalcategory'     => \app\common\model\Category::count(),
            'todayusersignup'   => User::whereTime('jointime', 'today')->count(),
            'todayuserlogin'    => User::whereTime('logintime', 'today')->count(),
            'sevendau'          => User::whereTime('jointime|logintime|prevtime', '-7 days')->count(),
            'thirtydau'         => User::whereTime('jointime|logintime|prevtime', '-30 days')->count(),
            'threednu'          => User::whereTime('jointime', '-3 days')->count(),
            'sevendnu'          => User::whereTime('jointime', '-7 days')->count(),
            'dbtablenums'       => count($dbTableList),
            'dbsize'            => array_sum(array_map(function ($item) {
                return $item['Data_length'] + $item['Index_length'];
            }, $dbTableList)),
            'totalworkingaddon' => $totalworkingaddon,
            'attachmentnums'    => Attachment::count(),
            'attachmentsize'    => Attachment::sum('filesize'),
            'picturenums'       => Attachment::where('mimetype', 'like', 'image/%')->count(),
            'picturesize'       => Attachment::where('mimetype', 'like', 'image/%')->sum('filesize'),
            // 彩票业务统计
            'todayRecharge'     => round($todayRecharge, 2),
            'todayBet'          => round($todayBet, 2),
            'todayWin'          => round($todayWin, 2),
            'todayXima'         => round($todayXima, 2),
            'todayXimaSelf'     => round($todayXimaSelf, 2),
            'todayXimaParent'   => round($todayXimaParent, 2),
            'todayCommission'   => round($todayCommission, 2),
            'todayWithdraw'     => round($todayWithdraw, 2),
            'todayRebate'       => round($todayRebate, 2),
            'todayProfit'       => $todayProfit,
            'yesterdayRecharge' => round($yesterdayRecharge, 2),
            'yesterdayBet'      => round($yesterdayBet, 2),
            'yesterdayWin'      => round($yesterdayWin, 2),
            'pendingRecharge'   => $pendingRecharge,
            'pendingWithdraw'   => $pendingWithdraw,
            'totalRecharge'     => round($totalRecharge, 2),
            'totalBet'          => round($totalBet, 2),
            'totalWin'          => round($totalWin, 2),
            'totalXima'         => round($totalXima, 2),
            'totalCommission'   => round($totalCommission, 2),
            'totalWithdraw'     => round($totalWithdraw, 2),
            'totalRebate'       => round($totalRebate, 2),
            'sevenRecharge'     => round($sevenRecharge, 2),
            'sevenBet'          => round($sevenBet, 2),
            'sevenWin'          => round($sevenWin, 2),
            'sevenXima'         => round($sevenXima, 2),
            'sevenCommission'   => round($sevenCommission, 2),
            'sevenWithdraw'     => round($sevenWithdraw, 2),
        ]);

        $this->assignconfig('column', array_keys($userlist));
        $this->assignconfig('userdata', array_values($userlist));

        // 业务趋势数据传给前端 JS
        $this->assignconfig('trendDays', $trendDays);
        $this->assignconfig('trendRecharge', $trendRecharge);
        $this->assignconfig('trendBet', $trendBet);
        $this->assignconfig('trendWin', $trendWin);
        $this->assignconfig('trendProfit', $trendProfit);

        return $this->view->fetch();
    }

    /**
     * 轮询检查新充值/提现（AJAX）
     */
    public function pendingCheck()
    {
        try {
            $pendingRecharge = Db::name('recharge_order')->where('status', 0)->count();
            $pendingWithdraw = Db::name('withdraw_order')->where('status', 0)->count();
            // 最新一条待处理的ID（用于判断是否有新增）
            $lastRechargeId = Db::name('recharge_order')->where('status', 0)->order('id', 'desc')->value('id') ?: 0;
            $lastWithdrawId = Db::name('withdraw_order')->where('status', 0)->order('id', 'desc')->value('id') ?: 0;
            // 最新充值详情
            $latestRecharge = null;
            if ($pendingRecharge > 0) {
                $latestRecharge = Db::name('recharge_order')->alias('r')
                    ->join('user u', 'u.id = r.user_id', 'LEFT')
                    ->where('r.status', 0)->order('r.id', 'desc')
                    ->field('r.id, r.amount, u.nickname, u.username')
                    ->find();
            }
            $latestWithdraw = null;
            if ($pendingWithdraw > 0) {
                $latestWithdraw = Db::name('withdraw_order')->alias('w')
                    ->join('user u', 'u.id = w.user_id', 'LEFT')
                    ->where('w.status', 0)->order('w.id', 'desc')
                    ->field('w.id, w.amount, w.withdraw_type, u.nickname, u.username')
                    ->find();
            }
        } catch (\Exception $e) {
            $pendingRecharge = $pendingWithdraw = $lastRechargeId = $lastWithdrawId = 0;
            $latestRecharge = $latestWithdraw = null;
        }
        return json(['code' => 1, 'data' => [
            'pending_recharge' => $pendingRecharge,
            'pending_withdraw' => $pendingWithdraw,
            'last_recharge_id' => $lastRechargeId,
            'last_withdraw_id' => $lastWithdrawId,
            'latest_recharge'  => $latestRecharge,
            'latest_withdraw'  => $latestWithdraw,
        ]]);
    }

    /**
     * 按日期范围统计数据（AJAX）
     * 参数: start_date, end_date (Y-m-d)
     */
    public function rangeStats()
    {
        $startDate = $this->request->param('start_date', '');
        $endDate   = $this->request->param('end_date', '');

        if (empty($startDate) || empty($endDate)) {
            return json(['code' => 0, 'msg' => '请选择开始和结束日期']);
        }

        $startTime = strtotime($startDate . ' 00:00:00');
        $endTime   = strtotime($endDate . ' 23:59:59');

        if (!$startTime || !$endTime || $startTime > $endTime) {
            return json(['code' => 0, 'msg' => '日期范围无效']);
        }

        try {
            // 充值（已到账）
            $recharge = Db::name('recharge_order')->where('status', 1)
                ->where('createtime', 'between', [$startTime, $endTime])->sum('amount');
            // 投注总额（排除已撤单）
            $bet = Db::name('lottery_bet')
                ->where('createtime', 'between', [$startTime, $endTime])
                ->where('status', '<>', 3)->sum('total_amount');
            // 中奖
            $win = Db::name('lottery_bet')
                ->where('createtime', 'between', [$startTime, $endTime])
                ->where('status', 1)->sum('win_amount');
            // 推荐反佣（邀请人洗码）
            try { $commission = Db::name('xima_record')->where('type', 'parent')
                ->where('createtime', 'between', [$startTime, $endTime])->sum('xima_amount'); } catch (\Exception $e) { $commission = 0; }
            // 流水返利
            try { $rebate = Db::name('rebate_record')->where('status', 1)
                ->where('createtime', 'between', [$startTime, $endTime])->sum('rebate_amount'); } catch (\Exception $e) { $rebate = 0; }
            // 提现（已打款）
            $withdraw = Db::name('withdraw_order')->where('status', 1)
                ->where('createtime', 'between', [$startTime, $endTime])->sum('amount');
            // 投注笔数、投注人数
            $betCount = Db::name('lottery_bet')
                ->where('createtime', 'between', [$startTime, $endTime])
                ->where('status', '<>', 3)->count();
            $betUsers = Db::name('lottery_bet')
                ->where('createtime', 'between', [$startTime, $endTime])
                ->where('status', '<>', 3)->group('user_id')->count();

            // 自身洗码（每日生效的，不管是否领取）
            try { $ximaSelf = Db::name('xima_record')->where('type', 'self')
                ->where('createtime', 'between', [$startTime, $endTime])->sum('xima_amount'); } catch (\Exception $e) { $ximaSelf = 0; }
            // 平台盈亏 = 投注 - 中奖 - 反佣 - 返利 - 自身洗码
            $profit = round($bet - $win - $commission - $rebate - $ximaSelf, 2);

            return json(['code' => 1, 'data' => [
                'start_date'  => $startDate,
                'end_date'    => $endDate,
                'recharge'    => round($recharge, 2),
                'bet'         => round($bet, 2),
                'win'         => round($win, 2),
                'commission'  => round($commission, 2),
                'rebate'      => round($rebate, 2),
                'xima_self'   => round($ximaSelf, 2),
                'withdraw'    => round($withdraw, 2),
                'bet_count'   => $betCount,
                'bet_users'   => $betUsers,
                'profit'      => $profit,
            ]]);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '查询失败: ' . $e->getMessage()]);
        }
    }

}
