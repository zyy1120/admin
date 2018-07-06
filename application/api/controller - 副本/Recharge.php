<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/5/11 0011
 * Time: 15:26
 */
namespace app\api\controller;
use app\models\Bases;
use app\service\Redis;
use app\service\Rsa;

class Recharge extends Base{
    /**
     * 充值列表
     * @return \think\response\Json
     */
    public function rechargeList(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $account  = new Bases('account');
            $recharge = new Bases('rechargeType');
            // -----------------------
            /*$arr['token'] = $post['token'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);

                $list['mili'] = $account->value(['uid'=>$uid],'mili');
                $list['record'] = $recharge->selectData(['status'=>Bases::STATUS_ON]);
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($list));
        }
    }

    /**
     * 充值记录
     * @return \think\response\Json
     */
    public function rechargeRecord(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $recharge = new Bases('recharge');
            // -----------------------
            /*$arr['token'] = $post['token'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);

                $rlist = $recharge->page(['uid'=>$uid],'id desc',20,[],[],'recharge_money,mili,create_time,status',$post['page']);
                
                $list = $rlist->items();
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($list));
        }
    }
}