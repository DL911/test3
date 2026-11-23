<?php

namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use app\common\library\Auth as UserAuth;
use app\common\library\Token;
use app\common\model\User as UserModel;
use fast\Random;
use think\Db;

/** 代理开户与返水系统设置。 */
class RegisterConfig extends Backend
{
    protected $noNeedRight = ['*'];

    public function index()
    {
        if ($this->request->isAjax()) {
            $config = Db::name('agent_config')->where('id', 1)->find();
            $todayStart = strtotime(date('Y-m-d 00:00:00'));
            return json(['code' => 1, 'msg' => 'ok', 'data' => [
                'config' => $config,
                'invite_config' => Db::name('invite_reward_config')->where('id', 1)->find(),
                'stats' => [
                    'total_users' => Db::name('user')->where('account_source', 'in', ['agent', 'invite'])->count(),
                    'today_users' => Db::name('user')->where('account_source', 'in', ['agent', 'invite'])->where('createtime', '>=', $todayStart)->count(),
                    'agent_users' => Db::name('user')->where('account_source', 'agent')->where('is_agent', 1)->count(),
                    'invited_users' => Db::name('user')->where('account_source', 'in', ['agent', 'invite'])->where('pid', '>', 0)->count(),
                    'invite_users' => Db::name('user')->where('account_source', 'invite')->count(),
                    'invite_direct_users' => Db::name('user')->where('account_source', 'invite')->where('pid', '>', 0)->count(),
                ],
                'top_agents' => Db::name('user')->where('agent_level', 1)->where('pid', 0)->where('is_agent', 1)
                    ->field('id,username,nickname,mobile,self_rebate_rate,can_open_account,status,createtime')
                    ->order('id', 'desc')->limit(100)->select(),
            ]]);
        }
        return $this->view->fetch();
    }

    public function saveConfig()
    {
        $maxPercent = $this->request->post('max_rebate_rate/f', -1);
        $gapPercent = $this->request->post('min_rate_gap/f', 0);
        if ($maxPercent < 0 || $maxPercent > 100 || $gapPercent < 0 || $gapPercent > $maxPercent) {
            return json(['code' => 0, 'msg' => '比例配置不正确']);
        }
        $maxRate = round($maxPercent / 100, 6);
        $gapRate = round($gapPercent / 100, 6);
        $highestTopRate = floatval(Db::name('user')->where('pid', 0)->where('is_agent', 1)->max('self_rebate_rate'));
        if ($maxRate + 0.0000001 < $highestTopRate) {
            return json(['code' => 0, 'msg' => '系统最高比例不能低于现有顶级代理比例']);
        }
        Db::name('agent_config')->where('id', 1)->update([
            'registration_mode' => 'agent_only',
            'max_rebate_rate' => $maxRate,
            'min_rate_gap' => $gapRate,
            'unlock_mode' => 'next_day',
            'claim_mode' => 'manual',
            'status' => 1,
            'updatetime' => time(),
        ]);
        return json(['code' => 1, 'msg' => '代理返水配置已保存']);
    }

    public function createTopAgent()
    {
        $username = trim($this->request->post('username', ''));
        $username = strpos($username, 'xy_') === 0 ? $username : 'xy_' . $username;
        $mobile = trim($this->request->post('mobile', ''));
        $password = (string)$this->request->post('password', '');
        $ratePercent = $this->request->post('rebate_rate/f', -1);
        if (!preg_match('/^[A-Za-z0-9_]{4,20}$/', $username)) return json(['code' => 0, 'msg' => '用户名需为4-20位字母、数字或下划线']);
        if ($mobile !== '' && !preg_match('/^1[3-9]\d{9}$/', $mobile)) return json(['code' => 0, 'msg' => '手机号格式不正确']);
        if (strlen($password) < 6 || strlen($password) > 30) return json(['code' => 0, 'msg' => '密码长度需为6-30位']);
        $maxRate = floatval(Db::name('agent_config')->where('id', 1)->value('max_rebate_rate'));
        $rate = round($ratePercent / 100, 6);
        if ($rate < 0 || $rate > $maxRate + 0.0000001) return json(['code' => 0, 'msg' => '顶级代理比例不能超过系统最高比例']);
        if (UserModel::getByUsername($username) || ($mobile && UserModel::getByMobile($mobile))) return json(['code' => 0, 'msg' => '用户名或手机号已存在']);

        $salt = Random::alnum();
        $now = time();
        try {
            $user = UserModel::create([
                'pid' => 0, 'is_agent' => 1, 'can_open_account' => 1, 'agent_level' => 1, 'account_source' => 'agent',
                'username' => $username, 'nickname' => $username,
                'password' => (new UserAuth())->getEncryptPassword($password, $salt), 'salt' => $salt,
                'email' => '', 'mobile' => $mobile, 'avatar' => '', 'level' => 1, 'score' => 0, 'money' => 0,
                'self_rebate_rate' => $rate, 'invite_rebate_rate' => 0,
                'jointime' => $now, 'joinip' => $this->request->ip(), 'logintime' => 0, 'loginip' => '', 'prevtime' => 0,
                'status' => 'normal', 'createtime' => $now, 'updatetime' => $now,
            ], true);
            return json(['code' => 1, 'msg' => '顶级代理创建成功', 'data' => ['id' => $user->id]]);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '创建失败：' . $e->getMessage()]);
        }
    }

    public function saveInviteConfig()
    {
        $self = $this->request->post('self_rate/f', -1);
        $parent = $this->request->post('parent_rate/f', -1);
        $minimum = $this->request->post('min_bet/f', -1);
        $status = $this->request->post('status/d', 0);
        if ($self < 0 || $parent < 0 || $self > 100 || $parent > 100 || $minimum < 0 || !in_array($status, [0, 1], true))
            return json(['code' => 0, 'msg' => '邀请奖励配置不正确']);
        Db::name('invite_reward_config')->where('id', 1)->update(['self_rate' => round($self / 100, 6),
            'parent_rate' => round($parent / 100, 6), 'min_bet' => round($minimum, 2), 'status' => $status, 'updatetime' => time()]);
        return json(['code' => 1, 'msg' => '邀请奖励配置已保存']);
    }

    public function createInviteRoot()
    {
        $raw = trim((string)$this->request->post('username', ''));
        $username = strpos($raw, 'xj_') === 0 ? $raw : 'xj_' . $raw;
        $password = (string)$this->request->post('password', '');
        if (!preg_match('/^xj_[A-Za-z0-9_]{4,17}$/', $username) || strlen($password) < 6 || strlen($password) > 30)
            return json(['code' => 0, 'msg' => '用户名需4至17位，密码需6至30位']);
        if (UserModel::getByUsername($username)) return json(['code' => 0, 'msg' => '账号已存在']);
        $salt = Random::alnum(); $now = time();
        $user = UserModel::create(['pid' => 0, 'account_source' => 'invite', 'is_agent' => 0, 'can_open_account' => 0,
            'agent_level' => 0, 'username' => $username, 'nickname' => $username,
            'password' => (new UserAuth())->getEncryptPassword($password, $salt), 'salt' => $salt,
            'email' => '', 'mobile' => '', 'avatar' => '', 'level' => 1, 'score' => 0, 'money' => 0,
            'self_rebate_rate' => 0, 'invite_rebate_rate' => 0, 'jointime' => $now, 'joinip' => $this->request->ip(),
            'status' => 'normal', 'createtime' => $now, 'updatetime' => $now], true);
        return json(['code' => 1, 'msg' => '邀请体系初始账号已创建', 'data' => ['username' => $username, 'id' => $user->id]]);
    }

    /** 后台重置新模式顶级代理密码。 */
    public function resetTopAgentPassword()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 0, 'msg' => '请求方式不正确']);
        }
        $agentId = $this->request->post('agent_id/d', 0);
        $password = (string)$this->request->post('password', '');
        if ($agentId <= 0) return json(['code' => 0, 'msg' => '顶级代理参数不正确']);
        if (strlen($password) < 6 || strlen($password) > 30) {
            return json(['code' => 0, 'msg' => '密码长度需为6-30位']);
        }

        $agent = Db::name('user')->where('id', $agentId)
            ->where('pid', 0)->where('agent_level', 1)->where('is_agent', 1)->find();
        if (!$agent) return json(['code' => 0, 'msg' => '新模式顶级代理不存在']);

        $salt = Random::alnum();
        Db::startTrans();
        try {
            $updated = Db::name('user')->where('id', $agentId)->update([
                'salt' => $salt,
                'password' => (new UserAuth())->getEncryptPassword($password, $salt),
                'updatetime' => time(),
            ]);
            if ($updated !== 1) throw new \RuntimeException('密码更新失败');
            Token::clear($agentId);
            Db::commit();
            return json(['code' => 1, 'msg' => '顶级代理密码已重置，原登录状态已失效']);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 0, 'msg' => '密码重置失败：' . $e->getMessage()]);
        }
    }

    /** 启用或禁用新模式顶级代理。 */
    public function toggleTopAgentStatus()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 0, 'msg' => '请求方式不正确']);
        }
        $agentId = $this->request->post('agent_id/d', 0);
        $agent = Db::name('user')->where('id', $agentId)
            ->where('pid', 0)->where('agent_level', 1)->where('is_agent', 1)->find();
        if (!$agent) return json(['code' => 0, 'msg' => '新模式顶级代理不存在']);

        $newStatus = $agent['status'] === 'normal' ? 'hidden' : 'normal';
        Db::startTrans();
        try {
            $updated = Db::name('user')->where('id', $agentId)->update([
                'status' => $newStatus,
                'updatetime' => time(),
            ]);
            if ($updated !== 1) throw new \RuntimeException('账号状态更新失败');
            if ($newStatus !== 'normal') Token::clear($agentId);
            Db::commit();
            return json([
                'code' => 1,
                'msg' => $newStatus === 'normal' ? '顶级代理已启用' : '顶级代理已禁用，原登录状态已失效',
                'data' => ['status' => $newStatus],
            ]);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 0, 'msg' => '状态切换失败：' . $e->getMessage()]);
        }
    }
}
