<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\Config;
use think\console\Input;
use think\Db;
use think\Exception;

/**
 * 在线命令管理
 *
 * @icon fa fa-circle-o
 */
class Command extends Backend
{

    /**
     * Command模型对象
     */
    protected $model = null;
    protected $noNeedRight = ['get_controller_list', 'get_field_list'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Command;
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    /**
     * 添加
     */
    public function add()
    {

        $tableList = [];
        $list = \think\Db::query("SHOW TABLES");
        foreach ($list as $key => $row) {
            $tableList[reset($row)] = reset($row);
        }

        $this->view->assign("tableList", $tableList);
        return $this->view->fetch();
    }

    /**
     * 获取字段列表
     * @internal
     */
    public function get_field_list()
    {
        if (!$this->request->isPost()) {
            $this->error(__('请求方式不正确'));
        }
        $dbname = Config::get('database.database');
        $prefix = Config::get('database.prefix');
        $table = $this->request->request('table');
        //从数据库中获取表字段信息
        $sql = "SELECT * FROM `information_schema`.`columns` "
            . "WHERE TABLE_SCHEMA = ? AND table_name = ? "
            . "ORDER BY ORDINAL_POSITION";
        //加载主表的列
        $columnList = Db::query($sql, [$dbname, $table]);
        $fieldlist = [];
        foreach ($columnList as $index => $item) {
            $fieldlist[] = $item['COLUMN_NAME'];
        }
        $this->success("", null, ['fieldlist' => $fieldlist]);
    }

    /**
     * 获取控制器列表
     * @internal
     */
    public function get_controller_list()
    {
        if (!$this->request->isPost()) {
            $this->error(__('请求方式不正确'));
        }
        //搜索关键词,客户端输入以空格分开,这里接收为数组
        $word = (array)$this->request->post("q_word/a");
        $word = implode('', $word);

        $adminPath = dirname(__DIR__) . DS;
        $controllerDir = $adminPath . 'controller' . DS;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($controllerDir), \RecursiveIteratorIterator::LEAVES_ONLY
        );
        $list = [];
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $name = str_replace($controllerDir, '', $filePath);
                $name = str_replace(DS, "/", $name);
                if (!preg_match("/(.*)\.php\$/", $name)) {
                    continue;
                }
                if (!$word || stripos($name, $word) !== false) {
                    $list[] = ['id' => $name, 'name' => $name];
                }
            }
        }
        $pageNumber = $this->request->request("pageNumber");
        $pageSize = $this->request->request("pageSize");
        return json(['list' => array_slice($list, ($pageNumber - 1) * $pageSize, $pageSize), 'total' => count($list)]);
    }

    /**
     * 详情
     */
    public function detail($ids)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $row['params'] = (array)json_decode($row['params'], true);
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /**
     * 执行
     */
    public function execute($ids)
    {
        if (!$this->request->isPost()) {
            $this->error(__('请求方式不正确'));
        }
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $params = (array)json_decode($row['params'], true);
        if (!isset($params['commandtype'])) {
            $this->error('不支持1.1.3之前版本的旧命令');
        }
        $this->request->post($params);
        $this->command('execute');
    }

    /**
     * 生成命令
     */
    public function command($action = '')
    {
        if (!$this->request->isPost()) {
            $this->error(__('请求方式不正确'));
        }
        $commandtype = $this->request->post("commandtype");
        $params = $this->request->post();
        $allowfields = [
            'crud' => 'table,controller,model,fields,force,local,delete,menu',
            'menu' => 'delete,force',
            'min'  => 'module,resource,optimize',
            'api'  => 'url,module,output,template,force,title,class,language,addon',
        ];
        $argv = [];
        $allowfields = isset($allowfields[$commandtype]) ? explode(',', $allowfields[$commandtype]) : [];
        $allowfields = array_filter(array_intersect_key($params, array_flip($allowfields)));
        if (isset($params['local']) && !$params['local']) {
            $allowfields['local'] = $params['local'];
        } else {
            unset($allowfields['local']);
        }
        foreach ($allowfields as $key => $param) {
            if (is_string($param) && in_array($param, ['force', 'delete', 'local'])) {
                $param = (int)$param;
            }
            $argv[] = "--{$key}=" . (is_array($param) ? implode(',', $param) : $param);
        }
        if ($commandtype == 'crud') {
            $extend = 'setcheckboxsuffix,enumradiosuffix,imagefield,filefield,intdatesuffix,switchsuffix,citysuffix,selectpagesuffix,selectpagessuffix,ignorefields,sortfield,editorsuffix,headingfilterfield,tagsuffix,jsonsuffix,fixedcolumns';
            $extendArr = explode(',', $extend);
            foreach ($params as $index => $item) {
                if (in_array($index, $extendArr)) {
                    foreach (explode(',', $item) as $key => $value) {
                        if ($value) {
                            $argv[] = "--{$index}={$value}";
                        }
                    }
                }
            }
            $isrelation = (int)$this->request->request('isrelation');
            if ($isrelation && isset($params['relation'])) {
                foreach ($params['relation'] as $index => $relation) {
                    foreach ($relation as $key => $value) {
                        $argv[] = "--{$key}=" . (is_array($value) ? implode(',', $value) : $value);
                    }
                }
            }
        } elseif ($commandtype == 'menu') {

            if (isset($params['allcontroller']) && $params['allcontroller']) {
                $argv[] = "--controller=all-controller";
            } else {
                foreach (explode(',', $params['controllerfile']) as $index => $param) {
                    if ($param) {
                        if (!preg_match("/^([a-zA-Z0-9\/]+)\.php$/", $param)) {
                            $this->error("请输入正确的控制器名称");
                        }
                        $argv[] = "--controller=" . substr($param, 0, -4);
                    }
                }
            }
        } elseif ($commandtype == 'min') {
            $module = $params['module'] ?? '';
            if ($module && !in_array($module, ['all', 'backend', 'frontend'])) {
                $this->error("请选择压缩模块");
            }
            $resource = $params['resource'] ?? '';
            if ($resource && !in_array($resource, ['all', 'js', 'css'])) {
                $this->error("请选择压缩资源");
            }
            $optimize = $params['optimize'] ?? '';
            if ($optimize && !in_array($optimize, ['uglify', 'closure'])) {
                $this->error("请选择压缩模式");
            }
        } elseif ($commandtype == 'api') {
            $url = $params['url'] ?? '';
            if ($url && !filter_var($url, FILTER_VALIDATE_URL)) {
                $this->error("接口地址格式错误");
            }
            $template = $params['template'] ?? '';
            if ($template && !preg_match("/([a-zA-Z0-9\-_]+)\.html/", $template)) {
                $this->error("模板文件错误");
            }
            $output = $params['output'] ?? '';
            if ($output && !preg_match("/([a-zA-Z0-9\-_]+)\.html/", $output)) {
                $this->error("接口生成文件只支持HTML后缀");
            }
            $title = $params['title'] ?? '';
            $author = $params['author'] ?? '';
            $language = $params['language'] ?? '';
            $module = $params['module'] ?? '';
            $addon = $params['addon'] ?? '';
            if (($title && !$this->validateString($title))) {
                $this->error("请填写正确的标题");
            }
            if (($author && !$this->validateString($author))) {
                $this->error("请填写正确的作者");
            }
            if ($language && !in_array($language, ['zh-cn', 'en'])) {
                $this->error("请选择正确的语言");
            }
            if ($module && !preg_match("/[a-zA-Z0-9]+/", $module)) {
                $this->error("请填写正确的模块名称");
            }
            if ($addon && !preg_match("/[a-zA-Z0-9]+/", $addon)) {
                $this->error("请填写正确的插件名称");
            }
        } else {
            $this->error('参数类型错误');
        }
        if ($action == 'execute') {
            if (stripos(implode(' ', $argv), '--controller=all-controller') !== false) {
                $this->error("只允许在命令行执行该命令，执行前请做好菜单规则备份！！！");
            }
            if (config('app_debug')) {
                $result = $this->doexecute($commandtype, $argv);
                $this->success("", null, ['result' => $result]);
            } else {
                $this->error("只允许在开发环境下执行命令");
            }
        } else {
            $this->success("", null, ['command' => "php think {$commandtype} " . implode(' ', $argv)]);
        }
    }

    protected function doexecute($commandtype, $argv)
    {
        if (!config('app_debug')) {
            $this->error("只允许在开发环境下执行命令");
        }
        if (preg_match("/([;\|&]+)/", implode(' ', $argv))) {
            $this->error("不支持的命令参数");
        }
        if (!$this->auth->isSuperAdmin()) {
            $this->error("仅允许超级管理员执行命令");
        }
        $commandName = "\\app\\admin\\command\\" . ucfirst($commandtype);
        $input = new Input($argv);
        $output = new \addons\command\library\Output();
        $command = new $commandName($commandtype);
        $data = [
            'type'        => $commandtype,
            'params'      => json_encode($this->request->post()),
            'command'     => "php think {$commandtype} " . implode(' ', $argv),
            'executetime' => time(),
        ];
        $this->model->save($data);
        try {
            $command->run($input, $output);
            $result = implode("\n", $output->getMessage());
            $this->model->status = 'successed';
        } catch (Exception $e) {
            $result = implode("\n", $output->getMessage()) . "\n";
            $result .= $e->getMessage();
            $this->model->status = 'failured';
        }
        $result = trim($result);
        $this->model->content = $result;
        $this->model->save();
        return $result;
    }

    protected function validateString($string)
    {
        // 匹配中文、英文、数字、下划线、连字符、空格和感叹号
        $pattern = '/^[a-zA-Z0-9_\-\.\s\x{4e00}-\x{9fa5}!]+$/u';
        return preg_match($pattern, $string);
    }

}
