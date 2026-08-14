<?php
namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use app\common\library\KefuChannel as ChannelLibrary;
use think\Db;

/** 客服通道管理 */
class KefuChannel extends Backend
{
    protected $noNeedRight = ['*'];

    public function index()
    {
        if ($this->request->isPost()) {
            $code = strtolower(trim($this->request->post('code', '')));
            $allowed = ['general', 'wanli', 'dongfang', 'crown'];
            if (!in_array($code, $allowed, true)) $this->error('通道参数错误');

            $row = Db::name('lottery_kefu_channel')->where('code', $code)->find();
            $image = trim($this->request->post('image', ''));
            $file = $this->request->file('image_file');
            if ($file) {
                $info = $file->validate(['ext'=>'jpg,jpeg,png,gif,webp','size'=>5*1024*1024])
                    ->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . 'kefu');
                if (!$info) $this->error($file->getError());
                $image = '/uploads/kefu/' . str_replace('\\', '/', $info->getSaveName());
            }

            $data = [
                'name'=>trim($this->request->post('name', '')),
                'announcement'=>trim($this->request->post('announcement', '')),
                'image'=>$image,
                'intro'=>$this->request->post('intro', ''),
                'status'=>$this->request->post('status/d', 0) ? 1 : 0,
                'updatetime'=>time(),
            ];
            if ($data['name'] === '') $this->error('通道名称不能为空');
            if ($row) {
                Db::name('lottery_kefu_channel')->where('id', $row['id'])->update($data);
            } else {
                $defaults = ChannelLibrary::map(false);
                $base = $defaults[$code];
                $data = array_merge($base, $data, ['code'=>$code, 'createtime'=>time()]);
                unset($data['id']);
                Db::name('lottery_kefu_channel')->insert($data);
            }
            $this->success('保存成功', url('lottery/kefu_channel/index'));
        }
        $this->view->assign('channels', ChannelLibrary::all(false));
        return $this->view->fetch();
    }
}
