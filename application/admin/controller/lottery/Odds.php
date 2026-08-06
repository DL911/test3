<?php
namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use think\Db;

/**
 * 赔率管理
 * 管理福彩3D和排列三所有玩法的赔率
 */
class Odds extends Backend
{
    protected $noNeedRight = ['*'];

    // 彩种映射
    protected $lotteryNames = [1 => '福彩3D', 2 => '排列三'];

    // 玩法分类映射（排列三和福彩3D完全一致）
    protected $playTypeNames = [
        'baiwei' => '百位', 'shiwei' => '十位', 'gewei' => '个位',
        'shuangmian' => '总和属性', 'longhu' => '龙虎和', 'xingtai' => '形态',
        'yizi_zuhe' => '一字组合', 'erzi_zuhe' => '二字组合', 'sanzi_zuhe' => '三字组合',
        'yizi_dingwei' => '一字定位', 'erzi_dingwei' => '二字定位', 'sanzi_dingwei' => '三字定位',
        'erzi_heshu_baishi' => '百十和数', 'erzi_heshu_baige' => '百个和数', 'erzi_heshu_shige' => '十个和数',
        'erzi_heshu_baishi_ws' => '百十和数尾数', 'erzi_heshu_baige_ws' => '百个和数尾数', 'erzi_heshu_shige_ws' => '十个和数尾数',
        'hezhi' => '三字和值', 'hezhi_ws' => '三字和值尾数',
        'zusan' => '组三', 'zuliu' => '组六', 'kuadu' => '跨度',
        // 标准盘玩法
        'bzp_sanxing' => '【标准盘】三星', 'bzp_qianer' => '【标准盘】前二', 'bzp_houer' => '【标准盘】后二',
        'bzp_dingweidan' => '【标准盘】定位胆', 'bzp_budindan' => '【标准盘】不定胆', 'bzp_dxds' => '【标准盘】大小单双',
        // 双面盘扩展
        'shuangmian_combo' => '两两组合(百十/百个/十个)', 'shuangmian_total' => '总和扩展(百十个)',
    ];

    /**
     * 赔率列表页
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            try {
                $lotteryType = $this->request->param('lottery_type', '');
                $playType    = $this->request->param('play_type', '');
                $keyword     = $this->request->param('keyword', '');

                $where = [];
                if ($lotteryType !== '') {
                    $where['lottery_type'] = intval($lotteryType);
                }
                if ($playType !== '') {
                    $where['play_type'] = $playType;
                }

                $betKey = $this->request->param('bet_key', '');
                if ($betKey !== '') {
                    $where['bet_key'] = $betKey;
                }

                // 计算总数
                $countQ = Db::name('lottery_odds')->where($where);
                if ($keyword) {
                    $countQ->where('bet_name|bet_key', 'like', "%{$keyword}%");
                }
                $total = $countQ->count();

                // 分页查询
                $offset = intval($this->request->param('offset', 0));
                $limit  = intval($this->request->param('limit', 50));

                $listQ = Db::name('lottery_odds')->where($where);
                if ($keyword) {
                    $listQ->where('bet_name|bet_key', 'like', "%{$keyword}%");
                }
                $list = $listQ->order('lottery_type asc, play_type asc, id asc')
                    ->limit($offset, $limit)
                    ->select();

                foreach ($list as &$item) {
                    $item['lottery_name'] = isset($this->lotteryNames[$item['lottery_type']])
                        ? $this->lotteryNames[$item['lottery_type']] : '未知';
                    $item['play_type_name'] = isset($this->playTypeNames[$item['play_type']])
                        ? $this->playTypeNames[$item['play_type']] : $item['play_type'];
                    $item['status_text'] = $item['status'] == 1 ? '启用' : '禁用';
                }

                return json(['total' => $total, 'rows' => $list]);
            } catch (\Exception $e) {
                return json(['total' => 0, 'rows' => [], 'error' => $e->getMessage()]);
            }
        }

        $this->view->assign('lotteryNames', $this->lotteryNames);
        $this->view->assign('playTypeNames', $this->playTypeNames);
        return $this->view->fetch();
    }

    /**
     * 获取指定玩法下的所有子项(bet_key+bet_name)
     */
    public function subkeys()
    {
        $lotteryType = $this->request->param('lottery_type', '');
        $playType    = $this->request->param('play_type', '');
        $where = [];
        if ($lotteryType !== '') $where['lottery_type'] = intval($lotteryType);
        if ($playType !== '') $where['play_type'] = $playType;
        $list = Db::name('lottery_odds')->where($where)
            ->field('bet_key, bet_name, odds, max_odds')
            ->order('id asc')
            ->select();
        return json($list);
    }

    /**
     * 编辑赔率（单条）
     */
    public function edit($id = null)
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (empty($data['id'])) {
                $this->error('参数错误');
            }

            $updateData = [
                'odds'       => floatval($data['odds']),
                'max_odds'   => isset($data['max_odds']) ? floatval($data['max_odds']) : 0,
                'status'     => intval($data['status']),
                'updatetime' => time(),
            ];

            Db::name('lottery_odds')->where('id', $data['id'])->update($updateData);
            $this->success('修改成功');
        }

        $row = $id ? Db::name('lottery_odds')->where('id', $id)->find() : [];
        if ($row) {
            $row['lottery_name'] = isset($this->lotteryNames[$row['lottery_type']])
                ? $this->lotteryNames[$row['lottery_type']] : '未知';
            $row['play_type_name'] = isset($this->playTypeNames[$row['play_type']])
                ? $this->playTypeNames[$row['play_type']] : $row['play_type'];
        }
        $this->view->assign('row', $row);
        return $this->view->fetch();
    }

    /**
     * 批量修改赔率
     * POST: lottery_type, play_type, odds
     */
    public function batchUpdate()
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }

        $lotteryType = $this->request->post('lottery_type/d', 0);
        $playType    = $this->request->post('play_type', '');
        $odds        = $this->request->post('odds', '');

        if (!$lotteryType || !$playType || $odds === '') {
            $this->error('参数不完整');
        }

        $oddsVal = floatval($odds);
        if ($oddsVal <= 0) {
            $this->error('赔率必须大于0');
        }

        $count = Db::name('lottery_odds')
            ->where('lottery_type', $lotteryType)
            ->where('play_type', $playType)
            ->update([
                'odds'       => $oddsVal,
                'updatetime' => time(),
            ]);

        $this->success("成功更新 {$count} 条赔率");
    }

    /**
     * 批量启用/禁用
     */
    public function batchStatus()
    {
        $ids    = $this->request->post('ids', '');
        $status = $this->request->post('status/d', 1);

        if (empty($ids)) {
            $this->error('请选择要操作的记录');
        }

        Db::name('lottery_odds')
            ->where('id', 'in', $ids)
            ->update(['status' => $status, 'updatetime' => time()]);

        $this->success('操作成功');
    }

    /**
     * 逐项批量更新赔率
     * POST: lottery_type, play_type, items (JSON: [{bet_key, odds}, ...])
     */
    public function batchUpdateItems()
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        // 安全清除之前的输出
        if (ob_get_level()) @ob_end_clean();
        ob_start();

        $lotteryType = $this->request->post('lottery_type/d', 0);
        $playType    = $this->request->post('play_type', '');
        $itemsJson   = $this->request->post('items', '');

        if (!$lotteryType || !$playType || !$itemsJson) {
            $this->error('参数不完整');
        }

        $items = json_decode($itemsJson, true);
        if (!is_array($items) || empty($items)) {
            $this->error('赔率数据无效');
        }

        $updated = 0;
        foreach ($items as $item) {
            if (empty($item['bet_key']) || !isset($item['odds'])) continue;
            $odds = floatval($item['odds']);
            if ($odds <= 0) continue;
            $maxOdds = isset($item['max_odds']) ? floatval($item['max_odds']) : 0;

            // 先检查记录是否存在
            $exists = Db::name('lottery_odds')
                ->where('lottery_type', $lotteryType)
                ->where('play_type', $playType)
                ->where('bet_key', $item['bet_key'])
                ->find();

            if ($exists) {
                Db::name('lottery_odds')
                    ->where('id', $exists['id'])
                    ->update(['odds' => $odds, 'max_odds' => $maxOdds, 'updatetime' => time()]);
                $updated++;
            }
        }

        $this->success("成功更新 {$updated} 条赔率");
    }
}
