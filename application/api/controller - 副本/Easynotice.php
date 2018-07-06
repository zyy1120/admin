<?php
namespace app\api\controller;
use app\models\Bases;
use app\service\Constants;
use app\api\Easyorder;
use app\service\TransactionClient;
    header("Content-Type=>text/html; charset=utf-8");
    class Easynotice extends Base  {
        public function notify(){
        // 结果通知参数，易联异步通知采用GET提交
        $version      = $_POST["Version"];
        $merchantId   = $_POST["MerchantId"];
        $merchOrderId = $_POST["MerchOrderId"];
        $amount       = $_POST["Amount"];
        $extData      = $_POST["ExtData"];
        $orderId      = $_POST["OrderId"];
        $status       = $_POST["Status"];
        $payTime      = $_POST["PayTime"];
        $settleDate   = $_POST["SettleDate"];
        $sign         = $_POST["Sign"];
        $retMsgJson   = "";
        if(empty($_POST)){
            $this->error('缺少请求参数');
        }
        try {
            //验证订单结果通知的签名
            $b = TransactionClient::bCheckNotifySign($version, $merchantId, $merchOrderId, 
                    $amount, $extData, $orderId, $status, $payTime, $settleDate, $sign, 
                    Constants::getPayecoRsaPublicKey());
            if (!$b) {
                $retMsgJson = "{\"RetCode\":\"E101\",\"RetMsg\":\"验证签名失败!\"}";
                 echo $retMsgJson;
                 exit;
            }else{
                // 签名验证成功后，需要对订单进行后续处理
                if (strcmp("02", $status) == 0) { // 订单已支付
                    // 1、检查Amount和商户系统的订单金额是否一致
                    // 2、订单支付成功的业务逻辑处理请在本处增加（订单通知可能存在多次通知的情况，需要做多次通知的兼容处理）；
                    // 3、返回响应内容
                    // 修改充值状态
                    $order = new Bases('order');
                    $recharge = new Bases('recharge');
                    $info = $recharge->find(['order_num' => $merchOrderId]);
                    $rechargeSave = [
                        'trade_num' => $orderId,
                        'pay_time'  => date('Y-m-d H:i:s',time()),
                        'status'    => Bases::PAY_YES,
                    ];
                    $bool1 = $recharge->updateData($rechargeSave, ['order_num' => $merchOrderId]);
                    // 账户添加米粒
                    $account = new Bases('account');
                    $bool2 = $account->_setInc(['uid' => $info['uid']], 'mili', $info['mili']);
                   
                    //安卓直接充值不执行
                    $order->startTrans();
                    $is_set = $order->find(['order_num' => $merchOrderId]);
                    if($is_set){
                        $order_class            = new Order();
                        $data['uid']            = $is_set['uid'];
                        $data['mili']           = $is_set['mili'];
                        $data['price']          = $amount;
                        $data['order_num']      = $merchOrderId;
                        $data['trade_no']       = $orderId;
                        $data['pay_time']       = date('Y-m-d H:i:s',time());
                        $data['beneficiary_id'] = $is_set['beneficiary_id'];
                        if ($is_set['type_id'] == Bases::ORDER_TYPE_HONGBAO) {
                            $order_class->lookDynamic($data);
                        }
                        if ($is_set['type_id'] == Bases::ORDER_TYPE_REWARD) {
                            $order_class->reward($data);
                        }
                        if ($is_set['type_id'] == Bases::ORDER_TYPE_WECHAT) {
                            $order_class->buyWechat($data);
                        }
                        if (!$bool1 || !$bool2) {
                            $order->rollback();
                        }
                    }
                    $order->commit();
                    $retMsgJson = "{\"RetCode\":\"0000\",\"RetMsg\":\"订单已支付\"}";
                     echo $retMsgJson;
                     exit;
                } else {
                    // 1、订单支付失败的业务逻辑处理请在本处增加（订单通知可能存在多次通知的情况，需要做多次通知的兼容处理，避免成功后又修改为失败）；
                    // 2、返回响应内容
                   $retMsgJson = "{\"RetCode\":\"E102\",\"RetMsg\":\"订单支付失败".status."\"}";
                    echo $retMsgJson;
                }
            }
        } catch (\Exception $e) {
            $retMsgJson = "{\"RetCode\":\"E103\",\"RetMsg\":\"处理通知结果异常\"}";
             echo $retMsgJson;
             exit;
        }
        //返回数据
       
            }
        }

?>