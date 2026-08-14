<?php
namespace app\api\controller;

use app\common\controller\Api;
use think\Db;
use app\common\library\KefuChannel;

class Kefu extends Api
{
    // 只有获取未读数接口不需要强制验证(手动判断登录)，其他接口均需要登录
    protected $noNeedLogin = ['unreadCount', 'channels', 'channelDetail'];
    protected $noNeedRight = ['*'];

    /**
     * 获取聊天历史记录
     */
    public function history()
    {
        $user_id = $this->auth->id;
        $channel = KefuChannel::normalize($this->request->get('channel', KefuChannel::DEFAULT_CODE));
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 20);

        // 获取记录并倒序排列后，再反转为正序(因为需要呈现最新的在底端)
        $list = Db::name('lottery_kefu_message')
            ->where('user_id', $user_id)
            ->where('channel', $channel)
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        $list = array_reverse($list);

        // 如果获取到消息，并且是管理员发来的且未读，则标记为已读
        $unreadIds = [];
        foreach ($list as $msg) {
            if ($msg['sender_type'] == 'admin' && $msg['is_read'] == 0) {
                $unreadIds[] = $msg['id'];
            }
        }
        if (!empty($unreadIds)) {
            Db::name('lottery_kefu_message')->where('id', 'in', $unreadIds)->where('channel', $channel)->update(['is_read' => 1]);
        }

        $this->success("获取成功", ['list' => $list]);
    }

    /**
     * 用户发送消息给客服
     */
    public function send()
    {
        $user_id = $this->auth->id;
        $channel = KefuChannel::normalize($this->request->post('channel', KefuChannel::DEFAULT_CODE));
        $content = $this->request->post('content', '');
        
        if (empty($content)) {
            $this->error('消息内容不能为空');
        }

        $data = [
            'user_id' => $user_id,
            'admin_id' => 0, 
            'channel' => $channel,
            'sender_type' => 'user',
            'content' => $content,
            'is_read' => 0,
            'createtime' => time(),
            'updatetime' => time(),
        ];

        $id = Db::name('lottery_kefu_message')->insertGetId($data);

        if ($id) {
            $data['id'] = $id;
            $this->success("发送成功", $data);
        } else {
            $this->error("发送失败");
        }
    }

    /**
     * 短轮询获取最新消息
     */
    public function poll()
    {
        $user_id = $this->auth->id;
        $channel = KefuChannel::normalize($this->request->get('channel', KefuChannel::DEFAULT_CODE));
        $last_id = $this->request->get('last_id/d', 0);

        $where = [
            'user_id' => $user_id,
            'channel' => $channel,
            'id' => ['>', $last_id]
        ];

        $list = Db::name('lottery_kefu_message')
            ->where($where)
            ->order('id', 'asc')
            ->select();

        // 标记已读
        $unreadIds = [];
        foreach ($list as $msg) {
            if ($msg['sender_type'] == 'admin' && $msg['is_read'] == 0) {
                $unreadIds[] = $msg['id'];
            }
        }
        if (!empty($unreadIds)) {
            Db::name('lottery_kefu_message')->where('id', 'in', $unreadIds)->where('channel', $channel)->update(['is_read' => 1]);
        }

        $this->success("检查成功", ['list' => $list]);
    }
    
    /**
     * 获取总的未读消息数量 (用于底部导航红点等)
     */
    public function unreadCount()
    {
        if (!$this->auth->isLogin()) {
            $this->success("", ['count' => 0]);
            return;
        }
        $user_id = $this->auth->id;
        $count = Db::name('lottery_kefu_message')
            ->where('user_id', $user_id)
            ->where('sender_type', 'admin')
            ->where('is_read', 0)
            ->count();
            
        $this->success("", ['count' => $count]);
    }

    public function channels()
    {
        $this->success('', ['list' => KefuChannel::all(true)]);
    }

    public function channelDetail()
    {
        $code = KefuChannel::normalize($this->request->get('channel', KefuChannel::DEFAULT_CODE));
        $map = KefuChannel::map(true);
        $this->success('', isset($map[$code]) ? $map[$code] : $map[KefuChannel::DEFAULT_CODE]);
    }

    /**
     * 用户端上传图片
     */
    public function uploadImage()
    {
        $file = $this->request->file('file');
        if (!$file) {
            $this->error('未选择文件');
        }
        $info = $file->validate(['ext' => 'jpg,jpeg,png,gif,webp', 'size' => 5 * 1024 * 1024])
            ->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . 'kefu');
        if ($info) {
            $url = '/uploads/kefu/' . str_replace('\\', '/', $info->getSaveName());
            $this->success('上传成功', ['url' => $url]);
        } else {
            $this->error($file->getError());
        }
    }
}
