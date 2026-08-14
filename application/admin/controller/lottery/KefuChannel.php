<?php
namespace app\admin\controller\lottery;

use app\common\controller\Backend;

/** 客服通道管理 */
class KefuChannel extends Backend
{
    protected $noNeedRight = ['*'];

    public function index()
    {
        return $this->view->fetch();
    }
}
