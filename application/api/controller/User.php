<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Ems;
use app\common\library\Sms;
use fast\Random;
use think\Config;
use think\Db;
use think\Validate;

/**
 * 会员接口
 */
class User extends Api
{
    protected $noNeedLogin = ['login', 'mobilelogin', 'register', 'resetpwd', 'changeemail', 'changemobile', 'third'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();

        if (!Config::get('fastadmin.usercenter')) {
            $this->error(__('User center already closed'));
        }

    }

    /**
     * 会员中心 - 返回完整用户信息
     */
    public function index()
    {
        $user = $this->auth->getUser();
        $data = [
            'id'       => $user->id,
            'username' => $user->username,
            'nickname' => $user->nickname,
            'avatar'   => $user->avatar,
            'money'    => $user->money,
            'score'    => $user->score,
            'level'    => $user->level,
            'mobile'   => $user->mobile,
            'group_id' => $user->group_id,
            'self_rebate_rate'   => $user->self_rebate_rate,
            'invite_rebate_rate' => $user->invite_rebate_rate,
            'verify_status' => $this->getUserVerifyStatus($user->id),
        ];
        $this->success('', $data);
    }

    /**
     * 会员登录
     *
     * @ApiMethod (POST)
     * @ApiParams (name="account", type="string", required=true, description="账号")
     * @ApiParams (name="password", type="string", required=true, description="密码")
     */
    public function login()
    {
        $account = $this->request->post('account');
        $password = $this->request->post('password');
        if (!$account || !$password) {
            $this->error(__('Invalid parameters'));
        }
        $ret = $this->auth->login($account, $password);
        if ($ret) {
            $this->writeLoginLog('account');
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Logged in successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 记录会员登录日志
     * @param string $loginType 登录方式 account|mobile
     */
    private function writeLoginLog($loginType = 'account')
    {
        try {
            $user = $this->auth->getUser();
            if (!$user) return;
            Db::name('user_login_log')->insert([
                'user_id'    => $user->id,
                'username'   => $user->username ?: '',
                'nickname'   => $user->nickname ?: '',
                'login_ip'   => $this->request->ip(),
                'login_type' => $loginType,
                'user_agent' => mb_substr($this->request->server('HTTP_USER_AGENT', ''), 0, 490),
                'createtime' => time(),
            ]);
        } catch (\Exception $e) {
            // 表不存在或写入失败时静默忽略，不影响登录
        }
    }

    /**
     * 手机验证码登录
     *
     * @ApiMethod (POST)
     * @ApiParams (name="mobile", type="string", required=true, description="手机号")
     * @ApiParams (name="captcha", type="string", required=true, description="验证码")
     */
    public function mobilelogin()
    {
        $mobile = $this->request->post('mobile');
        $captcha = $this->request->post('captcha');
        if (!$mobile || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        if (!Sms::check($mobile, $captcha, 'mobilelogin')) {
            $this->error(__('Captcha is incorrect'));
        }
        $user = \app\common\model\User::getByMobile($mobile);
        if ($user) {
            if ($user->status != 'normal') {
                $this->error(__('Account is locked'));
            }
            //如果已经有账号则直接登录
            $ret = $this->auth->direct($user->id);
        } else {
            $ret = $this->auth->register($mobile, Random::alnum(), '', $mobile, []);
        }
        if ($ret) {
            Sms::flush($mobile, 'mobilelogin');
            $this->writeLoginLog('mobile');
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Logged in successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 注册会员
     *
     * @ApiMethod (POST)
     * @ApiParams (name="username", type="string", required=true, description="用户名")
     * @ApiParams (name="password", type="string", required=true, description="密码")
     * @ApiParams (name="email", type="string", required=true, description="邮箱")
     * @ApiParams (name="mobile", type="string", required=true, description="手机号")
     * @ApiParams (name="code", type="string", required=true, description="验证码")
     */
    public function register()
    {
        $username = $this->request->post('username');
        $password = $this->request->post('password');
        $email = $this->request->post('email');
        $mobile = $this->request->post('mobile');
        if (!$username || !$password) {
            $this->error(__('Invalid parameters'));
        }
        if ($email && !Validate::is($email, "email")) {
            $this->error(__('Email is incorrect'));
        }
        if ($mobile && !Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        // 短信验证码暂时关闭（如需开启请取消以下注释）
        // $code = $this->request->post('code');
        // $ret = Sms::check($mobile, $code, 'register');
        // if (!$ret) {
        //     $this->error(__('Captcha is incorrect'));
        // }
        $extend = $this->request->post('extend/a', []);
        if (!isset($extend['self_rebate_rate'])) $extend['self_rebate_rate'] = 0.05;
        if (!isset($extend['invite_rebate_rate'])) $extend['invite_rebate_rate'] = 0.015;
        $ret = $this->auth->register($username, $password, $email, $mobile, $extend);
        if ($ret) {
            // 邀请码绑定上级
            $invite = $this->request->post('invite', $this->request->param('invite', ''));
            if ($invite) {
                $parent = \app\common\model\User::get(intval($invite));
                if ($parent && $parent->id != $this->auth->id) {
                    \app\common\model\User::where('id', $this->auth->id)->update(['pid' => $parent->id]);
                }
            }
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Sign up successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 退出登录
     * @ApiMethod (POST)
     */
    public function logout()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $this->auth->logout();
        $this->success(__('Logout successful'));
    }

    /**
     * 修改会员个人信息
     *
     * @ApiMethod (POST)
     * @ApiParams (name="avatar", type="string", required=true, description="头像地址")
     * @ApiParams (name="username", type="string", required=true, description="用户名")
     * @ApiParams (name="nickname", type="string", required=true, description="昵称")
     * @ApiParams (name="bio", type="string", required=true, description="个人简介")
     */
    public function profile()
    {
        $user = $this->auth->getUser();
        $username = $this->request->post('username');
        $nickname = $this->request->post('nickname');
        $bio = $this->request->post('bio');
        $avatar = $this->request->post('avatar', '', 'trim,strip_tags,htmlspecialchars');
        if ($username) {
            $exists = \app\common\model\User::where('username', $username)->where('id', '<>', $this->auth->id)->find();
            if ($exists) {
                $this->error(__('Username already exists'));
            }
            $user->username = $username;
        }
        if ($nickname) {
            $exists = \app\common\model\User::where('nickname', $nickname)->where('id', '<>', $this->auth->id)->find();
            if ($exists) {
                $this->error(__('Nickname already exists'));
            }
            $user->nickname = $nickname;
        }
        $user->bio = $bio;
        $user->avatar = $avatar;
        $user->save();
        $this->success();
    }

    /**
     * 修改邮箱
     *
     * @ApiMethod (POST)
     * @ApiParams (name="email", type="string", required=true, description="邮箱")
     * @ApiParams (name="captcha", type="string", required=true, description="验证码")
     */
    public function changeemail()
    {
        $user = $this->auth->getUser();
        $email = $this->request->post('email');
        $captcha = $this->request->post('captcha');
        if (!$email || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::is($email, "email")) {
            $this->error(__('Email is incorrect'));
        }
        if (\app\common\model\User::where('email', $email)->where('id', '<>', $user->id)->find()) {
            $this->error(__('Email already exists'));
        }
        $result = Ems::check($email, $captcha, 'changeemail');
        if (!$result) {
            $this->error(__('Captcha is incorrect'));
        }
        $verification = $user->verification;
        $verification->email = 1;
        $user->verification = $verification;
        $user->email = $email;
        $user->save();

        Ems::flush($email, 'changeemail');
        $this->success();
    }

    /**
     * 修改手机号
     *
     * @ApiMethod (POST)
     * @ApiParams (name="mobile", type="string", required=true, description="手机号")
     * @ApiParams (name="captcha", type="string", required=true, description="验证码")
     */
    public function changemobile()
    {
        $user = $this->auth->getUser();
        $mobile = $this->request->post('mobile');
        $captcha = $this->request->post('captcha');
        if (!$mobile || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        if (\app\common\model\User::where('mobile', $mobile)->where('id', '<>', $user->id)->find()) {
            $this->error(__('Mobile already exists'));
        }
        $result = Sms::check($mobile, $captcha, 'changemobile');
        if (!$result) {
            $this->error(__('Captcha is incorrect'));
        }
        $verification = $user->verification;
        $verification->mobile = 1;
        $user->verification = $verification;
        $user->mobile = $mobile;
        $user->save();

        Sms::flush($mobile, 'changemobile');
        $this->success();
    }

    /**
     * 第三方登录
     *
     * @ApiMethod (POST)
     * @ApiParams (name="platform", type="string", required=true, description="平台名称")
     * @ApiParams (name="code", type="string", required=true, description="Code码")
     */
    public function third()
    {
        $url = url('user/index');
        $platform = $this->request->post("platform");
        $code = $this->request->post("code");
        $config = get_addon_config('third');
        if (!$config || !isset($config[$platform])) {
            $this->error(__('Invalid parameters'));
        }
        $app = new \addons\third\library\Application($config);
        //通过code换access_token和绑定会员
        $result = $app->{$platform}->getUserInfo(['code' => $code]);
        if ($result) {
            $loginret = \addons\third\library\Service::connect($platform, $result);
            if ($loginret) {
                $data = [
                    'userinfo'  => $this->auth->getUserinfo(),
                    'thirdinfo' => $result
                ];
                $this->success(__('Logged in successful'), $data);
            }
        }
        $this->error(__('Operation failed'), $url);
    }

    /**
     * 重置密码
     *
     * @ApiMethod (POST)
     * @ApiParams (name="mobile", type="string", required=true, description="手机号")
     * @ApiParams (name="newpassword", type="string", required=true, description="新密码")
     * @ApiParams (name="captcha", type="string", required=true, description="验证码")
     */
    public function resetpwd()
    {
        $type = $this->request->post("type", "mobile");
        $mobile = $this->request->post("mobile");
        $email = $this->request->post("email");
        $newpassword = $this->request->post("newpassword");
        $captcha = $this->request->post("captcha");
        if (!$newpassword || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        //验证Token
        if (!Validate::make()->check(['newpassword' => $newpassword], ['newpassword' => 'require|regex:\S{6,30}'])) {
            $this->error(__('Password must be 6 to 30 characters'));
        }
        if ($type == 'mobile') {
            if (!Validate::regex($mobile, "^1\d{10}$")) {
                $this->error(__('Mobile is incorrect'));
            }
            $user = \app\common\model\User::getByMobile($mobile);
            if (!$user) {
                $this->error(__('User not found'));
            }
            $ret = Sms::check($mobile, $captcha, 'resetpwd');
            if (!$ret) {
                $this->error(__('Captcha is incorrect'));
            }
            Sms::flush($mobile, 'resetpwd');
        } else {
            if (!Validate::is($email, "email")) {
                $this->error(__('Email is incorrect'));
            }
            $user = \app\common\model\User::getByEmail($email);
            if (!$user) {
                $this->error(__('User not found'));
            }
            $ret = Ems::check($email, $captcha, 'resetpwd');
            if (!$ret) {
                $this->error(__('Captcha is incorrect'));
            }
            Ems::flush($email, 'resetpwd');
        }
        //模拟一次登录
        $this->auth->direct($user->id);
        $ret = $this->auth->changepwd($newpassword, '', true);
        if ($ret) {
            $this->success(__('Reset password successful'));
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 获取用户实名认证状态
     * -1=未提交 0=待审核 1=已通过 2=已拒绝
     */
    private function getUserVerifyStatus($userId)
    {
        $verify = Db::name('user_verify')->where('user_id', $userId)->order('id', 'desc')->find();
        if (!$verify) return -1;
        return intval($verify['status']);
    }

    /**
     * 查询实名认证详情
     * GET /api/user/verifyStatus
     */
    public function verifyStatus()
    {
        $userId = $this->auth->id;
        $verify = Db::name('user_verify')->where('user_id', $userId)->order('id', 'desc')->find();
        if (!$verify) {
            $this->success('OK', ['status' => -1, 'status_text' => '未认证']);
        }
        $statusMap = [-1 => '未认证', 0 => '审核中', 1 => '已认证', 2 => '已拒绝'];
        $verify['status_text'] = isset($statusMap[$verify['status']]) ? $statusMap[$verify['status']] : '未知';
        $verify['create_date'] = date('Y-m-d H:i', $verify['createtime']);
        $this->success('OK', $verify);
    }

    /**
     * 提交实名认证（免审核，直接通过）
     * POST /api/user/submitVerify
     */
    public function submitVerify()
    {
        $userId = $this->auth->id;

        // 检查是否已通过
        $existing = Db::name('user_verify')->where('user_id', $userId)->where('status', 1)->find();
        if ($existing) {
            $this->error('您已通过实名认证，不可重复提交');
        }

        $realName = $this->request->post('real_name', '');
        $idCard   = $this->request->post('id_card', '');

        if (empty($realName) || empty($idCard)) {
            $this->error('请填写真实姓名和身份证号');
        }
        if (!preg_match('/^[\x{4e00}-\x{9fa5}]{2,10}$/u', $realName)) {
            $this->error('请输入正确的中文姓名');
        }
        if (!preg_match('/^\d{17}[\dXx]$/', $idCard)) {
            $this->error('请输入正确的18位身份证号');
        }

        // 直接通过，无需审核
        $data = [
            'user_id'     => $userId,
            'real_name'   => $realName,
            'id_card'     => $idCard,
            'front_image' => '',
            'back_image'  => '',
            'status'      => 1,  // 直接通过
            'reject_reason' => '',
            'createtime'  => time(),
            'updatetime'  => time(),
        ];

        // 如有旧记录则更新，否则新建
        $old = Db::name('user_verify')->where('user_id', $userId)->find();
        if ($old) {
            unset($data['createtime']);
            Db::name('user_verify')->where('id', $old['id'])->update($data);
        } else {
            Db::name('user_verify')->insert($data);
        }

        $this->success('实名认证成功');
    }

    /**
     * 交易明细
     * GET /api/user/transactions?type=all&page=1&limit=20
     */
    public function transactions()
    {
        $userId = $this->auth->id;
        $type   = $this->request->param('type', 'all');
        $page   = $this->request->param('page/d', 1);
        $limit  = $this->request->param('limit/d', 20);

        $records = [];

        try {
            if ($type === 'all' || $type === 'recharge') {
                $rows = Db::name('recharge_order')->where('user_id', $userId)
                    ->field('id, amount, status, createtime')->order('createtime', 'desc')->select();
                $rechargeStatusMap = [0 => '待确认', 1 => '已到账', 2 => '已取消'];
                foreach ($rows as $r) {
                    $stText = $rechargeStatusMap[$r['status']] ?? '未知';
                    $color = $r['status'] == 1 ? '#27C24C' : ($r['status'] == 0 ? '#f59e0b' : '#999');
                    $records[] = ['id' => $r['id'], 'type' => 'recharge', 'type_text' => '充值', 'amount' => '+' . $r['amount'], 'color' => $color, 'time' => $r['createtime'], 'date' => date('m-d H:i', $r['createtime']), 'status' => $r['status'], 'status_text' => $stText];
                }
            }

            if ($type === 'all' || $type === 'withdraw') {
                $rows = Db::name('withdraw_order')->where('user_id', $userId)
                    ->field('id, amount, status, createtime')->order('createtime', 'desc')->select();
                $withdrawStatusMap = [0 => '待审核', 1 => '已打款', 2 => '已拒绝'];
                foreach ($rows as $r) {
                    $stText = $withdrawStatusMap[$r['status']] ?? '未知';
                    $color = $r['status'] == 1 ? '#F05050' : ($r['status'] == 0 ? '#f59e0b' : '#999');
                    $records[] = ['id' => $r['id'], 'type' => 'withdraw', 'type_text' => '提现', 'amount' => '-' . $r['amount'], 'color' => $color, 'time' => $r['createtime'], 'date' => date('m-d H:i', $r['createtime']), 'status' => $r['status'], 'status_text' => $stText];
                }
            }

            if ($type === 'all' || $type === 'bet') {
                $rows = Db::name('lottery_bet')->where('user_id', $userId)->where('status', '<>', 3)
                    ->field('id, total_amount, win_amount, status, createtime, lottery_type, period')->order('createtime', 'desc')->select();
                $typeMap = [1 => '福彩3D', 2 => '排列三'];
                foreach ($rows as $r) {
                    $records[] = ['id' => $r['id'], 'type' => 'bet', 'type_text' => '投注-' . ($typeMap[$r['lottery_type']] ?? ''), 'amount' => '-' . $r['total_amount'], 'color' => '#f5576c', 'time' => $r['createtime'], 'date' => date('m-d H:i', $r['createtime']), 'extra' => $r['period']];
                    if ($r['status'] == 1 && $r['win_amount'] > 0) {
                        $records[] = ['id' => $r['id'], 'type' => 'win', 'type_text' => '中奖-' . ($typeMap[$r['lottery_type']] ?? ''), 'amount' => '+' . $r['win_amount'], 'color' => '#27C24C', 'time' => $r['createtime'] + 1, 'date' => date('m-d H:i', $r['createtime']), 'extra' => $r['period']];
                    }
                }
            }

            if ($type === 'all' || $type === 'transfer') {
                $rows = Db::name('transfer_order')->where('from_user_id|to_user_id', $userId)
                    ->field('id, from_user_id, to_user_id, amount, createtime')->order('createtime', 'desc')->select();
                foreach ($rows as $r) {
                    $isOut = ($r['from_user_id'] == $userId);
                    $otherId = $isOut ? $r['to_user_id'] : $r['from_user_id'];
                    $otherName = Db::name('user')->where('id', $otherId)->value('nickname') ?: 'UID:' . $otherId;
                    $records[] = ['id' => $r['id'], 'type' => 'transfer', 'type_text' => $isOut ? '转出' : '转入', 'amount' => ($isOut ? '-' : '+') . $r['amount'], 'color' => $isOut ? '#F05050' : '#27C24C', 'time' => $r['createtime'], 'date' => date('m-d H:i', $r['createtime']), 'extra' => $otherName];
                }
            }

            if ($type === 'all' || $type === 'commission') {
                $rows = Db::name('lottery_commission')->where('user_id', $userId)
                    ->field('id, amount, createtime, remark')->order('createtime', 'desc')->select();
                foreach ($rows as $r) {
                    $records[] = ['id' => $r['id'], 'type' => 'commission', 'type_text' => '推荐佣金', 'amount' => '+' . $r['amount'], 'color' => '#fa709a', 'time' => $r['createtime'], 'date' => date('m-d H:i', $r['createtime'])];
                }
            }

            if ($type === 'all' || $type === 'rebate') {
                $rows = Db::name('rebate_record')->where('user_id', $userId)->where('status', 1)
                    ->field('id, rebate_amount, createtime, period_start')->order('createtime', 'desc')->select();
                foreach ($rows as $r) {
                    $records[] = ['id' => $r['id'], 'type' => 'rebate', 'type_text' => '返水', 'amount' => '+' . $r['rebate_amount'], 'color' => '#e67e22', 'time' => $r['createtime'], 'date' => date('m-d H:i', $r['createtime'])];
                }
            }
        } catch (\Exception $e) {}

        // 按时间排序
        usort($records, function ($a, $b) { return $b['time'] - $a['time']; });

        $total = count($records);
        $offset = ($page - 1) * $limit;
        $list = array_slice($records, $offset, $limit);

        $this->success('OK', ['list' => $list, 'total' => $total]);
    }
}
