<?php
namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use app\common\library\KefuChannel as ChannelLibrary;

/** 客服通道管理 */
class KefuChannel extends Backend
{
    protected $noNeedRight = ['*'];

    public function index()
    {
        $this->view->assign('channels', ChannelLibrary::all(false));
        return $this->view->fetch();
    }
}
