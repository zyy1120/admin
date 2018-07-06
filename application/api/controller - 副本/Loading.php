<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/3/23 0023
 * Time: 11:11
 */
namespace app\api\controller;
use app\models\Bases;
use app\service\Redis;
use app\service\Rsa;

class Loading extends Base{
    /**
     * 闪屏页获取公钥
     * @return \think\response\Json
     */
    public function sendKey(){
        if(request()->isPost()){
            $post = input('param.');
            $rsa  = new Rsa();
            try{
                // 验证参数
                if(!isset($post['equipment'])){
                    throw new \LogicException('参数错误',1020);
                }
                $result = $rsa->generateKey($post['equipment']);
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($result));
        }
    }

    public function sendKeyV1_2(){
        if(request()->isPost()){
            $post = input('param.');
            $rsa  = new Rsa();
            try{
                // 验证参数
                if(!isset($post['equipment'])){
                    throw new \LogicException('参数错误',1020);
                }
                $result = $rsa->generateKeyV1_2($post['equipment']);
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($result));
        }
    }

    /**
     * 检查token是否失效
     * @return \think\response\Json
     */
    public function checkToken(){
        if(request()->isPost()) {
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            try{
                // 验证参数
                if(!isset($post['encrypt']) || !isset($post['equipment'])){
                    throw new \LogicException('参数错误',1020);
                }

                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);

                // 根据token获取用户id
                $uid = $redis->get($data['token']);
                if(!$uid){
                    throw new \LogicException('登录失效',1018);
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult());
        }
    }

    public function userFrom(){
        $from = new Bases('userFrom');
        $list = $from->selectData();
        return json(self::formatSuccessResult($list));
    }
}