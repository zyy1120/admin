<?php
namespace app\api\controller;
use app\models\Bases;
use think\config;
use app\service\ClientResponseHandler;
use app\service\PayHttpClient;
use app\service\RequestHandler;
use app\service\Utils;

Class Request extends Base {

    /**
     * 提交订单信息
     */
    public function submitOrderInfo(){
        $post          = input('param.');
          $resHandler    = new  ClientResponseHandler();
          $reqHandler    = new  RequestHandler();
          $Utils         = new  Utils();
          $config        = new  config();
          $pay           = new  PayHttpClient();
          $mchId         = Config('wx.mchId');
          $version       = Config('wx.version');
          $url           = Config('wx.url');
          $key           = Config('wx.key');
           $reqHandler->setGateUrl($url);
           $reqHandler->setKey($key);
           $reqHandler->setReqParams($post,array('method'));
           $reqHandler->setParameter('service','unified.trade.pay');//接口类型：pay.weixin.native  表示微信扫码
           $reqHandler->setParameter('mch_id',$mchId);//必填项，商户号，由威富通分配
           $reqHandler->setParameter('version',$version);
        
        //通知地址，必填项，接收威富通通知的URL，需给绝对路径，255字符内格式如:http://wap.tenpay.com/tenpay.asp
           $reqHandler->setParameter('notify_url','https://ulteriortest.xkmz.tv/api/Wxnotify/callback?method=callback');
           $reqHandler->setParameter('nonce_str',mt_rand(time(),time()+rand()));//随机字符串，必填项，不长于 32 位
           $reqHandler->createSign();//创建签名
           $data = Utils::toXml($reqHandler->getAllParameters());
           if($pay->call($url,$data)){
            $resHandler->setContent($pay->getResContent());
            $resHandler->setKey($key);
            if($resHandler->isTenpaySign()){
                //当返回状态与业务结果都为0时才返回支付二维码，其它结果请查看接口文档
                if($resHandler->getParameter('status') == 0 && $resHandler->getParameter('result_code') == 0){
                    echo json_encode(array('token_id'=>$resHandler->getParameter('token_id'),
                               'services'=>$resHandler->getParameter('services')));                
                    exit();
                }else{
                    echo json_encode(array('status'=>500,'msg'=>'Error Code:'.$resHandler->getParameter('err_code').' Error Message:'.$resHandler->getParameter('err_msg')));
                    exit();
                }
            }
            echo json_encode(array('status'=>500,'msg'=>'Error Code:'.$resHandler->getParameter('status').' Error Message:'.$resHandler->getParameter('message')));
        }else{
            echo json_encode(array('status'=>500,'msg'=>'Response Code:'.$pay->getResponseCode().' Error Info:'.$pay->getErrInfo()));
        }   
}
}

?>