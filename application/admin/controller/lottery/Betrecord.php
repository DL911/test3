<?php

namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use think\Db;

/**
 * 会员投注记录管理
 */
class Betrecord extends Backend
{
    protected $model = null;
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 投注记录列表
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $page      = $this->request->param('page/d', 1);
            $limit     = $this->request->param('limit/d', 20);
            $status    = $this->request->param('status', '');
            $keyword   = $this->request->param('keyword', '');
            $type      = $this->request->param('lottery_type', '');
            $period    = $this->request->param('period', '');
            $dateStart = $this->request->param('date_start', '');
            $dateEnd   = $this->request->param('date_end', '');

            $where = [];
            if ($status !== '') $where['b.status'] = intval($status);
            if ($type !== '') $where['b.lottery_type'] = intval($type);
            if ($period) $where['b.period'] = $period;
            if ($keyword) {
                $where['b.order_no|u.username|u.nickname'] = ['like', "%{$keyword}%"];
            }
            if ($dateStart) {
                $where['b.createtime'] = ['>=', strtotime($dateStart)];
            }
            if ($dateEnd) {
                if (isset($where['b.createtime'])) {
                    $where['b.createtime'] = ['between', [strtotime($dateStart), strtotime($dateEnd) + 86399]];
                } else {
                    $where['b.createtime'] = ['<=', strtotime($dateEnd) + 86399];
                }
            }

            $list = Db::name('lottery_bet')
                ->alias('b')
                ->join('user u', 'u.id = b.user_id', 'LEFT')
                ->where($where)
                ->field('b.*, u.username, u.nickname')
                ->order('b.createtime', 'desc')
                ->page($page, $limit)
                ->select();

            $total = Db::name('lottery_bet')
                ->alias('b')
                ->join('user u', 'u.id = b.user_id', 'LEFT')
                ->where($where)
                ->count();

            // 统计总金额
            $totalAmount = Db::name('lottery_bet')
                ->alias('b')
                ->join('user u', 'u.id = b.user_id', 'LEFT')
                ->where($where)
                ->sum('b.total_amount');

            $totalWin = Db::name('lottery_bet')
                ->alias('b')
                ->join('user u', 'u.id = b.user_id', 'LEFT')
                ->where($where)
                ->sum('b.win_amount');

            $typeMap = [1 => '福彩3D', 2 => '排列三'];
            $statusMap = [0 => '待开奖', 1 => '已中奖', 2 => '未中奖', 3 => '已取消'];
            $playMap = [
                'kuaijie'=>'快捷','shuangmian'=>'双面','daxiao'=>'大小','danshuang'=>'单双',
                'longhu'=>'龙虎','zonghe_daxiao'=>'总和大小','zonghe_danshuang'=>'总和单双',
                'biaozhun'=>'标准','dingweidan'=>'定位胆',
                'yizi_dingwei'=>'一字定位','erzi_dingwei'=>'二字定位','sanzi_dingwei'=>'三字定位',
                'yizi_zuhe'=>'一字组合','erzi_zuhe'=>'二字组合','sanzi_zuhe'=>'三字组合',
                'erzi_heshu'=>'二字和数','kuadu'=>'跨度','xingtai'=>'形态',
                'zusan'=>'组三','zuliu'=>'组六','hezhi'=>'和值','zhixuan'=>'直选','danshi'=>'单式',
                'sx_zx_fushi'=>'三星直选复式','sx_zx_danshi'=>'三星直选单式',
                'sx_zx_hezhi'=>'三星直选和值','sx_zx_hezhi2'=>'三星组选和值',
                'sx_zx_kuadu'=>'三星直选跨度','sx_hzweishu'=>'三星和值尾数',
                'sx_zx3_fushi'=>'组三复式','sx_zx3_danshi'=>'组三单式',
                'sx_zx6_fushi'=>'组六复式','sx_zx6_danshi'=>'组六单式',
                'sx_hunhe'=>'三星混合组选','sx_zx_baodan'=>'三星组选包胆',
                'sx_budingdan'=>'三星不定胆',
                'sx_yimabuding'=>'三星一码不定胆','sx_ermabuding'=>'三星二码不定胆',
                'dxds'=>'大小单双',
                'qe_zx_fushi'=>'前二直选复式','qe_zx_danshi'=>'前二直选单式','qe_zx_hezhi'=>'前二直选和值','qe_zx_kuadu'=>'前二直选跨度',
                'qe_zuxuan_fushi'=>'前二组选复式','qe_zuxuan_danshi'=>'前二组选单式',
                'qe_zuxuan_hezhi'=>'前二组选和值','qe_zuxuan_baodan'=>'前二组选包胆',
                'he_zx_fushi'=>'后二直选复式','he_zx_danshi'=>'后二直选单式','he_zx_hezhi'=>'后二直选和值','he_zx_kuadu'=>'后二直选跨度',
                'he_zuxuan_fushi'=>'后二组选复式','he_zuxuan_danshi'=>'后二组选单式',
                'he_zuxuan_hezhi'=>'后二组选和值','he_zuxuan_baodan'=>'后二组选包胆',
            ];
            $betKeyMap = [
                // 基础属性
                'da'=>'大','xiao'=>'小','dan'=>'单','shuang'=>'双','zhi'=>'质','he'=>'合',
                'long'=>'龙','hu'=>'虎',
                'baozi'=>'豹子','shunzi'=>'顺子','duizi'=>'对子','banshun'=>'半顺','zaliu'=>'杂六',
                // 总和
                'zonghe_da'=>'总和大','zonghe_xiao'=>'总和小','zonghe_dan'=>'总和单','zonghe_shuang'=>'总和双',
                'zonghe_heda'=>'总和大','zonghe_hexiao'=>'总和小','zonghe_hedan'=>'总和单','zonghe_heshuang'=>'总和双',
                'zonghe_heweida'=>'总和尾大','zonghe_heweixiao'=>'总和尾小',
                'zonghe_heweidan'=>'总和尾单','zonghe_heweishuang'=>'总和尾双',
                'zonghe_heweizhi'=>'总和尾质','zonghe_heweihe'=>'总和尾合',
                // 百位
                'bai_da'=>'百位大','bai_xiao'=>'百位小','bai_dan'=>'百位单','bai_shuang'=>'百位双',
                'bai_zhi'=>'百位质','bai_he'=>'百位合',
                // 十位
                'shi_da'=>'十位大','shi_xiao'=>'十位小','shi_dan'=>'十位单','shi_shuang'=>'十位双',
                'shi_zhi'=>'十位质','shi_he'=>'十位合',
                // 个位
                'ge_da'=>'个位大','ge_xiao'=>'个位小','ge_dan'=>'个位单','ge_shuang'=>'个位双',
                'ge_zhi'=>'个位质','ge_he'=>'个位合',
                // 百十和数
                'baishi_heda'=>'百十和大','baishi_hexiao'=>'百十和小',
                'baishi_hedan'=>'百十和单','baishi_heshuang'=>'百十和双',
                'baishi_heweida'=>'百十和尾大','baishi_heweixiao'=>'百十和尾小',
                'baishi_heweidan'=>'百十和尾单','baishi_heweishuang'=>'百十和尾双',
                'baishi_heweizhi'=>'百十和尾质','baishi_heweihe'=>'百十和尾合',
                // 百个和数
                'baige_heda'=>'百个和大','baige_hexiao'=>'百个和小',
                'baige_hedan'=>'百个和单','baige_heshuang'=>'百个和双',
                'baige_heweida'=>'百个和尾大','baige_heweixiao'=>'百个和尾小',
                'baige_heweidan'=>'百个和尾单','baige_heweishuang'=>'百个和尾双',
                'baige_heweizhi'=>'百个和尾质','baige_heweihe'=>'百个和尾合',
                // 十个和数
                'shige_heda'=>'十个和大','shige_hexiao'=>'十个和小',
                'shige_hedan'=>'十个和单','shige_heshuang'=>'十个和双',
                'shige_heweida'=>'十个和尾大','shige_heweixiao'=>'十个和尾小',
                'shige_heweidan'=>'十个和尾单','shige_heweishuang'=>'十个和尾双',
                'shige_heweizhi'=>'十个和尾质','shige_heweihe'=>'十个和尾合',
            ];

            foreach ($list as &$item) {
                $item['create_date'] = date('Y-m-d H:i:s', $item['createtime']);
                $item['lottery_name'] = isset($typeMap[$item['lottery_type']]) ? $typeMap[$item['lottery_type']] : '未知';
                $item['status_text'] = isset($statusMap[$item['status']]) ? $statusMap[$item['status']] : '未知';
                $item['win_amount'] = isset($item['win_amount']) ? $item['win_amount'] : 0;
                $item['play_name'] = isset($playMap[$item['play_type']]) ? $playMap[$item['play_type']] : ($item['play_type'] ?? '-');
                // play_sub → 中文附加
                $subLabelMap = ['baiwei'=>'百位','shiwei'=>'十位','gewei'=>'个位',
                    'baishi'=>'百十','baige'=>'百个','shige'=>'十个','zonghe'=>'总和','longhu'=>'龙虎'];
                if (!empty($item['play_sub']) && isset($subLabelMap[$item['play_sub']])) {
                    $item['play_name'] .= '·' . $subLabelMap[$item['play_sub']];
                }
                $item['draw_result'] = $item['draw_result'] ?? '-';

                // 解析投注内容
                $betDisplay = '-';
                if (!empty($item['bet_content'])) {
                    $bets = json_decode($item['bet_content'], true);
                    if (is_array($bets)) {
                        $posMap = ['bai'=>'百位','shi'=>'十位','ge'=>'个位'];
                        $grpMap = ['q3'=>'前三','z3'=>'中三','h3'=>'后三'];
                        $parts = [];
                        $posNames = ['0'=>'百','1'=>'十','2'=>'个','3'=>'千','4'=>'万'];

                        // 快捷/双面玩法：从play_sub获取位置前缀
                        $subPrefix = '';
                        if (in_array($item['play_type'], ['kuaijie', 'shuangmian']) && !empty($item['play_sub'])) {
                            $subMap = ['baiwei'=>'百位','shiwei'=>'十位','gewei'=>'个位',
                                       'bai'=>'百位','shi'=>'十位','ge'=>'个位',
                                       'zonghe'=>'总和','longhu'=>'龙虎'];
                            $subPrefix = $subMap[$item['play_sub']] ?? '';
                        }

                        // erzi_dingwei位置映射
                        $erziDwMap = ['baishi'=>['0'=>'百','1'=>'十'],'baige'=>['0'=>'百','1'=>'个'],'shige'=>['0'=>'十','1'=>'个']];
                        $erziDwLabels = ($item['play_type'] === 'erzi_dingwei' && !empty($item['play_sub']) && isset($erziDwMap[$item['play_sub']])) ? $erziDwMap[$item['play_sub']] : null;

                        // erzi_heshu位置前缀
                        $ehSubMap = ['baishi'=>'百十','baige'=>'百个','shige'=>'十个'];
                        $ehPrefix = ($item['play_type'] === 'erzi_heshu' && !empty($item['play_sub']) && isset($ehSubMap[$item['play_sub']])) ? $ehSubMap[$item['play_sub']] : '';

                        // 组选类：不分位置，p0_N格式只显示数字
                        $noPosFix = in_array($item['play_type'], [
                            'sx_zx3_fushi','sx_zx6_fushi','sx_budingdan',
                            'qe_zuxuan_fushi','qe_zuxuan_danshi','qe_zuxuan_baodan',
                            'he_zuxuan_fushi','he_zuxuan_danshi','he_zuxuan_baodan',
                        ]);

                        // 后二类：pos0=十, pos1=个
                        if (strpos($item['play_type'], 'he_zx') === 0 || strpos($item['play_type'], 'he_zuxuan') === 0) {
                            $posNames = ['0'=>'十','1'=>'个'];
                        }
                        // 前二类：pos0=百, pos1=十（与默认一致，显式声明）
                        if (strpos($item['play_type'], 'qe_zx') === 0 || strpos($item['play_type'], 'qe_zuxuan') === 0) {
                            $posNames = ['0'=>'百','1'=>'十'];
                        }

                        foreach ($bets as $b) {
                            $k = is_array($b) ? ($b['key'] ?? '') : $b;
                            // 快捷多选位置时，优先使用item自带的sub字段
                            $itemPrefix = $subPrefix;
                            if (is_array($b) && !empty($b['sub'])) {
                                $itemSubMap = ['baiwei'=>'百位','shiwei'=>'十位','gewei'=>'个位',
                                               'bai'=>'百位','shi'=>'十位','ge'=>'个位'];
                                $itemPrefix = $itemSubMap[$b['sub']] ?? $subPrefix;
                            }
                            // p0_5 格式
                            if (preg_match('/^p(\d+)_(\d+)$/', $k, $m)) {
                                if ($noPosFix) {
                                    $parts[] = $m[2]; // 组选只显示数字
                                } else {
                                    $pLabel = $erziDwLabels ? ($erziDwLabels[$m[1]] ?? $posNames[$m[1]] ?? '位'.$m[1]) : ($posNames[$m[1]] ?? '位'.$m[1]);
                                    $parts[] = $pLabel . $m[2];
                                }
                                continue;
                            }
                            // ehz_0-4, ehz_5 → 和0-4, 和5 (二字和数)
                            if (preg_match('/^ehz_(.+)$/', $k, $m)) { $parts[] = $ehPrefix . '和' . $m[1]; continue; }
                            // ehzws_0 → 和尾0 (二字和数尾)
                            if (preg_match('/^ehzws_(\d+)$/', $k, $m)) { $parts[] = $ehPrefix . '和尾' . $m[1]; continue; }
                            // hzws_5 格式 → 和尾5 (三字和数尾数)
                            if (preg_match('/^hzws_(\d+)$/', $k, $m)) { $parts[] = '和尾' . $m[1]; continue; }
                            // hz_5 格式 → 和5
                            if (preg_match('/^hz_(\d+)$/', $k, $m)) { $parts[] = '和' . $m[1]; continue; }
                            // yz_5 格式 → 5 (一字组合)
                            if (preg_match('/^yz_(\d)$/', $k, $m)) { $parts[] = $m[1]; continue; }
                            // num_0 ~ num_9
                            if (preg_match('/^num_(\d)$/', $k, $m)) { $parts[] = ($itemPrefix ? $itemPrefix : '') . $m[1]; continue; }
                            // 纯数字
                            if (preg_match('/^\d+$/', $k)) { $parts[] = $k; continue; }
                            // bai_0 ~ bai_9, shi_0 ~ shi_9, ge_0 ~ ge_9
                            if (preg_match('/^(bai|shi|ge)_(\d)$/', $k, $m)) { $parts[] = ($posMap[$m[1]] ?? $m[1]) . $m[2]; continue; }
                            // ez_12 → 12 (二字组合), sz_123 → 123 (三字组合)
                            if (preg_match('/^[es]z_(\d+)$/', $k, $m)) { $parts[] = $m[1]; continue; }
                            // q3_baozi, h3_shunzi 等
                            if (preg_match('/^(q3|z3|h3)_(.+)$/', $k, $m)) { $parts[] = ($grpMap[$m[1]] ?? $m[1]) . ($betKeyMap[$m[2]] ?? $m[2]); continue; }
                            // kd_0 ~ kd_9 (跨度)
                            if (preg_match('/^kd_(\d)$/', $k, $m)) { $parts[] = '跨' . $m[1]; continue; }
                            // 直接映射（大/小/单/双等）- 加位置前缀
                            $label = $betKeyMap[$k] ?? $k;
                            $parts[] = $itemPrefix ? ($itemPrefix . $label) : $label;
                        }
                        $betDisplay = implode(' ', $parts);
                    } else {
                        $betDisplay = mb_substr($item['bet_content'], 0, 30);
                    }
                }
                $item['bet_display'] = $betDisplay;
            }

            $this->success('', '', [
                'list'         => $list,
                'total'        => $total,
                'total_amount' => round($totalAmount, 2),
                'total_win'    => round($totalWin, 2),
            ]);
        }

        return $this->view->fetch();
    }

    /**
     * 查看投注详情
     */
    public function detail()
    {
        $id = $this->request->param('id/d', 0);
        $bet = Db::name('lottery_bet')
            ->alias('b')
            ->join('user u', 'u.id = b.user_id', 'LEFT')
            ->where('b.id', $id)
            ->field('b.*, u.username, u.nickname')
            ->find();

        if (!$bet) {
            return json(['code' => 0, 'msg' => '记录不存在']);
        }

        $bet['create_date'] = date('Y-m-d H:i:s', $bet['createtime']);
        $bet['bet_content_arr'] = json_decode($bet['bet_content'], true);
        $bet['draw_result'] = $bet['draw_result'] ?? '-';

        // 投注号码可读
        $betKeyMap = [
            'da'=>'大','xiao'=>'小','dan'=>'单','shuang'=>'双','zhi'=>'质','he'=>'合',
            'long'=>'龙','hu'=>'虎',
            'baozi'=>'豹子','shunzi'=>'顺子','duizi'=>'对子','banshun'=>'半顺','zaliu'=>'杂六',
            'zonghe_da'=>'总和大','zonghe_xiao'=>'总和小','zonghe_dan'=>'总和单','zonghe_shuang'=>'总和双',
            'bai_da'=>'百位大','bai_xiao'=>'百位小','bai_dan'=>'百位单','bai_shuang'=>'百位双',
            'shi_da'=>'十位大','shi_xiao'=>'十位小','shi_dan'=>'十位单','shi_shuang'=>'十位双',
            'ge_da'=>'个位大','ge_xiao'=>'个位小','ge_dan'=>'个位单','ge_shuang'=>'个位双',
        ];
        $posMap = ['bai'=>'百位','shi'=>'十位','ge'=>'个位'];
        $posNames = ['0'=>'百','1'=>'十','2'=>'个','3'=>'千','4'=>'万'];
        $grpMap = ['q3'=>'前三','z3'=>'中三','h3'=>'后三'];
        $betDisplay = '-';
        if (is_array($bet['bet_content_arr'])) {
            $parts = [];
            // erzi_dingwei位置映射
            $erziDwMap = ['baishi'=>['0'=>'百','1'=>'十'],'baige'=>['0'=>'百','1'=>'个'],'shige'=>['0'=>'十','1'=>'个']];
            $erziDwLabels = ($bet['play_type'] === 'erzi_dingwei' && !empty($bet['play_sub']) && isset($erziDwMap[$bet['play_sub']])) ? $erziDwMap[$bet['play_sub']] : null;
            // erzi_heshu位置前缀
            $ehSubMap = ['baishi'=>'百十','baige'=>'百个','shige'=>'十个'];
            $ehPrefix = ($bet['play_type'] === 'erzi_heshu' && !empty($bet['play_sub']) && isset($ehSubMap[$bet['play_sub']])) ? $ehSubMap[$bet['play_sub']] : '';

            // 组选类不分位置
            $noPosFix = in_array($bet['play_type'], [
                'sx_zx3_fushi','sx_zx6_fushi','sx_budingdan',
                'qe_zuxuan_fushi','qe_zuxuan_danshi','qe_zuxuan_baodan',
                'he_zuxuan_fushi','he_zuxuan_danshi','he_zuxuan_baodan',
            ]);

            foreach ($bet['bet_content_arr'] as $b) {
                $k = is_array($b) ? ($b['key'] ?? '') : $b;
                if (preg_match('/^p(\d+)_(\d+)$/', $k, $m)) {
                    if ($noPosFix) {
                        $parts[] = $m[2];
                    } else {
                        $pLabel = $erziDwLabels ? ($erziDwLabels[$m[1]] ?? $posNames[$m[1]] ?? '位'.$m[1]) : ($posNames[$m[1]] ?? '位'.$m[1]);
                        $parts[] = $pLabel . $m[2];
                    }
                    continue;
                }
                if (preg_match('/^ehz_(.+)$/', $k, $m)) { $parts[] = $ehPrefix . '和' . $m[1]; continue; }
                if (preg_match('/^ehzws_(\d+)$/', $k, $m)) { $parts[] = $ehPrefix . '和尾' . $m[1]; continue; }
                if (preg_match('/^hzws_(\d+)$/', $k, $m)) { $parts[] = '和尾' . $m[1]; continue; }
                if (preg_match('/^hz_(\d+)$/', $k, $m)) { $parts[] = '和' . $m[1]; continue; }
                if (preg_match('/^yz_(\d)$/', $k, $m)) { $parts[] = $m[1]; continue; }
                if (preg_match('/^kd_(\d)$/', $k, $m)) { $parts[] = '跨' . $m[1]; continue; }
                if (preg_match('/^num_(\d)$/', $k, $m)) { $parts[] = $m[1]; continue; }
                if (preg_match('/^\d+$/', $k)) { $parts[] = $k; continue; }
                if (preg_match('/^(bai|shi|ge)_(\d)$/', $k, $m)) { $parts[] = ($posMap[$m[1]] ?? $m[1]) . $m[2]; continue; }
                if (preg_match('/^[es]z_(\d+)$/', $k, $m)) { $parts[] = $m[1]; continue; }
                if (preg_match('/^(q3|z3|h3)_(.+)$/', $k, $m)) { $parts[] = ($grpMap[$m[1]] ?? $m[1]) . ($betKeyMap[$m[2]] ?? $m[2]); continue; }
                $parts[] = $betKeyMap[$k] ?? $k;
            }
            $betDisplay = implode(' ', $parts);
        }
        $bet['bet_display'] = $betDisplay;

        return json(['code' => 1, 'data' => $bet]);
    }

    /**
     * 管理员撤销投注
     */
    public function cancel()
    {
        $id = $this->request->post('id/d', 0);
        if (!$id) return json(['code' => 0, 'msg' => '缺少参数']);

        $bet = Db::name('lottery_bet')->where('id', $id)->find();
        if (!$bet) return json(['code' => 0, 'msg' => '记录不存在']);
        if ($bet['status'] == 3) return json(['code' => 0, 'msg' => '该订单已撤销']);

        Db::startTrans();
        try {
            // 待开奖(0) 或 未中奖(2)：退还投注金额
            if ($bet['status'] == 0 || $bet['status'] == 2) {
                Db::name('user')->where('id', $bet['user_id'])->setInc('money', $bet['total_amount']);
            }
            // 已中奖(1)：扣回中奖金额 + 退还投注金额
            if ($bet['status'] == 1 && $bet['win_amount'] > 0) {
                Db::name('user')->where('id', $bet['user_id'])->setDec('money', $bet['win_amount']);
                Db::name('user')->where('id', $bet['user_id'])->setInc('money', $bet['total_amount']);
            }

            Db::name('lottery_bet')->where('id', $id)->update([
                'status' => 3,
                'updatetime' => time(),
            ]);

            // 撤销相关代理佣金（优先用 bet_id 精确匹配，备选 remark 模糊匹配）
            try {
                $commission = Db::name('lottery_commission')->where('bet_id', $id)->find();
                if (!$commission) {
                    $commission = Db::name('lottery_commission')->where('remark', 'like', "%{$bet['order_no']}%")->find();
                }
                if ($commission) {
                    Db::name('user')->where('id', $commission['user_id'])->setDec('money', $commission['amount']);
                    Db::name('lottery_commission')->where('id', $commission['id'])->delete();
                }
            } catch (\Exception $e) {}

            // 撤销关联洗码记录
            try {
                $ximaRecords = Db::name('xima_record')->where('bet_order_no', $bet['order_no'])->select();
                foreach ($ximaRecords as $xr) {
                    if ($xr['status'] == 1 && $xr['xima_amount'] > 0) {
                        Db::name('user')->where('id', $xr['user_id'])->setDec('money', $xr['xima_amount']);
                    }
                }
                Db::name('xima_record')->where('bet_order_no', $bet['order_no'])->delete();
            } catch (\Exception $e) {}

            Db::commit();
            return json(['code' => 1, 'msg' => '撤销成功，已退还 ¥' . $bet['total_amount']]);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 0, 'msg' => '操作失败: ' . $e->getMessage()]);
        }
    }
}
