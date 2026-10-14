<?php
namespace app\admin\controller\lottery;

use app\common\controller\Backend;

/**
 * 流水返利配置管理
 */
class RebateConfig extends Backend
{
    protected $noNeedRight = ['*'];

    /**
     * 列表 + 编辑
     */
    public function index()
    {
        $this->error('旧流水返利配置已停用，请使用返水管理与代理参数配置');
    }

    /**
     * 添加/编辑
     */
    public function edit($id = null)
    {
        $this->error('旧流水返利配置已停用');
    }

    /**
     * 删除
     */
    public function del($ids = null)
    {
        $this->error('旧流水返利配置已停用');
    }
}
