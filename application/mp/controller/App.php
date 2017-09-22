<?php
// +----------------------------------------------------------------------
// | [RhaPHP System] Copyright (c) 2017 http://www.rhaphp.com/
// +----------------------------------------------------------------------
// | [RhaPHP] 并不是自由软件,你可免费使用,未经许可不能去掉RhaPHP相关版权
// +----------------------------------------------------------------------
// | 官方网站：RhaPHP.com 任何企业和个人不允许对程序代码以任何形式任何目的再发布
// +----------------------------------------------------------------------
// | Author: Geeson <qimengkeji@vip.qq.com>
// +----------------------------------------------------------------------


namespace app\mp\controller;


use app\common\model\Addons;
use think\Db;
use think\Request;
use think\Validate;
use app\common\model\MpReply;
use app\common\model\MpRule;

class App extends Base
{

    private $addonCfByDb;
    private $addonCfByFile;

    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $name = input('name');
        if ($name == '') {
            $this->error('找不到相应的应用');
        }
        $model = new Addons();
        $addon = $model->where(['addon' => $name])->find();
        if (empty($addon)) {
            $this->error('应用不存在');
        }
        $this->addonCfByDb = $addonCfByDb = $model->where(['addon' => $name, 'status' => 1])->find();
        $this->addonCfByFile = $addonCfByFile = $model->getAddonByFile($name);
        if ($addonCfByDb['addon'] != $addonCfByFile['addon']) {
            $this->error('应用信息不相符，请检查');
        }
        $addonMenu=isset($addonCfByFile['menu'])?$addonCfByFile['menu']:'';
        $this->assign('addonMenu', $addonMenu);
        $this->assign('addonInfo', $addonCfByDb);
        $this->assign('name', $name);
        $this->assign('menu_app', '');
        $this->assign('Mkey', '-1');
    }

    /**
     * 应用入口配置
     * @author Geeson 314835050@qq.com
     * @param string $type
     * @return \think\response\View
     */
    public function index($type = '')
    {
        if (Request::instance()->isPost()) {
            $input = input();
            $ruleModel = new MpRule();
            $replyMode = new MpReply();
            if ($type == 'news') {

                $validate = new Validate(
                    [
                        'keyword' => 'require',
                        'title' => 'require',
                        'picurl' => 'require',
                        'link' => 'require',
                    ],
                    [
                        'keyword.require' => '关键词不能为空',
                        'title.require' => '标题不能为空',
                        'picurl.require' => '请上传图文封面图',
                        'link.require' => '连接不能为空',
                    ]
                );
                $result = $validate->check(input());
                if ($result === false) {
                    ajaxMsg(0, $validate->getError());
                }
                $data['title'] = $input['title'];
                $data['url'] = $input['picurl'];
                $data['content'] = $input['news_content'];
                $data['link'] = $input['link'];
                $data['type'] = 'news';
                $data['keyword'] = $input['keyword'];
                $data['addon'] = input('name');
                $result = $ruleModel->where(['type' => 'news', 'addon' => input('name'), 'mpid' => $this->mid])
                    ->where('reply_id', 'neq', '')
                    ->find();
                if (!empty($result)) {
                    $res_1 = $replyMode->allowField(true)->save($data, ['reply_id' => $result['reply_id']]);

                    $res_2 = $ruleModel->allowField(true)->save($data, ['reply_id' => $result['reply_id']]);
                    if ($res_1 || $res_2) {
                        ajaxMsg(1, '更新成功');
                    } else {
                        ajaxMsg(0, '更新失败');
                    }
                } else {
                    if ($res_1 = $replyMode->allowField(true)->save($data)) {
                        $data['reply_id'] = $replyMode->reply_id;
                        $data['mpid'] = $this->mid;
                        if (!$res_2 = $ruleModel->allowField(true)->save($data)) {
                            $replyMode::destroy(['reply_id' => $data['reply_id']]);
                        }
                    }
                    if ($res_1 && $res_2) {
                        ajaxMsg(1, '提交成功');
                    } else {
                        ajaxMsg(0, '提交失败');
                    }
                }
            }
            if ($type == 'addon') {
                $data['keyword'] = $input['keyword'];
                $data['type'] = 'addon';
                $data['addon'] = $input['name'];
                $data['mpid'] = $this->mid;
                $validate = new Validate(
                    [
                        'mpid' => 'require',
                        'keyword' => 'require',
                        'addon' => 'require',
                    ],
                    [
                        'mpid.require' => '公众号标识不存在！',
                        'keyword.require' => '关键词不能为空',
                        'addons.require' => '应用不能为空',
                    ]
                );
                $result = $validate->check($data);
                if ($result === false) {
                    ajaxMsg(0, $validate->getError());
                }
                $result = $ruleModel->where(['type' => 'addon', 'addon' => input('name'), 'mpid' => $this->mid])->find();
                if (empty($result)) {
                    if ($ruleModel->allowField(true)->save($data)) {
                        ajaxMsg(1, '提交成功');
                    } else {
                        ajaxMsg(0, '提交失败');
                    }
                } else {
                    if ($ruleModel->allowField(true)->save($data, ['type' => 'addon', 'addon' => input('name'), 'mpid' => $this->mid])) {
                        ajaxMsg(1, '更新成功');
                    } else {
                        ajaxMsg(0, '并没更新');
                    }
                }
            }

        } else {
            $addon = $this->addonCfByDb;
            $url = null;
            if ($addon['entry_url'] != '') {
                $url = getHostDomain() . addonUrl($addon['entry_url'], ['mid' => $this->mid]);

            }
//            else{
//                $type='addon';
//            }
            $ruleModel = new MpRule();
            $rePly = $ruleModel->alias('r')
                ->where(['r.mpid' => $this->mid, 'r.type' => $type, 'r.addon' => input('name')])
                ->join('__MP_REPLY__ p', 'p.reply_id=r.reply_id')
                ->order('r.id DESC')
                ->find();
            $rule = $ruleModel->where(['type' => 'addon', 'addon' => input('name'), 'mpid' => $this->mid])->find();
            $this->assign('news', $rePly);
            $this->assign('addon', $rule);
            $this->assign('entryUrl', $url);
            $this->assign('type', $type);
            return view('entry');
        }

    }

    /**
     * 参数配置
     * @author Geeson  314835050@qq.com
     * @param string $name
     * @return \think\response\View
     */
    public function config($name = '')
    {
        if (Request::instance()->isPost()) {
            $input = input();
            $data['mpid'] = $this->mid;
            $data['addon'] = $input['addonName'];
            $data['infos'] = json_encode($input);
            $result = Db::name('addon_info')->where(['mpid' => $this->mid, 'addon' => $input['addonName']])->find();
            if (empty($result)) {
                $res = Db::name('addon_info')->insert($data);
            } else {
                $res = Db::name('addon_info')->where(['mpid' => $this->mid, 'addon' => $input['addonName']])->update(['infos' => json_encode($input)]);
            }
            ajaxMsg(1, '配置成功');

        } else {
            $result = Db::name('addon_info')->where(['mpid' => $this->mid, 'addon' => $name])->find();
            $addonConfigByMp = json_decode($result['infos'], true);
            $config = json_decode($this->addonCfByDb['config'], true);

            if (!empty($addonConfigByMp)) {
                foreach ($config as $key1 => $val1) {
                    foreach ($addonConfigByMp as $name => $val2) {
                        if ($val1['name'] == $name) {
                            $config[$key1] = $val1;
                            if ($val1['type'] == 'radio') {
                                foreach ($val1['value'] as $key3 => $val3) {
                                    if ($val3['value'] == $val2) {
                                        $config[$key1]['value'][$key3]['checked'] = 1;
                                    } else {
                                        $config[$key1]['value'][$key3]['checked'] = 0;
                                    }
                                }
                            } elseif ($val1['type'] == 'checkbox') {
                                foreach ($val1['value'] as $key3 => $val3) {
                                    foreach ($val2 as $key4 => $val4) {
                                        if ($val3['name'] == $key4) {
                                            $config[$key1]['value'][$key3]['checked'] = 1;
                                            break;
                                        } else {
                                            $config[$key1]['value'][$key3]['checked'] = 0;
                                        }
                                    }
                                }
                            } else {
                                $config[$key1]['value'] = $val2;
                            }
                        }
                    }
                }
            }
            $this->assign('config', $config);
            return view();
        }
    }

    public function toView($key)
    {
        $url = '';
        if (is_array($menu = $this->addonCfByFile['menu'])) {
            foreach ($menu as $key1 => $val) {
                if ($key1 == $key) {
                    $url = addonUrl($val['url'], ['mid' => $this->mid]);
                    break;
                }
            }
        }
        $this->assign('Mkey', $key);
        $this->assign('url', $url);
        return view('view');
    }


}