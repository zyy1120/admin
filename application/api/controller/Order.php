<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/3/28 0028
 * Time: 15:24
 */
namespace app\api\controller;
use app\models\Bases;
use app\service\Redis;
use app\service\Rsa;

class Order extends Base{
    /**
     * 我的订单
     * @return \think\response\Json
     */
    public function orderList(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $order = new Bases('order');
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

                $list = $order->page(['a.uid'=>$uid,'a.status'=>$order::PAY_YES],'a.id desc',20,[],[['tq_order_type b','a.type_id = b.id','LEFT'],['tq_user c','a.beneficiary_id = c.id','LEFT'],['tq_wechat_buy d','a.id = d.oid','LEFT'],['tq_dynamic e','a.dynamic_id = e.id','LEFT']],'a.id,a.beneficiary_id as uid,a.dynamic_id,c.nickname,c.portrait,a.mili as order_amount,a.create_time,b.id as type_id,b.type_name,d.status is_friend,e.is_delete',$post['page']);

            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($list->items()));
        }
    }

    /**
     * 确认添加微信好友
     * @return \think\response\Json
     */
    public function makeFriend(){
        if(request()->isPost()){
            $post       = input('param.');
            $rsa        = new Rsa();
            $order      = new Bases('order');
            $record     = new Bases('orderRecord');
            $user       = new Bases('user');
            $invite     = new Bases('invite');
            $account    = new Bases('account');
            $wechat_buy = new Bases('wechatBuy');
            $statist    = new Bases('statistics');
            $time = time();
            /*// -----------------------
            $arr['oid'] = $post['oid'];
            $arr['token'] = $post['token'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);

                $order->startTrans();
                $agent_id      = true;
                $agent_account = true;
                $agent_save    = true;
                $agent_invite  = true;
                $bool4 = true;
                $bool5 = true;
                // 获取订单信息
                $info = $order->find(['id'=>$data['oid']]);
                if($info['agent_money']){
                    // 出售微信用户有经纪人获取经济人id
                    $agent_id = $user->joinOne([['tq_user b','a.agent_id = b.id','LEFT']],['a.id'=>$info['beneficiary_id']],'b.id');
                    // 出售微信用户经济人账户操作
                    $agent_account  = $account->find(['uid'=>$agent_id['id']]);
                    $acc['amount']  = $info['agent_money'] + $agent_account['amount'];
                    $acc['account'] = $info['agent_money'] + $agent_account['account'];
                    $acc['balance'] = $info['agent_money'] + $agent_account['balance'];
                    $agent_save = $account->updateData($acc,['uid'=>$agent_id['id']]);
                    // 邀请表邀请人mili添加
                    $agent_invite = $invite->_setInc(['inviter_id'=>$agent_id['id'],'invitee_id'=>$info['beneficiary_id']],'inviter_money',$info['agent_money']);
                    // 添加获取分成交易记录
                    $bool4 = $record->insertData(['uid'=>$agent_id['id'],'money'=>$info['agent_money'],'record_type'=>'消费分成','create_time'=>$time]);
                    // 后台统计表统计总收益
                    $bool5 = $statist->insertData(['agent_id'=>$agent_id['id'],'uid'=>$info['beneficiary_id'],'amount'=>$info['order_amount'],'create_time'=>$time]);
                }
                // 受益人账户金额操作
                $ainfo  = $account->find(['uid'=>$info['beneficiary_id']]);
                //$acc1['amount']  = $ainfo['amount']  + $info['order_amount'];
                //$acc1['account'] = $ainfo['account'] + $info['income_money'];
                $acc1['balance'] = $ainfo['balance'] + $info['income_money'];
                $acc1['frozen']  = $ainfo['frozen']  - $info['income_money'];
                $bool  = $account->updateData($acc1,['uid'=>$info['beneficiary_id']]);
                // 微信交易表确认
                $bool2 = $wechat_buy->updateData(['status'=>$wechat_buy::FRIEND_YES,'create_time'=>$time],['uid'=>$info['uid'],'beneficiary_id'=>$info['beneficiary_id']]);
                // 交易记录表添加交易记录
                $bool3 = $record->insertData(['uid'=>$info['beneficiary_id'],'money'=>$info['income_money'],'record_type'=>'微信交易成功','create_time'=>$time]);

                if(!$info || !$ainfo || !$bool || !$bool2 || !$bool3 || !$bool4 || !$bool5 ||!$agent_id || !$agent_account || !$agent_save || !$agent_invite){
                    $order->rollback();
                    throw new \LogicException('操作失败',1010);
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            $order->commit();
            return json(self::formatSuccessResult());
        }
    }

    /**
     * 查看红包动态
     * @param $data
     * @return bool
     */
    public function lookDynamic($data){
        $order   = new Bases('order');
        $record  = new Bases('orderRecord');
        $type    = new Bases('userType');
        $dynamic = new Bases('dynamic');
        $invite  = new Bases('invite');
        $account = new Bases('account');
        $message = new Bases('message');
        $statist = new Bases('statistics');
        $time = time();

        // 查询动态信息
        $info = $order->joinOne([['tq_user b','a.beneficiary_id = b.id','LEFT']],['a.order_num'=>$data['order_num']],'a.dynamic_id as did,b.agent_id');
        // 获取无经纪人模特分佣比
        $ratio1 = $type->value(['id'=>$type::USER_TYPE_MODEL],'ratio');
        $order_amount   = $data['price'];
        $income_money   = $order_amount * ($ratio1/100);
        $platform_money = $order_amount - $income_money;
        $agent_money    = 0;
        $bool1 = true;
        $bool3 = true;
        $bool6 = true;
        $bool9 = true;
        if($info['agent_id'] > 0){
            // 发布动态用户有经纪人相关分成操作
            $ratio2 = $type->value(['id'=>$type::USER_TYPE_AGENT],'ratio');//经纪人分佣比
            $agent_money    = $order_amount * ($ratio2/100);
            $platform_money = $order_amount - $income_money - $agent_money;
            // 发布动态用户经纪人账户收益操作
            $ainfo = $account->find(['uid'=>$info['agent_id']]);
            $acc['amount']  = $agent_money + $ainfo['amount'];
            $acc['account'] = $agent_money + $ainfo['account'];
            $acc['balance'] = $agent_money + $ainfo['balance'];
            $bool1 = $account->updateData($acc,['uid'=>$info['agent_id']]);
            // 修改邀请表经纪人收益金额
            $bool3 = $invite->_setInc(['inviter_id'=>$info['agent_id'],'invitee_id'=>$data['beneficiary_id']],'inviter_money',$agent_money);
            // 添加获取分成交易记录
            $bool6 = $record->insertData(['uid'=>$info['agent_id'],'money'=>$agent_money,'record_type'=>'消费分成','create_time'=>$time]);
            // 后台统计表统计总收益
            $bool9 = $statist->insertData(['agent_id'=>$info['agent_id'],'uid'=>$data['beneficiary_id'],'amount'=>$order_amount,'create_time'=>$time]);
        }

        $save = [
            'trade_num'      => empty($data['trade_no']) ? '' :$data['trade_no'],
            'income_money'   => $income_money,
            'platform_money' => $platform_money,
            'agent_money'    => $agent_money,
            'status'         => $order::PAY_YES,
            'pay_time'       => $data['pay_time']
        ];
        // 修改订单
        $bool  = $order->updateData($save,['order_num'=>$data['order_num']]);
        // 发布动态用户收益账户操作
        $account_info = $account->find(['uid'=>$data['beneficiary_id']]);
        $acc1['amount']  = $account_info['amount']  + $order_amount;
        $acc1['account'] = $account_info['account'] + $income_money;
        $acc1['balance'] = $account_info['balance'] + $income_money;
        $bool2 = $account->updateData($acc1,['uid'=>$data['beneficiary_id']]);
        // 动态表购买数+1
        $bool4 = $dynamic->_setInc(['id'=>$info['did']],'look_num');
        // 发送站内消息
        $bool5 = $message->insertData(['uid'=>$data['beneficiary_id'],'send_id'=>$data['uid'],'content'=>'查看了你的红包动态','create_time'=>$time]);
        // 交易记录表添加交易记录
        $bool7 = $record->insertData(['uid'=>$data['beneficiary_id'],'money'=>$income_money,'record_type'=>'被查看红包动态','create_time'=>$time]);
        // 查看动态用户账户操作
        $bool8 = $account->_setDec(['uid'=>$data['uid']],'mili',$data['mili']);
        // 查看动态用户有邀请人，并且首次消费，给邀请人账户添加米粒
        $friend = new Bases('inviteFriend');
        $friend_info = $friend->find(['invitee_id'=>$data['uid']]);
        $bool10 = true;
        $bool11 = true;
        if($friend_info && $friend_info['status'] == Bases::NOT_COST){
            $conf = new Bases('config');
            $cost_mili = $conf->value(['cname'=>'invite_cost'],'option');
            $bool10 = $friend->setField(['invitee_id'=>$data['uid']],['status'=>Bases::IS_COST]);
            $mili = $account->value(['uid'=>$friend_info['inviter_id']],'mili');
            $mili = $mili + $cost_mili;
            $bool11 = $account->updateData(['mili'=>$mili],['uid'=>$friend_info['inviter_id']]);
        }

        if(!$bool || !$bool1 || !$bool2 || !$bool3 || !$bool4 || !$bool5 || !$bool6 || !$bool7 || !$bool8 || !$bool9 || !$bool10 || !$bool11){
            $dynamic->rollback();
            throw new \LogicException('操作失败',1010);
        }
        return true;
    }

    /**
     * 购买微信
     * @param $data
     * @return bool
     */
    public function buyWechat($data){
        $order   = new Bases('order');
        $user    = new Bases('user');
        $record  = new Bases('orderRecord');
        $type    = new Bases('userType');
        $account = new Bases('account');
        $message = new Bases('message');
        $wechat_buy = new Bases('wechat_buy');
        $time = time();

        // 获取用户信息
        $info = $order->joinOne([['tq_user b','a.beneficiary_id = b.id','LEFT']],['a.order_num'=>$data['order_num']],'a.id as oid,a.wechat,b.agent_id');
        // 获取无经纪人模特分佣比
        $ratio1 = $type->value(['id'=>$type::USER_TYPE_MODEL],'ratio');
        $income_money   = $data['price'] * ($ratio1/100);
        $platform_money = $data['price'] - $income_money;
        $agent_money    = 0;
        if($info['agent_id'] > 0){
            // 发布动态用户有经纪人相关分成操作
            $ratio2 = $type->value(['id'=>$type::USER_TYPE_AGENT],'ratio');//经纪人分佣比
            $agent_money    = $data['price'] * ($ratio2/100);
            $platform_money = $data['price'] - $income_money - $agent_money;
        }

        $orderSave = [
            'trade_num'      => empty($data['trade_no']) ? '' :$data['trade_no'],
            'income_money'   => $income_money,
            'platform_money' => $platform_money,
            'agent_money'    => $agent_money,
            'status'         => $order::PAY_YES,
            'pay_time'       => $data['pay_time']
        ];
        // 修改订单
        $bool = $order->updateData($orderSave,['order_num'=>$data['order_num']]);
        // 出售微信用户账户添加冻结金额
        $bool1 = $account->_setInc(['uid'=>$data['beneficiary_id']],'frozen',$income_money);
        $bool6 = $account->_setInc(['uid'=>$data['beneficiary_id']],'account',$income_money);
        $bool7 = $account->_setInc(['uid'=>$data['beneficiary_id']],'amount',$data['price']);
        // 发送站内消息
        $bool2 = $message->insertData(['uid'=>$data['beneficiary_id'],'send_id'=>$data['uid'],'content'=>'购买了你的微信','create_time'=>$time]);
        // 交易记录表添加交易记录
        $bool3 = $record->insertData(['uid'=>$data['beneficiary_id'],'money'=>$income_money,'record_type'=>'出售微信号','status'=>1,'create_time'=>$time]);
        // 修改购买微信的支付状态
        $bool4 = $wechat_buy->updateData(['is_pay'=>$wechat_buy::PAY_YES,'oid'=>$info['oid'],'wechat'=>$info['wechat']],['uid'=>$data['uid'],'beneficiary_id'=>$data['beneficiary_id']]);
        // 购买微信用户账户操作
        $bool5 = $account->_setDec(['uid'=>$data['uid']],'mili',$data['mili']);
        // 查看动态用户有邀请人，并且首次消费，给邀请人账户添加米粒
        $friend = new Bases('inviteFriend');
        $friend_info = $friend->find(['invitee_id'=>$data['uid']]);
        $bool8 = true;
        $bool9 = true;
        if($friend_info && $friend_info['status'] == Bases::NOT_COST){
            $conf = new Bases('config');
            $cost_mili = $conf->value(['cname'=>'invite_cost'],'option');
            $bool8 = $friend->setField(['invitee_id'=>$data['uid']],['status'=>Bases::IS_COST]);
            $mili = $account->value(['uid'=>$friend_info['inviter_id']],'mili');
            $mili = $mili + $cost_mili;
            $bool9 = $account->updateData(['mili'=>$mili],['uid'=>$friend_info['inviter_id']]);
        }

        if(!$info || !$bool || !$bool1 || !$bool2 || !$bool3 || !$bool4 || !$bool5 || !$bool6 || !$bool7 || !$bool8 || !$bool9){
            $user->rollback();
            throw new \LogicException('操作失败',1010);
        }
        return true;
    }

    /**
     * 打赏
     * @param $data
     * @return bool
     */
    public function reward($data){
        $order = new Bases('order');
        $record  = new Bases('orderRecord');
        $type    = new Bases('userType');
        $invite  = new Bases('invite');
        $account = new Bases('account');
        $message = new Bases('message');
        $statist = new Bases('statistics');
        $time = time();

        // 获取用户信息
        $info = $order->joinOne([['tq_user b','a.beneficiary_id = b.id','LEFT']],['a.order_num'=>$data['order_num']],'b.agent_id');

        // 获取无经纪人模特分佣比
        $ratio1 = $type->value(['id'=>$type::USER_TYPE_MODEL],'ratio');
        $income_money   = $data['price'] * ($ratio1/100);
        $platform_money = $data['price'] - $income_money;
        $agent_money    = 0;
        $bool1 = true;
        $bool3 = true;
        $bool5 = true;
        $bool8 = true;
        if($info['agent_id'] > 0){
            // 被打赏用户有经纪人获取分成后金额
            $ratio2 = $type->value(['id'=>$type::USER_TYPE_AGENT],'ratio');//经纪人分佣比
            $agent_money    = $data['price'] * ($ratio2/100);
            $platform_money = $data['price'] - $income_money - $agent_money;
            // 被打赏用户经纪人账户收益操作
            $ainfo = $account->find(['uid'=>$info['agent_id']]);
            $acc['amount']  = $agent_money + $ainfo['amount'];
            $acc['account'] = $agent_money + $ainfo['account'];
            $acc['balance'] = $agent_money + $ainfo['balance'];
            $bool1 = $account->updateData($acc,['uid'=>$info['agent_id']]);
            // 修改邀请表被打赏用户经纪人收益金额
            $bool3 = $invite->_setInc(['inviter_id'=>$info['agent_id'],'invitee_id'=>$data['beneficiary_id']],'inviter_money',$agent_money);
            // 添加获取分成交易记录
            $bool5 = $record->insertData(['uid'=>$info['agent_id'],'money'=>$agent_money,'record_type'=>'消费分成','create_time'=>$time]);
            // 后台统计表统计总收益
            $bool8 = $statist->insertData(['agent_id'=>$info['agent_id'],'uid'=>$data['beneficiary_id'],'amount'=>$data['price'],'create_time'=>$time]);
        }

        $save = [
            'trade_num'      => empty($data['trade_no']) ? '' :$data['trade_no'],
            'income_money'   => $income_money,
            'platform_money' => $platform_money,
            'agent_money'    => $agent_money,
            'status'         => $order::PAY_YES,
            'pay_time'       => $data['pay_time']
        ];
        // 修改订单
        $bool = $order->updateData($save,['order_num'=>$data['order_num']]);

        // 发布动态用户收益账户操作
        $ainfo = $account->find(['uid'=>$data['beneficiary_id']]);
        $acc1['amount']  = $ainfo['amount']  + $data['price'];
        $acc1['account'] = $ainfo['account'] + $income_money;
        $acc1['balance'] = $ainfo['balance'] + $income_money;
        $bool2 = $account->updateData($acc1,['uid'=>$data['beneficiary_id']]);
        // 发送站内消息
        $bool4 = $message->insertData(['uid'=>$data['beneficiary_id'],'send_id'=>$data['uid'],'content'=>'打赏了你','create_time'=>$time]);
        // 交易记录表添加交易记录
        $bool6 = $record->insertData(['uid'=>$data['beneficiary_id'],'money'=>$income_money,'record_type'=>'被打赏红包','create_time'=>$time]);
        // 打赏用户账户操作
        $bool7 = $account->_setDec(['uid'=>$data['uid']],'mili',$data['mili']);
        // 查看动态用户有邀请人，并且首次消费，给邀请人账户添加米粒
        $friend = new Bases('inviteFriend');
        $friend_info = $friend->find(['invitee_id'=>$data['uid']]);
        $bool9 = true;
        $bool10 = true;
        if($friend_info && $friend_info['status'] == Bases::NOT_COST){
            $conf = new Bases('config');
            $cost_mili = $conf->value(['cname'=>'invite_cost'],'option');
            $bool9 = $friend->setField(['invitee_id'=>$data['uid']],['status'=>Bases::IS_COST]);
            $mili = $account->value(['uid'=>$friend_info['inviter_id']],'mili');
            $mili = $mili + $cost_mili;
            $bool10 = $account->updateData(['mili'=>$mili],['uid'=>$friend_info['inviter_id']]);
        }

// file_put_contents('zkk.txt',$bool);
// file_put_contents('zkk1.txt',$bool1);
// file_put_contents('zkk2.txt',$bool2);
// file_put_contents('zkk3.txt',$bool3);
// file_put_contents('zkk4.txt',$bool4);
// file_put_contents('zkk5.txt',$bool5);
// file_put_contents('zkk6.txt',$bool6);
// file_put_contents('zkk7.txt',$bool7);
// file_put_contents('zkk8.txt',$bool8);
// file_put_contents('zkk9.txt',$bool9);
// file_put_contents('zkk10.txt',$bool10);
        if(!$bool || !$bool1 || !$bool2 || !$bool3 || !$bool4 || !$bool5 || !$bool6 || !$bool7 || !$bool8 || !$bool9 || !$bool10){
            $order->rollback();
            throw new \LogicException('操作失败',1010);
        }
        return true;
    }

    /**
     * 交易记录
     * @return \think\response\Json
     */
    public function orderRecord(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $order = new Bases('orderRecord');
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

                $list = $order->page(['uid'=>$uid],'id desc',20,[],[],'*',$post['page']);
                $list = $list->items();

            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($list));
        }
    }

    /**
     * 检查米粒是否足够，选择支付方式
     * @return \think\response\Json
     */
    public function payMethod(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $account = new Bases('account');
            // -----------------------
            /*$arr['token'] = $post['token'];
            $arr['mili'] = $post['mili'];
            $arr['type_id'] = $post['type_id'];
            $arr['did'] = $post['did'];
            $arr['uid'] = $post['uid'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);
                // 判断支付方式
                $mili  = $account->value(['uid'=>$uid],'mili');
                $result = 1;
                if($mili < $data['mili']){
                    $result = 2;
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult(['status'=>$result,'mili'=>$mili]));
        }
    }

    /**
     * 米粒支付
     * @return \think\response\Json
     */
    public function MiliPay(){
        if(request()->isPost()){
            $post   = input('param.');
            $rsa    = new Rsa();
            $redis  = new Redis();
            $config = new Bases('config');
            $order  = new Bases('order');
            $account = new Bases('account');
            /*// -----------------------
            $arr['token'] = $post['token'];
            //$arr['did']   = $post['did'];
            $arr['uid']   = $post['uid'];
            $arr['total_amount']   = $post['total_amount'];
            $arr['type_id'] = $post['type_id'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);

                $order_num    = date('YmdHis').rand(00001,99999);   // 生成订单号
                $ratio  = $config->value(['cname'=>'mili'],'option');
                $mili = $data['total_amount'];
                $total_amount = $mili / $ratio;

                // 红包动态获取动态信息
                if($data['type_id'] == Bases::ORDER_TYPE_HONGBAO){
                    $order  = new Bases('order');
                    $dynamic = new Bases('dynamic');
                    $is_buy = $order->find(['dynamic_id'=>$data['did'],'uid'=>$uid,'type_id'=>$order::ORDER_TYPE_HONGBAO,'status'=>$order::PAY_YES]);
                    if($is_buy){
                        throw new \LogicException('您已购买过该红包动态',1026);
                    }
                    $info = $dynamic->find(['id'=>$data['did']],'uid,is_delete');
					if($info['is_delete'] == 1){
						throw new \LogicException('该动态已经被删除!',1026);
					}
                }
                // 判断该用户微信是否下架
                if($data['type_id'] == Bases::ORDER_TYPE_WECHAT){
                    $wechat  = new Bases('wechat');
                    $is_sell = $wechat->joinOne([['tq_user b','a.uid = b.id','LEFT']],['uid'=>$data['uid'],'a.status'=>$wechat::SELL_YES,'b.status'=>$wechat::STATUS_ON],'a.money,b.agent_id');
                    if(!$is_sell){
                        throw new \LogicException('该用户微信已下架',1027);
                    }
                }
                // 判断米粒是否足够
                $acc_mili  = $account->value(['uid'=>$uid],'mili');
                if($acc_mili < $mili){
                    throw new \LogicException('米粒不足',1031);
                }

                $order->startTrans();

                // 向数据库添加订单
                $add = [
                    'uid'            => $uid,
                    'beneficiary_id' => $data['type_id'] == Bases::ORDER_TYPE_HONGBAO ? $info['uid'] : $data['uid'],
                    'type_id'        => $data['type_id'],
                    'dynamic_id'     => $data['type_id'] == Bases::ORDER_TYPE_HONGBAO ? $data['did'] : 0,
                    'wechat'         => $data['type_id'] == Bases::ORDER_TYPE_WECHAT ? $post['wechat'] : '',
                    'order_num'      => $order_num,
                    'payment'        => Bases::PAY_METHOD_MILI,
                    'order_amount'   => $total_amount ,
                    'mili'           => $mili ,
                    'create_time'    => time(),
                ];
                $oid = $order->insertData($add);
                if(!$oid){
                    $order->rollback();
                    throw new \LogicException('操作失败',1010);
                }
                // 微信购买表添加购买记录
                if($data['type_id'] == Bases::ORDER_TYPE_WECHAT){
                    $wechat_buy = new Bases('wechatBuy');
                    $wechatAdd  = [
                        'oid'            => $oid,
                        'uid'            => $uid,
                        'beneficiary_id' => $data['uid'],
                        'wechat'         => $post['wechat'],
                    ];
                    $wechat_info = $wechat_buy->find(['uid'=>$uid,'beneficiary_id'=>$data['uid']]);
                    if(!$wechat_info){
                        $result = $wechat_buy->insertData($wechatAdd);
                        if(!$result){
                            $order->rollback();
                            throw new \LogicException('操作失败',1010);
                        }
                    }
                }

                // 组合传递参数
                $param['uid']       = $uid;
                $param['mili']      = $mili;
                $param['price']     = $total_amount;
                $param['order_num'] = $order_num;
                $param['pay_time']  = time();
                $param['beneficiary_id'] = $data['type_id'] == Bases::ORDER_TYPE_HONGBAO ? $info['uid'] : $data['uid'];
                if ($data['type_id'] == Bases::ORDER_TYPE_HONGBAO) {
                    $this->lookDynamic($param);
                }
                if ($data['type_id'] == Bases::ORDER_TYPE_REWARD) {
                    $this->reward($param);
                }
                if ($data['type_id'] == Bases::ORDER_TYPE_WECHAT) {
                    $this->buyWechat($param);
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
	
            $order->commit();
            return json(self::formatSuccessResult());
        }
    }
}