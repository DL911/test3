<?php

namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use think\Db;

/**
 * 在线客服系统
 *
 * @icon fa fa-comments
 */
class Kefu extends Backend
{
    /**
     * 客服工作台
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            // Fastadmin 拦截 ajax 请求
            return '';
        }
        return $this->view->fetch();
    }

    /**
     * 获取用户会话列表
     */
    public function get_user_list()
    {
        $subQuery = Db::name('lottery_kefu_message')->field('user_id, MAX(createtime) as last_time')->group('user_id')->buildSql();
        
        $list = Db::name('user')
            ->alias('u')
            ->join([$subQuery=>'m'], 'u.id = m.user_id')
            ->field('u.id, u.username, u.nickname, u.avatar, m.last_time')
            ->order('m.last_time', 'desc')
            ->select();

        foreach($list as &$v) {
            $v['unread'] = Db::name('lottery_kefu_message')
                ->where('user_id', $v['id'])
                ->where('sender_type', 'user')
                ->where('is_read', 0)
                ->count();
        }

        $this->success('', null, $list);
    }

    /**
     * 获取指定用户的聊天记录
     */
    public function get_chat_history()
    {
        $user_id = $this->request->param('user_id');
        $last_id = $this->request->param('last_id', 0);
        
        $where = ['user_id' => $user_id];
        if ($last_id > 0) {
            $where['id'] = ['>', $last_id];
        }

        // 取出100条最新，然后按照ID升序排列
        $list = Db::name('lottery_kefu_message')
            ->where($where)
            ->order('id', 'desc')
            ->limit(100)
            ->select();
            
        $list = array_reverse($list);
            
        // 标记所有来自用户的为已读
        Db::name('lottery_kefu_message')
            ->where('user_id', $user_id)
            ->where('sender_type', 'user')
            ->where('is_read', 0)
            ->update(['is_read' => 1]);

        $this->success('', null, $list);
    }

    /**
     * 客服发送回复
     */
    public function send_message()
    {
        $user_id = $this->request->post('user_id');
        $content = $this->request->post('content');
        $admin_id = $this->auth->id;

        if (empty($content) || empty($user_id)) {
            $this->error('参数错误');
        }

        $data = [
            'user_id' => $user_id,
            'admin_id' => $admin_id,
            'sender_type' => 'admin',
            'content' => $content,
            'is_read' => 0,
            'createtime' => time(),
            'updatetime' => time()
        ];
        
        $id = Db::name('lottery_kefu_message')->insertGetId($data);
        if ($id) {
            $data['id'] = $id;
            $this->success('发送成功', null, $data);
        } else {
            $this->error('发送失败');
        }
    }

    /**
     * 获取全局未读统计状态
     */
    public function global_status()
    {
        $unread = Db::name('lottery_kefu_message')
            ->where('sender_type', 'user')
            ->where('is_read', 0)
            ->count();
        $this->success('', null, ['unread' => $unread]);
    }

    /**
     * 上传图片
     */
    public function upload_image()
    {
        $file = $this->request->file('file');
        if (!$file) {
            $this->error('未选择文件');
        }
        $info = $file->validate(['ext' => 'jpg,jpeg,png,gif,webp', 'size' => 5 * 1024 * 1024])
            ->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . 'kefu');
        if ($info) {
            $url = '/uploads/kefu/' . str_replace('\\', '/', $info->getSaveName());
            $this->success('上传成功', null, ['url' => $url]);
        } else {
            $this->error($file->getError());
        }
    }
}

