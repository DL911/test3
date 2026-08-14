<?php

namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use think\Db;
use app\common\library\KefuChannel;

/**
 * 在线客服系统
 *
 * @icon fa fa-comments
 */
class Kefu extends Backend
{
    protected $noNeedRight = ['get_user_list', 'get_chat_history', 'send_message', 'global_status', 'upload_image', 'channels', 'save_channel', 'delete_channel', 'save_user_remark'];
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
        $channel = KefuChannel::normalize($this->request->get('channel', KefuChannel::DEFAULT_CODE), false);
        $subQuery = Db::name('lottery_kefu_message')->where('channel', $channel)->field('user_id, MAX(createtime) as last_time')->group('user_id')->buildSql();
        
        $list = Db::name('user')
            ->alias('u')
            ->join([$subQuery=>'m'], 'u.id = m.user_id')
            ->field('u.id, u.username, u.nickname, u.avatar, m.last_time')
            ->order('m.last_time', 'desc')
            ->select();

        foreach($list as &$v) {
            try { $v['service_remark'] = (string)Db::name('lottery_kefu_user_remark')->where(['user_id'=>$v['id'],'channel'=>$channel])->value('remark'); }
            catch (\Exception $e) { $v['service_remark'] = ''; }
            $v['unread'] = Db::name('lottery_kefu_message')
                ->where('user_id', $v['id'])
                ->where('channel', $channel)
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
        $channel = KefuChannel::normalize($this->request->param('channel', KefuChannel::DEFAULT_CODE), false);
        $last_id = $this->request->param('last_id', 0);
        
        $where = ['user_id' => $user_id, 'channel' => $channel];
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
            ->where('channel', $channel)
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
        $channel = KefuChannel::normalize($this->request->post('channel', KefuChannel::DEFAULT_CODE), false);

        if (empty($content) || empty($user_id)) {
            $this->error('参数错误');
        }

        $data = [
            'user_id' => $user_id,
            'admin_id' => $admin_id,
            'channel' => $channel,
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
        $rows = Db::name('lottery_kefu_message')
            ->where('sender_type', 'user')
            ->where('is_read', 0)
            ->field('channel,COUNT(*) unread')->group('channel')->select();
        $byChannel = []; $unread = 0;
        foreach ($rows as $row) { $byChannel[$row['channel']] = (int)$row['unread']; $unread += (int)$row['unread']; }
        $this->success('', null, ['unread' => $unread, 'channels' => $byChannel]);
    }

    public function channels()
    {
        $this->success('', null, KefuChannel::all(false));
    }

    public function save_channel()
    {
        $id = $this->request->post('id/d', 0);
        $code = strtolower(trim($this->request->post('code', '')));
        $name = trim($this->request->post('name', ''));
        if (!preg_match('/^[a-z][a-z0-9_]{1,31}$/', $code) || $name === '') $this->error('编码或名称格式不正确');
        $data = ['code'=>$code, 'name'=>$name, 'description'=>trim($this->request->post('description','')), 'announcement'=>trim($this->request->post('announcement','')), 'image'=>trim($this->request->post('image','')), 'intro'=>$this->request->post('intro',''), 'icon'=>trim($this->request->post('icon','fa-comments')), 'color'=>trim($this->request->post('color','#18bc9c')), 'weigh'=>$this->request->post('weigh/d',0), 'status'=>$this->request->post('status/d',1), 'updatetime'=>time()];
        if ($id) {
            $existing = Db::name('lottery_kefu_channel')->where('id',$id)->find();
            if (!$existing) $this->error('通道不存在');
            $data['code'] = $existing['code'];
            Db::name('lottery_kefu_channel')->where('id',$id)->update($data);
        }
        else { $data['createtime']=time(); Db::name('lottery_kefu_channel')->insert($data); }
        $this->success('保存成功');
    }

    public function save_user_remark()
    {
        $userId = $this->request->post('user_id/d', 0);
        $channel = KefuChannel::normalize($this->request->post('channel', KefuChannel::DEFAULT_CODE), false);
        $remark = mb_substr(trim($this->request->post('remark', '')), 0, 100);
        if (!$userId) $this->error('用户参数错误');
        $row = Db::name('lottery_kefu_user_remark')->where(['user_id'=>$userId,'channel'=>$channel])->find();
        $data = ['remark'=>$remark,'admin_id'=>$this->auth->id,'updatetime'=>time()];
        if ($row) Db::name('lottery_kefu_user_remark')->where('id',$row['id'])->update($data);
        else { $data['user_id']=$userId; $data['channel']=$channel; $data['createtime']=time(); Db::name('lottery_kefu_user_remark')->insert($data); }
        $this->success('备注已保存');
    }

    public function delete_channel()
    {
        $id = $this->request->post('id/d', 0);
        $row = Db::name('lottery_kefu_channel')->where('id',$id)->find();
        if (!$row || $row['code'] === KefuChannel::DEFAULT_CODE) $this->error('默认通道不能删除');
        if (Db::name('lottery_kefu_message')->where('channel',$row['code'])->count()) $this->error('该通道已有消息，请停用而不要删除');
        Db::name('lottery_kefu_channel')->where('id',$id)->delete();
        $this->success('删除成功');
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
