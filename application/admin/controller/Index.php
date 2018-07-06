<?php
namespace app\admin\controller;

use think\Controller;

class Index extends Common
{
    public function index()
    {
        $loginLogList = $this->loginLogList();              //最新登录8条信息
        $this->assign('loginLogList', $loginLogList);
        
        $loginLogLineJson = $this->loginLogLineJson();      //最近30天登录统计json
        $this->assign('loginLogLineJson', $loginLogLineJson);
        
        $groupPieJson = $this->groupPieJson();              //用户组人数统计
        $this->assign('groupPieJson', $groupPieJson);
        
        $systemConfig = $this->systemConfig();              //服务器信息配置
        $this->assign('systemConfig', $systemConfig);
        
        return $this->fetch();
    }
    /**
     * @Title: loginLogList
     * @Description: todo(最新登录8条信息)
     * @author zyy
     * @date 2017年8月14日
     * @throws
     */
    private function loginLogList()
    {
        $loginLogModel = new \app\admin\model\LoginLog;
        return $loginLogList = $loginLogModel->limit(8)->order('id DESC')->select();
    }
    
    /**
     * @Title: loginLogLineJson
     * @Description: todo(最近30天登录统计json)
     * @author zyy
     * @date 2017年8月14日
     * @throws
     */
    private function loginLogLineJson()
    {
        $loginLogModel = new \app\admin\model\LoginLog;
        $userModel = new \app\admin\model\User;
        
        $nowTime = strtotime(date('Ymd', time())) + 86400;
        $loginLogLineTime = $nowTime - 2592000;
        $loginLogLine = $loginLogModel->where('create_time', 'between', [$loginLogLineTime, $nowTime])->select();
        $loginLogLineArr = [];
        $loginLogLineArr['datasets'][0]['label'] = lang('login_count');
        $loginLogLineArr['datasets'][0]['fill'] = false;
        $loginLogLineArr['datasets'][0]['borderColor'] = '#4bc0c0';
        foreach ($loginLogLine as $k=>$v){
            $labels = date('Y-m-d', strtotime($v['create_time']));
            if (!in_array($labels, $loginLogLineArr['labels'])){
                $loginLogLineArr['labels'][] = date('Y-m-d', strtotime($v['create_time']));
            }
            $loginLogLineArr['datasets']['data'][$labels] += 1;
        }
        $old = $loginLogLineArr['datasets']['data'];
        unset($loginLogLineArr['datasets']['data']);
        foreach ($old as $v){
            $loginLogLineArr['datasets'][0]['data'][] = $v;
        }
        return $loginLogLineJson = json_encode($loginLogLineArr);
    }
    
    /**
     * @Title: groupPieJson
     * @Description: todo(用户组人数统计)
     * @author zyy
     * @date 2017年8月14日
     * @throws
     */
    private function groupPieJson()
    {
        $agModel = new \app\admin\model\AuthGroup;
        $userModel = new \app\admin\model\User;
        
        $groupPieArr = [];
        
        $agData = $agModel->where('status', 1)->select();
        $noGroup = ['id' => '0', 'title' => '无角色用户', 'pic' => '#666'];
        $agData[count($array)-1] = $noGroup;
        foreach($agData as $k => $v){
            $agData[$k]['count'] = 0;
            $groupPieArr['labels'][] = $v['title'];
        }
        $userData = $userModel->select();
        foreach ($userData as $k=>$v){
            $userGroup = $v->userGroup;
            if(!empty($userGroup)){
                foreach ($userGroup as $k2 => $v2){
                    foreach ($agData as $k3 =>$v3){
                        if ($v3['id'] == $v2['group_id']){
                            $agData[$k3]['count'] += 1;
                            break;
                        }
                    }
                }
            }else{
                $agData[count($array)-1]['count'] += 1;
            }
        }
        foreach($agData as $k=>$v){
            $groupPieArr['datasets'][0]['data'][] = $v['count'];
            $groupPieArr['datasets'][0]['backgroundColor'][] = $v['pic'];
        }
        return $groupPieJson = json_encode($groupPieArr);
    }
    
    /**
     * @Title: systemConfig
     * @Description: todo(服务器信息配置)
     * @author zyy
     * @date 2017年8月14日
     * @throws
     */
    private function systemConfig()
    {
        return $config = [
            '操作系统' => PHP_OS,
            '服务器时间' => date("Y-n-j H:i:s"),
            '运行环境' => $_SERVER["SERVER_SOFTWARE"],
            'PHP运行方式' => php_sapi_name(),
            '上传附件限制' => ini_get('upload_max_filesize'),
            '执行时间限制' => ini_get('max_execution_time').'秒',
        ];
    }
    
    
    /**
     * @Title: cleanCache
     * @Description: todo(清除缓存)
     * @author zyy
     * @date 2017年8月14日
     * @throws
     */
    public function cleanCache()
    {
        if (request()->isPost()){
            deldir(RUNTIME_PATH);
            return ajaxReturn(lang('action_success'));
        }else{
            deldir(RUNTIME_PATH);
            return $this->fetch();
        }
    }
    
    
    
    
    public function icons()
    {
        return $this->fetch();
    }
    
    public function forms()
    {
        return $this->fetch();
    }
    
    public function box()
    {
        return $this->fetch();
    }
    
    public function tab()
    {
        return $this->fetch();
    }
    
    public function tables()
    {
        return $this->fetch();
    }
    
    public function question()
    {
        return $this->fetch();
    }
}
