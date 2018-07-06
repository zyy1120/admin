<?php
namespace app\api\controller;
use app\models\Bases;
use app\service\ClientResponseHandler;
use app\service\PayHttpClient;
use app\service\RequestHandler;
use app\service\Utils;
use think\config;
// use app\service\Redis;
// use app\service\Rsa;

class Weifu extends Base {
    /**
     * 订单快捷支付接口(纯API接口版本)
     * @param method            类型
     * @param out_trade_no      商户订单号
     * @param body              商品名
     * @param attach            附加信息
     * @param total_fee         价格 单位分
     * @param mch_create_ip     ip
     * @param time_start        订单生成时间
     * @param time_expire       订单超时时间
     * @param notify_url        回调
     * @param mch_id            
     * @param version           商户号 
     * @param key
     * @param url               请求地址
     * @param version           版本
     */
    static function submitOrderInfo() {
      if(request()->isPost()){
          $post          = input('param.');
          $resHandler    = new  ClientResponseHandler();
          $reqHandler    = new  RequestHandler();
          $Utils         = new  Utils();
          $config        = new  config();
          $pay           = new  PayHttpClient();
          $mchId         = Config('scancode.mchId');
          $version       = Config('scancode.version');
          $url           = Config('scancode.url');
          $key           = Config('scancode.key');
          try{
           $reqHandler->setGateUrl($url);
           $reqHandler->setKey($key);
           $reqHandler->setReqParams($post,array('method'));
           $reqHandler->setParameter('service','pay.weixin.native');//接口类型：pay.weixin.native  表示微信扫码
           $reqHandler->setParameter('mch_id',$mchId);//必填项，商户号，由威富通分配
           $reqHandler->setParameter('version',$version);
        
        //通知地址，必填项，接收威富通通知的URL，需给绝对路径，255字符内格式如:http://wap.tenpay.com/tenpay.asp
           $reqHandler->setParameter('notify_url','https://ulteriortest.xkmz.tv/api/Weifunotify/callback');
           $reqHandler->setParameter('nonce_str',mt_rand(time(),time()+rand()));//随机字符串，必填项，不长于 32 位
           $reqHandler->createSign();//创建签名
           $data = Utils::toXml($reqHandler->getAllParameters());
           if($pay->call($url,$data)){
            $resHandler->setContent($pay->getResContent());
            $resHandler->setKey($key);
            var_dump($resHandler->isTenpaySign());
            if($resHandler->isTenpaySign()){
                //当返回状态与业务结果都为0时才返回支付二维码，其它结果请查看接口文档
                if($resHandler->getParameter('status') == 0 && $resHandler->getParameter('result_code') == 0){
                    echo json_encode(array('code_img_url'=>$resHandler->getParameter('code_img_url'),
                                           'code_url'=>$resHandler->getParameter('code_url'),
                                           'code_status'=>$resHandler->getParameter('code_status'),
                                           'type'=>$reqHandler->getParameter('service')));
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
            } catch (\LogicException $e) {
                    return json(self::formatResult($e->getCode(),$e->getMessage()));
                }
            }
        } 
}