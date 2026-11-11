<?php

namespace app\common\service;

use think\Db;

/** 新代理体系市场报表；只读，不参与投注和派奖。 */
class AgentMarketReportService
{
    public static function report($agentId, $params)
    {
        $agent = Db::name('user')->where('id', intval($agentId))->where('agent_level', '>', 0)->where('is_agent', 1)->find();
        if (!$agent) throw new \InvalidArgumentException('代理不存在');
        $start = isset($params['date_start']) ? $params['date_start'] : date('Y-m-d', strtotime('-6 days'));
        $end = isset($params['date_end']) ? $params['date_end'] : date('Y-m-d');
        if (!self::validDate($start) || !self::validDate($end) || strtotime($start) > strtotime($end) || strtotime($end) - strtotime($start) > 31 * 86400) {
            throw new \InvalidArgumentException('请选择不超过31天的有效日期范围');
        }
        $from = strtotime($start); $to = strtotime($end) + 86399;
        $branches = []; $frontier = [intval($agentId)]; $seen = [intval($agentId) => true];
        for ($depth = 1; $depth <= 100 && $frontier; $depth++) {
            $rows = Db::name('user')->where('pid', 'in', $frontier)->where('agent_level', '>', 0)->field('id,pid')->select();
            $frontier = [];
            foreach ($rows as $row) {
                $id = intval($row['id']); $pid = intval($row['pid']);
                if (isset($seen[$id])) continue;
                $seen[$id] = true; $frontier[] = $id;
                $branches[$id] = $pid === intval($agentId) ? $id : $branches[$pid];
            }
        }
        $directId = isset($params['direct_id']) ? intval($params['direct_id']) : 0;
        if ($directId && !isset($branches[$directId])) throw new \InvalidArgumentException('直属分支不属于该代理');
        $ids = array_keys($branches);
        if ($directId) $ids = array_values(array_filter($ids, function ($id) use ($branches, $directId) { return $branches[$id] === $directId; }));
        $userId = isset($params['user_id']) ? intval($params['user_id']) : 0;
        if ($userId && !in_array($userId, $ids, true)) throw new \InvalidArgumentException('用户不属于该代理市场');
        if ($userId) $ids = [$userId];
        $keyword = trim((string)(isset($params['user_keyword']) ? $params['user_keyword'] : ''));
        if ($keyword !== '' && !$userId) {
            $matched = Db::name('user')->where('id', 'in', $ids ?: [0])->where('username|nickname', 'like', '%' . addcslashes($keyword, '%_\\') . '%')->column('id');
            $ids = array_map('intval', $matched);
        }
        $type = isset($params['lottery_type']) ? intval($params['lottery_type']) : 0;
        if ($type && !in_array($type, [1, 2], true)) throw new \InvalidArgumentException('彩种不正确');
        $page = max(1, isset($params['page']) ? intval($params['page']) : 1);
        $limit = min(50, max(1, isset($params['limit']) ? intval($params['limit']) : 20));
        $zero = ['order_count'=>0,'bet_amount'=>0,'win_amount'=>0,'lost_amount'=>0,'winning_bet_amount'=>0,'pending_amount'=>0,'settled_difference'=>0];
        $userPage = max(1, isset($params['user_page']) ? intval($params['user_page']) : 1);
        $result = ['agent'=>['id'=>$agent['id'],'username'=>$agent['username']], 'scope'=>$userId ? 'user' : 'market', 'date_start'=>$start,'date_end'=>$end,'summary'=>$zero,'users'=>[],'user_total'=>0,'user_page'=>$userPage,'money_logs'=>[],'money_log_total'=>0,'page'=>$page,'limit'=>$limit,'generated_at'=>date('Y-m-d H:i:s')];
        if (!$ids) return $result;
        $query = Db::name('lottery_bet')->alias('b')->where('b.user_id', 'in', $ids)->where('b.createtime', 'between', [$from, $to]);
        if ($type) $query->where('b.lottery_type', $type);
        $result['summary']['order_count'] = (clone $query)->where('b.status', '<>', 3)->count();
        $result['summary']['bet_amount'] = round(floatval((clone $query)->where('b.status', '<>', 3)->sum('b.total_amount')), 2);
        $result['summary']['pending_amount'] = round(floatval((clone $query)->where('b.status', 0)->sum('b.total_amount')), 2);
        $result['summary']['winning_bet_amount'] = round(floatval((clone $query)->where('b.status', 1)->sum('b.total_amount')), 2);
        $result['summary']['win_amount'] = round(floatval((clone $query)->where('b.status', 1)->sum('b.win_amount')), 2);
        $result['summary']['lost_amount'] = round(floatval((clone $query)->where('b.status', 2)->sum('b.total_amount')), 2);
        $s = &$result['summary'];
        $s['settled_difference'] = round($s['winning_bet_amount'] + $s['lost_amount'] - $s['win_amount'], 2);
        unset($s);
        $result['user_total'] = Db::name('user')->where('id', 'in', $ids)->count();
        if ($result['user_total']) {
            $users = Db::name('user')->where('id', 'in', $ids)->field('id,username')->order('id desc')->page($userPage, 20)->select();
            foreach ($users as $person) {
                $uid = intval($person['id']);
                $personal = (clone $query)->where('b.user_id', $uid);
                $result['users'][] = [
                    'user_id' => $uid, 'username' => $person['username'],
                    'order_count' => (clone $personal)->where('b.status', '<>', 3)->count(),
                    'bet_amount' => round(floatval((clone $personal)->where('b.status', '<>', 3)->sum('b.total_amount')), 2),
                    'win_amount' => round(floatval((clone $personal)->where('b.status', 1)->sum('b.win_amount')), 2),
                    'lost_amount' => round(floatval((clone $personal)->where('b.status', 2)->sum('b.total_amount')), 2),
                ];
            }
        }
        if ($userId) {
            $logs = Db::name('user_money_log')->where('user_id', $userId)->where('createtime', 'between', [$from, $to]);
            $result['money_log_total'] = (clone $logs)->count();
            $result['money_logs'] = (clone $logs)->order('id desc')->page($page, $limit)->select();
        }
        return $result;
    }

    private static function validDate($value)
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
        $parts = array_map('intval', explode('-', $value));
        return checkdate($parts[1], $parts[2], $parts[0]);
    }
}
