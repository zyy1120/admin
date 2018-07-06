<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/3/28 0028
 * Time: 14:29
 */
namespace app\api\controller;
use app\models\Bases;
use app\service\Redis;
use app\service\Rsa;

class Account extends Base {
    /**
     * 用户账户
     * @return \think\response\Json
     */
    public function account(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $account = new Bases('account');
            $order = new Bases('order');
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

                $info = $account->find(['uid'=>$uid],'account,balance,frozen');

                // 7天内我作为经纪人的收益
                $time = time() - 3600 * 24 * 7;
                $agent = $order->joinAll([['tq_user b','a.beneficiary_id = b.id','RIGHT'],['tq_wechat_buy c','a.id = c.oid','LEFT']],"b.agent_id = $uid and a.create_time > $time and a.status = ".$order::PAY_YES,'a.agent_money,c.status,a.type_id');
                $m = 0;
                if($agent){
                    foreach($agent as $v){
                        if($v['status'] == $order::FRIEND_YES || $v['status'] === null){
                            $m += $v['agent_money'];
                        }
                        //$m += $v['agent_money'];
                    }
                }
                // 7天内我作为用户的收益
                //$orderList = $order->joinAll([['tq_wechat_buy b','a.id = b.oid','LEFT']],"a.beneficiary_id = $uid and a.create_time > $time and a.status = ".$order::PAY_YES,'a.income_money,a.type_id,b.status');
                $orderList = $order->selectData("beneficiary_id = $uid and create_time > $time and status = ".$order::PAY_YES,'income_money');
                $income_money = 0;
                if($orderList){
                    foreach($orderList as $k=>$v){
                        /*if($v['status'] == $order::FRIEND_YES || $v['status'] === null){
                            $income_money += $v['income_money'];
                        }*/
                        $income_money += $v['income_money'];
                    }
                }
                $money = $m + $income_money;
                $info['seven_day'] = sprintf('%.2f',$money);

            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($info));
        }
    }
}