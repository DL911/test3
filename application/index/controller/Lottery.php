<?php

namespace app\index\controller;

use app\common\controller\Frontend;

/**
 * 彩票投注控制器
 * 支持: 福彩3D, 排列三
 * 自动检测设备：PC / 手机端
 */
class Lottery extends Frontend
{
    // 必须设为 '*'，否则父类 Frontend 会先拦截并跳转到 index/user/login（默认登录页）
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    protected $layout = '';

    /**
     * 初始化：自主控制登录拦截，跳转到我们自己的登录页
     */
    public function _initialize()
    {
        parent::_initialize();

        // 获取当前操作名
        $action = strtolower($this->request->action());

        // 不需要登录的页面直接放行
        $freeActions = ['login', 'register', 'kefu'];
        if (in_array($action, $freeActions)) {
            return;
        }

        // index / m_index: 手机端免登录可访问大厅，PC端需要登录
        if (in_array($action, ['index', 'm_index'])) {
            if (!$this->isMobile() && !$this->auth->isLogin()) {
                $this->redirect('/index.php/index/lottery/login');
                exit;
            }
            return;
        }

        // 其他所有页面（trend, records, user, bet, recharge, withdraw, agent, verify 等）：必须登录
        if (!$this->auth->isLogin()) {
            if ($this->request->isAjax()) {
                $this->error('请先登录', '/index.php/index/lottery/login');
            }
            $this->redirect('/index.php/index/lottery/login');
            exit;
        }
    }

    /**
     * 判断是否为移动设备
     */
    protected function isMobile()
    {
        $agent = $this->request->server('HTTP_USER_AGENT', '');
        $mobileKeywords = ['iPhone', 'iPad', 'Android', 'Mobile', 'iPod', 'BlackBerry',
            'Windows Phone', 'MQQBrowser', 'UCBrowser', 'Opera Mini', 'webOS'];
        foreach ($mobileKeywords as $kw) {
            if (stripos($agent, $kw) !== false) return true;
        }
        if ($this->request->param('m') == '1') return true;
        return false;
    }

    /**
     * 彩票大厅
     */
    public function index()
    {
        if ($this->isMobile()) {
            return $this->view->fetch('lottery/m_index');
        }
        return $this->view->fetch();
    }

    /**
     * 投注页面
     */
    public function bet()
    {
        $type = $this->request->param('type', 'fc3d');
        $allowed = ['fc3d', 'pl3'];
        if (!in_array($type, $allowed)) {
            $type = 'fc3d';
        }
        $this->view->assign('lottery_type', $type);

        if ($this->isMobile()) {
            return $this->view->fetch('lottery/m_bet');
        }
        return $this->view->fetch();
    }

    /**
     * 登录页面
     */
    public function login()
    {
        return $this->view->fetch('lottery/login');
    }

    /**
     * 注册页面
     */
    public function register()
    {
        return $this->view->fetch('lottery/register');
    }

    /**
     * 个人中心页面
     */
    public function user()
    {
        if ($this->isMobile()) {
            return $this->view->fetch('lottery/user');
        }
        return $this->view->fetch('lottery/pc_user');
    }

    /**
     * 充值页面
     */
    public function recharge()
    {
        if ($this->isMobile()) {
            return $this->view->fetch('lottery/recharge');
        }
        return $this->view->fetch('lottery/pc_recharge');
    }

    /**
     * 提现页面
     */
    public function withdraw()
    {
        if ($this->isMobile()) {
            return $this->view->fetch('lottery/withdraw');
        }
        return $this->view->fetch('lottery/pc_withdraw');
    }

    /**
     * 转账页面
     */
    public function transfer()
    {
        if ($this->isMobile()) {
            return $this->view->fetch('lottery/transfer');
        }
        return $this->view->fetch('lottery/pc_transfer');
    }

    /**
     * 走势分析页面
     */
    public function trend()
    {
        if ($this->isMobile()) {
            return $this->view->fetch('lottery/trend');
        }
        return $this->view->fetch('lottery/pc_trend');
    }

    /**
     * 全民代理系统
     */
    public function agent()
    {
        if ($this->isMobile()) {
            return $this->view->fetch('lottery/agent');
        }
        return $this->view->fetch('lottery/pc_agent');
    }

    /**
     * 移动端大厅
     */
    public function m_index()
    {
        return $this->view->fetch('lottery/m_index');
    }

    /**
     * 移动端投注页
     */
    public function m_bet()
    {
        return $this->view->fetch('lottery/m_bet');
    }

    /**
     * 投注记录页面
     */
    public function records()
    {
        if ($this->isMobile()) {
            return $this->view->fetch('lottery/m_records');
        }
        // PC端暂用用户中心
        return redirect('/index.php/index/lottery/user');
    }

    /**
     * 在线客服页面
     */
    public function kefu()
    {
        if ($this->isMobile()) {
            return $this->view->fetch('lottery/kefu');
        }
        return $this->view->fetch('lottery/pc_kefu');
    }

    /**
     * 实名认证页面
     */
    public function verify()
    {
        return $this->view->fetch('lottery/verify');
    }

    /**
     * 洗码中心页面
     */
    public function xima()
    {
        return $this->view->fetch('lottery/xima');
    }

    /**
     * 返水领取页面
     */
    public function rebate()
    {
        return $this->view->fetch('lottery/rebate');
    }

    /**
     * 交易明细页面
     */
    public function transactions()
    {
        return $this->view->fetch('lottery/transactions');
    }
}

