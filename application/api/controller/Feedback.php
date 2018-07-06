<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/3/29 0029
 * Time: 16:07
 */
namespace app\api\controller;
use app\models\Bases;
use app\service\Redis;
use app\service\Rsa;

class Feedback extends Base {
    /**
     * 反馈
     * @return \think\response\Json
     */
    public function feedback(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $feedBack = new Bases('feedback');
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

                // 场景验证
                unset($post['equipment']);
                $validate = $this->validate($post,'Feedback.feedback');
                if(true !== $validate){
                    throw new \LogicException($validate,1011);
                }

                $bool = $feedBack->insertData(['uid'=>$uid,'content'=>$post['content'],'create_time'=>time()]);
                if(!$bool){
                    throw new \LogicException('操作失败',1010);
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult());
        }
    }
}