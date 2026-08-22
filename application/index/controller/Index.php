<?php

namespace app\index\controller;

use app\common\controller\Frontend;

class Index extends Frontend
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    protected $layout = '';

    public function index()
    {
        // 根域名默认进入彩票大厅；保留独立控制器，避免影响后台和 API 路由。
        return $this->redirect('/index/lottery/index', [], 302);
    }

}
