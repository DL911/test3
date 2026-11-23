<?php
namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/** Invitation-only registration and direct referral overview. */
class Invite extends Api
{
    protected $noNeedLogin = ['register', 'validateCode'];
    protected $noNeedRight = '*';

    public function validateCode()
    {
        $id = intval($this->request->param('invite/d', 0));
        $parent = Db::name('user')->where('id', $id)->where('account_source', 'invite')->where('status', 'normal')->find();
        if (!$parent) $this->error('邀请码无效');
        $this->success('邀请码有效', ['inviter' => $parent['username']]);
    }

    public function register()
    {
        $config = Db::name('invite_reward_config')->where('id', 1)->where('status', 1)->find();
        if (!$config) $this->error('邀请注册暂未开放');
        $parentId = intval($this->request->post('invite/d', 0));
        $parent = Db::name('user')->where('id', $parentId)->where('account_source', 'invite')->where('status', 'normal')->find();
        if (!$parent) $this->error('邀请码无效');
        $raw = trim((string)$this->request->post('username', ''));
        $username = strpos($raw, 'xj_') === 0 ? $raw : 'xj_' . $raw;
        if (!preg_match('/^xj_[A-Za-z0-9_]{4,17}$/', $username)) $this->error('用户名需填写4至17位字母、数字或下划线');
        $password = (string)$this->request->post('password', '');
        $mobile = trim((string)$this->request->post('mobile', ''));
        if (strlen($password) < 6 || strlen($password) > 30 || !preg_match('/^1[3-9]\d{9}$/', $mobile)) $this->error('密码或手机号格式不正确');
        $answers = [trim((string)$this->request->post('security_teacher', '')),
            trim((string)$this->request->post('security_hometown', '')),
            trim((string)$this->request->post('security_friend', ''))];
        foreach ($answers as $answer) if (mb_strlen($answer) < 2) $this->error('请完整填写安全问题答案');
        $deviceId = strtolower(trim((string)$this->request->post('device_id', '')));
        if (!preg_match('/^[a-f0-9]{32}$/', $deviceId)) $this->error('设备标识无效');
        $deviceHash = hash('sha256', $deviceId);
        $ip = $this->request->ip();
        try {
            if (Db::name('user_register_device')->where('device_hash', $deviceHash)->find()) $this->error('当前设备已注册过账号');
            if (Db::name('user_register_device')->where('register_ip', $ip)->where('createtime', '>=', time() - 86400)->count() >= 3) $this->error('当前网络24小时内注册次数已达上限');
            Db::name('user_security_answer')->where('id', 0)->count();
        } catch (\think\exception\HttpResponseException $e) { throw $e; }
        catch (\Exception $e) { $this->error('注册依赖表不可用，请先执行数据库升级'); }
        $email = $username . '_' . time() . '@lottery.local';
        Db::startTrans();
        try {
            if (!$this->auth->register($username, $password, $email, $mobile,
                ['pid' => $parentId, 'account_source' => 'invite', 'agent_level' => 0, 'is_agent' => 0, 'can_open_account' => 0])) {
                throw new \RuntimeException($this->auth->getError());
            }
            $id = $this->auth->id;
            Db::name('user_security_answer')->insert(['user_id' => $id, 'answer_teacher' => password_hash($answers[0], PASSWORD_DEFAULT),
                'answer_hometown' => password_hash($answers[1], PASSWORD_DEFAULT), 'answer_friend' => password_hash($answers[2], PASSWORD_DEFAULT),
                'createtime' => time(), 'updatetime' => time()]);
            Db::name('user_register_device')->insert(['user_id' => $id, 'device_hash' => $deviceHash, 'register_ip' => $ip,
                'user_agent' => mb_substr($this->request->server('HTTP_USER_AGENT', ''), 0, 255), 'createtime' => time()]);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('注册失败：' . $e->getMessage());
        }
        $this->success('注册成功', ['userinfo' => $this->auth->getUserinfo(), 'username' => $username]);
    }

    public function summary()
    {
        $id = $this->auth->id;
        $user = Db::name('user')->where('id', $id)->field('account_source,username')->find();
        if (!$user || $user['account_source'] !== 'invite') $this->error('当前账号不属于邀请体系');
        $this->success('ok', ['invite_url' => $this->request->domain() . '/index.php/index/lottery/register?invite=' . $id,
            'direct_count' => Db::name('user')->where('pid', $id)->where('account_source', 'invite')->count(),
            'claimed' => Db::name('xima_record')->where('user_id', $id)->where('reward_type', 'invite_parent')->where('status', 1)->sum('xima_amount')]);
    }
}
