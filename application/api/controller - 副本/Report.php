<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/3/29 0029
 * Time: 13:45
 */
namespace app\api\controller;
use app\models\Bases;
use app\service\Redis;
use app\service\Rsa;

class Report extends Base{
    /**
     * 举报类型
     * @return \think\response\Json
     */
    public function reportType(){
        $type = new Bases('reportType');
        try{
            $list = $type->selectData(['status'=>$type::STATUS_ON],'id,type_name');
        } catch (\LogicException $e){
            return json(self::formatResult($e->getCode(),$e->getMessage()));
        }
        return json(self::formatSuccessResult($list));
    }

    /**
     * 举报
     * @return \think\response\Json
     */
    public function report(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $report = new Bases('report');
            $dynamic = new Bases('dynamic');
            /*// -----------------------
            $arr['token'] = $post['token'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);
                $info = $report->find(['reporter_id'=>$uid,'informant_id'=>$post['id']]);
                if($info){
                    throw new \LogicException('你已举报过该动态',1031);
                }

                $bool = $report->insertData(['reporter_id'=>$uid,'informant_id'=>$post['id'],'type_id'=>$post['type_id'],'create_time'=>time()]);

                // 动态添加举报数
                $bool1 = $dynamic->_setInc(['id'=>$post['id']],'report_num');
                if(!$bool || !$bool1){
                    throw new \LogicException('操作失败',1010);
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult());
        }
    }
}