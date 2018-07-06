<?php
namespace app\api\controller;
use app\service\AlipayTradeQueryRequest;
use app\service\AopClient;
use think\config;


class Alipayorder extends Base {
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
       public function selectorder($out_trade_no,$trade_no){
        $aop = new AopClient ();
        $aop->gatewayUrl          = 'https://openapi.alipay.com/gateway.do';
        $aop->appId               = Config('Alipay.appid');
        $aop->rsaPrivateKey       = Config('Alipay.rsaPrivateKey');
        $aop->alipayrsaPublicKey =  Config('Alipay.alipayrsaPublicKey');
        $aop->apiVersion          = '1.0';
        $aop->signType            = 'RSA';
        $aop->postCharset         = 'UTF-8';
        $aop->format              = 'json';
        $request                  = new AlipayTradeQueryRequest ();

        $request->setBizContent("{" .
        "    \"out_trade_no\":\"$out_trade_no\"," .
        "    \"trade_no\":\"$trade_no\"" .
        "  }");
        $result = $aop->execute ($request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        if(!empty($resultCode) && $resultCode == 10000){
        echo "成功";
        } else {
        echo "失败";
        }
    }

}