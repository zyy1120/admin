<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/3/23 0023
 * Time: 9:44
 */
namespace app\api\controller;
use app\models\Bases;
use think\Controller;

class Base extends Controller{
    /**
     * 登录验证
     */
    public function checkLogin(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new \app\service\Rsa();
            $redis = new \app\service\Redis();
            $user  = new Bases('user');
            // 验证参数
            if(!isset($post['encrypt']) || !isset($post['equipment'])){
                throw new \LogicException('参数错误',1020);
            }
            // 获取解密后的数据
            $decrypt = $rsa->decrypt($post['equipment'],$post['encrypt']);
            if(!$decrypt['token']){
                throw new \LogicException('未登录',1019);
            }
            // 验证token
            $isToken = $redis->get($decrypt['token']);
            if(!$isToken){
                throw new \LogicException('登录失效',1018);
            }
            // 根据token获取用户id
            $uid = $redis->get($decrypt['token']);
            $decrypt['uid'] = $uid;
            // 获取账号状态
            $status = $user->value(['id'=>$uid],'status');
            if($status != Bases::STATUS_ON){
                throw new \LogicException('该账号已锁定',1013);
            }
            // 判断是否异地登录
            $equipment = $redis->get($uid);
            if($equipment != $post['equipment']){
                //删除token
                $redis->del($decrypt['token']);
                throw new \LogicException('您已在其他设备登录',1021);
            }
            // 返回组合数据
            unset($post['equipment']);
            unset($post['encrypt']);
            return array_merge($decrypt,$post);
        }
    }

    /**
     * ajax成功返回
     * @param null $data
     * @return array
     */
    public static function formatSuccessResult($data = ''){
        return self::formatResult(0, 'success', $data);
    }

    /**
     * ajax失败返回
     * @param $code
     * @param $errorMsg
     * @param null $data
     * @return array
     */
    public static function formatResult($code, $errorMsg, $data = ''){
        return array('code' => $code,'msg' => $errorMsg,'data'=>$data);
    }

    /**
     * 生成token
     * @param $equipment
     * @param $mobile
     * @param $password
     * @return string
     */
    public static function setToken($equipment,$mobile,$password){
        return md5(md5($equipment).md5($mobile).md5($password ));
    }
}