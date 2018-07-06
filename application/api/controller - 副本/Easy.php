<?php
namespace app\api\controller;
use app\models\Bases;
use app\service\Constants;
use app\service\Xml;
use app\service\Tools;
use app\service\TransactionClientApi;
use app\service\TransactionClientSdk;
use think\config;
use app\service\Redis;
use app\service\Rsa;

class Easy extends Base {
    /**
     * 订单快捷支付接口(纯API接口版本)
     * @param merchantId        商户代码
     * @param industryId        商户行业编号; 未上送此字段时，系统将使用商户配置中对应的行业
     * @param merchOrderId      商户订单号
     * @param amount            商户订单金额，单位为元，格式： nnnnnn.nn
     * @param orderDesc         商户订单描述    字符最大128，中文最多40个；参与签名：采用UTF-8编码
     * @param tradeTime         商户订单提交时间，格式：yyyyMMddHHmmss，超过订单超时时间未支付，订单作废；不提交该参数，采用系统的默认时间（从接收订单后超时时间为30分钟）
     * @param expTime           交易超时时间，格式：yyyyMMddHHmmss， 超过订单超时时间未支付，订单作废；不提交该参数，采用系统的默认时间（从接收订单后超时时间为30分钟）
     * @param notifyUrl         异步通知URL
     * @param extData           商户保留信息； 通知结果时，原样返回给商户；字符最大128，中文最多40个；参与签名：采用UTF-8编码
     * @param miscData          订单扩展信息   根据不同的行业，传送的信息不一样；参与签名：采用UTF-8编码
     * @param notifyFlag        订单通知标志    0：成功才通知，1：全部通知（成功或失败）  不填默认为“1：全部通知”
     * @param mercPriKey        商户签名的私钥
     * @param payecoPubKey      易联签名验证公钥
     * @param payecoUrl         易联服务器URL地址，只需要填写域名部分
     * @param retXml            通讯返回数据；当不是通讯错误时，该对象返回数据
     * @return  处理状态码： 0000 : 处理成功， 其他： 处理失败
     * @throws Exception        E101:通讯失败； E102：签名验证失败；  E103：签名失败；
     */
      public function Merchant() {
      if(request()->isPost()){
          $post    = input('param.');
          $rsa     = new Rsa();
          $redis   = new Redis();
          $order  = new Bases('order');
          try{
            // 登录验证
            self::checkLogin();
            // $arr['token'] = $post['token'];
            // $arr['amount'] =$post['amount'];
            // $arr['type_id'] = $post['type_id'];
            // $arr['did'] = $post['did'];
            // $key = $rsa->generateKey($post['equipment']);
            // $post['encrypt'] = $rsa->encrypt($key,$arr);
            $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
            //根据token获取用户id
            $uid = $redis->get($data['token']);
            // 如果是动态红包则查询动态信息
                if($post['type_id'] == Bases::ORDER_TYPE_HONGBAO){
                    // 查询动态信息
                    $dynamic = new Bases('dynamic');
                    $info = $dynamic->find(['id'=>$data['did']],'uid');
                }
                //设置参数
                $config         = new Bases('config');
                $ratio          = $config->value(['cname'=>'mili'],'option');
                $mili           = $data['amount'];
                $amount         = $mili / $ratio;  
                $orderDesc      = $post['orderDesc'];  //订单描述
                $merchOrderId   = Tools::currentTimeMillis();  // 订单号

                // 添加充值记录
                $recharge = new Bases('recharge');
                $rechargeAdd = [
                    'uid'            => $uid,
                    'recharge_money' => $amount,
                    'plat_money'     => $amount,
                    'mili'           => $mili,
                    'payment'        => Bases::PAY_METHOD_UNIONPAY,
                    'order_num'      => $merchOrderId,
                    'platform'       => Bases::ANDROID,
                    'create_time'    => time()
                ];
                $rid = $recharge->insertData($rechargeAdd);
                if(!$rid){
                    throw new \LogicException('操作失败',1010);
                }

            //提交的充值数据
            $datas =[
            'uid'            => $uid,
            'beneficiary_id' => $post['type_id'] == Bases::ORDER_TYPE_HONGBAO ? $info['uid'] : $data['uid'],
            'type_id'        => $post['type_id'],
            'dynamic_id'     => $post['type_id'] == Bases::ORDER_TYPE_HONGBAO ? $data['did'] : 0,
            'wechat'         => $post['type_id'] == Bases::ORDER_TYPE_WECHAT ? $data['wechat'] : '',
            'order_num'      => $merchOrderId,
            'payment'        => Bases::PAY_METHOD_UNIONPAY,
            'order_amount'   => $amount,
            'mili'           => $mili,
            'create_time'    => time() 
            ];
            $oid = $order->insertData($datas);
            if(!$oid){
                throw new \LogicException('操作失败',1010);
            }
            // 微信购买表添加购买记录
                if($post['type_id'] == Bases::ORDER_TYPE_WECHAT){
                    $wechat_buy = new Bases('wechatBuy');
                    $wechatAdd  = [
                        'oid'            => $oid,
                        'uid'            => $uid,
                        'beneficiary_id' => $data['uid'],
                        'wechat'         => $data['wechat'],
                    ];
                    $wechat_info = $wechat_buy->find(['uid'=>$uid,'beneficiary_id'=>$data['uid']]);
                    if(!$wechat_info){
                        $result = $wechat_buy->insertData($wechatAdd);
                        if(!$result){
                            throw new \LogicException('操作失败',1010);
                        }
                    }
                }
            // 调用下单接口
            $res = $this->Recharge($amount,$orderDesc,$merchOrderId);
            return $res;
            } catch (\LogicException $e) {
                    return json(self::formatResult($e->getCode(),$e->getMessage()));
                }
            }
        }



    /**
     * android充值
     * @return \think\response\Json
     */
    public function androidRecharge(){
        if(request()->isPost()){
            $post     = input('param.');
            $rsa      = new Rsa();
            $redis    = new Redis();
            $config   = new Bases('config');
            $recharge = new Bases('recharge');
            $type     = new Bases('rechargeType');
            try{
                // 登录验证
                self::checkLogin();
                // 数据解密
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                //根据token获取用户id
                $uid = $redis->get($data['token']);
                if(empty($data['amount'])){
                    // 获取充值产品的信息
                    $info = $type->find(['id'=>$data['rid']]);
                }

                $merchOrderId = Tools::currentTimeMillis();   // 生成订单号
                $amount       = empty($data['amount']) ? $info['type_money'] : $data['amount'];
                $orderDesc    = $post['orderDesc'];  //订单描述
                // 添加充值记录
                $ratio = $config->value(['cname'=>'mili'],'option');
                $mili  = $amount * $ratio;
                $rechargeAdd = [
                    'uid'            => $uid,
                    'recharge_money' => $amount,
                    'plat_money'     => $amount,
                    'mili'           => $mili,
                    'payment'        => Bases::PAY_METHOD_UNIONPAY,
                    'order_num'      => $merchOrderId,
                    'platform'       => Bases::ANDROID,
                    'create_time'    => time()
                ];
                $rid = $recharge->insertData($rechargeAdd);
                if(!$rid){
                    throw new \LogicException('操作失败',1010);
                }
                //支付
               $res = $this->Recharge($amount,$orderDesc,$merchOrderId);
               return $res;
            } catch (\LogicException $e) {
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
        }
    }

        public function  Recharge($amount,$orderDesc,$merchOrderId){
            $extData       = "充值"; 
            $miscData      = "|||||||||";  //互联网金融
            $merchantId    = Constants::getMerchantId();
            $notifyUrl     = Constants::getMerchantNotifyUrl(); // 需要做URLEncode???
            $tradeTime     = date("YmdHis");
            $expTime       = ""; // 采用系统默认的订单有效时间
            $notifyFlag    = "0";
            // 调用下单接口
            try {
                $retXml = new Xml();
                //接口参数请参考TransactionClient的参数说明
                $ret = TransactionClientSdk::MerchantOrder($merchantId,
                        $merchOrderId, $amount, $orderDesc, $tradeTime, $expTime,
                        $notifyUrl, $extData, $miscData, $notifyFlag,
                        Constants::getMerchantRsaPrivateKey(), Constants::getPayecoRsaPublicKey(), 
                        Constants::getPayecoUrl(), $retXml);
                if(strcmp("0000", $ret)){
                    return json(self::formatResult($ret,'商户下单接口测试失败'));
                }
            } catch (\Exception $e) {
                        return json(self::formatResult('签名验证失败',$e->getMessage()));
            }  
            
             $retMsgJson = array(
                        'RetCode'       => '0000' , 
                        'RetMsg'        => '下单成功', 
                        'Version'       => $retXml->getVersion(), 
                        'MerchOrderId'  => $retXml->getMerchOrderId(), 
                        'MerchantId'    => $retXml->getMerchantId(), 
                        'Amount'        => $retXml->getAmount(), 
                        'TradeTime'     => $retXml->getTradeTime(), 
                        'OrderId'       => $retXml->getOrderId(), 
                        'Sign'          => $retXml->getSign(), 
                        );
                      return json(self::formatSuccessResult($retMsgJson));
            }
            
            public function selectorder($orderId){
                if(request()->isPost()){
                    $recharge   = new Bases('recharge');
                    $selectorder=$recharge->selectData('trade_num = '.$orderId,'status');
                    $selectorder = $selectorder[0];
                    return json(self::formatSuccessResult($selectorder));
                }
            }
}