<?php
namespace app\api\controller;
use app\models\Bases;
use app\service\AlipayTradeAppPayRequest;
use app\service\AopClient;
use app\service\AopEncrypt;
use app\service\Redis;
use app\service\Rsa;

class Alipay extends Base {
    /**
     * 添加判断，         ios or Android
     * body              商品描述
     * total_amount      订单金额 只能为整数 单位为元
     * beneficiary_id    订单收益用户id
     * type_id           1红包动态 2打赏红包 3购买微信
     * dynamic_id        动态id
     * payment           支付方式，1支付宝，2微信，3银联
     * order_num = outtradeno  订单号
     * token
     * equipment
     *
     */
    public function payApply(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $order = new Bases('order');
            try{
                // 登录验证
                self::checkLogin();
                // 数据解密
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                //根据token获取用户id
                $uid = $redis->get($data['token']);
				//查看动态是否存在
			
                // 如果是动态红包则查询动态信息
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
                    $wechat_buy = new Bases('wechat_buy');
                    $is_buy = $wechat_buy->find(['uid'=>$uid,'beneficiary_id'=>$data['uid']]);
                    if($is_buy){
                        throw new \LogicException('您已购买过该微信',1038);
                    }
                }
                $config = new Bases('config');
                $ratio = $config->value(['cname'=>'mili'],'option');
                $mili = $data['total_amount'];
                $total_amount = $mili / $ratio;
                $out_trade_no = date('YmdHis').rand(00001,99999);   // 生成订单号
                $subject      = $post['subject'];

                // 添加充值记录
                $recharge = new Bases('recharge');
                $rechargeAdd = [
                    'uid'            => $uid,
                    'recharge_money' => $total_amount,
                    'plat_money'     => $total_amount,
                    'mili'           => $mili,
                    'payment'        => Bases::PAY_METHOD_ALIPAY,
                    'order_num'      => $out_trade_no,
                    'platform'       => Bases::ANDROID,
                    'create_time'    => time()
                ];
                $rid = $recharge->insertData($rechargeAdd);
                if(!$rid){
                    throw new \LogicException('操作失败',1010);
                }

                // 向数据库添加订单
                $add = [
                    'uid'            => $uid,
                    'beneficiary_id' => $data['type_id'] == Bases::ORDER_TYPE_HONGBAO ? $info['uid'] : $data['uid'],
                    'type_id'        => $data['type_id'],
                    'dynamic_id'     => $data['type_id'] == Bases::ORDER_TYPE_HONGBAO ? $data['did'] : 0,
                    'wechat'         => $data['type_id'] == Bases::ORDER_TYPE_WECHAT ? $post['wechat'] : '',
                    'order_num'      => $out_trade_no,
                    'payment'        => Bases::PAY_METHOD_ALIPAY,
                    'order_amount'   => $total_amount,
                    'mili'           => $mili,
                    'create_time'    => time(),
                ];
                $oid = $order->insertData($add);
                if(!$oid){
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
                            throw new \LogicException('操作失败',1010);
                        }
                    }
                }
                // 支付宝支付
                $response = $this->alipay($subject,$out_trade_no,$total_amount);

            } catch (\LogicException $e) {
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult(['result'=>$response]));
        }
    }

    /**
     * android充值
     * @return \think\response\Json
     */
    public function androidRecharge(){
        if(request()->isPost()){
            $post   = input('param.');
            $rsa    = new Rsa();
            $redis  = new Redis();
            $config = new Bases('config');
            $recharge = new Bases('recharge');
            $type = new Bases('rechargeType');
            // -----------------------
            /*$arr['token'] = $post['token'];
            $arr['total_amount'] = $post['total_amount'];
            $arr['rid'] = $post['rid'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                self::checkLogin();
                // 数据解密
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                //根据token获取用户id
                $uid = $redis->get($data['token']);
                if(empty($data['total_amount'])){
                    // 获取充值产品的信息
                    $info = $type->find(['id'=>$data['rid']]);
                }

                $out_trade_no = date('YmdHis').rand(00001,99999);   // 生成订单号
                $total_amount = empty($data['total_amount']) ? $info['type_money'] : $data['total_amount'];
                $subject      = $post['subject'];

                // 添加充值记录
                $ratio = $config->value(['cname'=>'mili'],'option');
                $mili = $total_amount * $ratio;
                $rechargeAdd = [
                    'uid'            => $uid,
                    'recharge_money' => $total_amount,
                    'plat_money'     => $total_amount,
                    'mili'           => $mili,
                    'payment'        => Bases::PAY_METHOD_ALIPAY,
                    'order_num'      => $out_trade_no,
                    'platform'       => Bases::ANDROID,
                    'create_time'    => time()
                ];
                $rid = $recharge->insertData($rechargeAdd);
                if(!$rid){
                    throw new \LogicException('操作失败',1010);
                }
                // 支付宝支付
                $response = $this->alipay($subject,$out_trade_no,$total_amount);

            } catch (\LogicException $e) {
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult(['result'=>$response]));
        }
    }

    /**
     * 支付宝支付
     * @param $subject
     * @param $out_trade_no
     * @param $total_amount
     * @return string
     */
    protected function alipay($subject,$out_trade_no,$total_amount){
        // 开始支付
        $aop = new AopClient;
        $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        $aop->appId = Config('Alipay.appid');
        $aop->rsaPrivateKey = Config('Alipay.rsaPrivateKey');
        $aop->alipayrsaPublicKey = Config('Alipay.alipayrsaPublicKey');
        $aop->format = "json";
        $aop->charset = "UTF-8";
        $aop->signType = "RSA2";
        //实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
        $request = new AlipayTradeAppPayRequest();
        //SDK已经封装掉了公共参数，这里只需要传入业务参数
        $timeout_express = '10m';
        $product_code    = 'QUICK_MSECURITY_PAY';
        $bizcontent = "{
                     \"subject\": \"$subject\","
            . "\"out_trade_no\": \"$out_trade_no\","
            . "\"timeout_express\": \"$timeout_express\","
            . "\"total_amount\": \"$total_amount\","
            . "\"product_code\":\"$product_code\""
            . "}";
        // 回调接口
        $request->setNotifyUrl('https://'.$_SERVER['HTTP_HOST'].DS.'api/Alipaynotify/notify');
        $request->setBizContent($bizcontent);
        //这里和普通的接口调用不同，使用的是sdkExecute
        $response = @$aop->sdkExecute($request);
        return $response;
    }

    /**
     * 支付完成返回支付状态
     * @return \think\response\Json
     */
    public function payComplete(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $recharge = new Bases('recharge');
            /*// -----------------------
            $arr['token'] = $post['token'];
            $arr['order_num'] = $post['order_num'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                $status = $recharge->value(['order_num'=>$data['order_num']],'status');
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult(['status'=>$status]));
        }
    }
}