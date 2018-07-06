<?php
namespace app\api\controller;
use app\models\Bases;
use think\config;
use app\service\WechatAppPay;
use app\service\Redis;
use app\service\Rsa;

class Apppay extends Base{

    public function payApply(){
        /**
        * 添加判断，ios or Android
        * body          商品描述
        * total_fee     订单金额 只能为整数 单位为分
        * trade_type    交易类型 JSAPI | NATIVE | APP | WAP
        * version       区分安卓传(Android)  ios传(ios) 
        * token         
        * equipment 
        */
        $post = input('param.');
        $rsa   = new Rsa();
        $redis   = new Redis();
        $payorder  = new Bases('payorder');
        $user      = new Bases('user');
        // $arr['token']       = $post['token'];
        // $arr['body']      = $post['body'];
        // $arr['total_fee']      = $post['total_fee'];
        // $arr['platform'] = $post['platform'];
        // $key = $rsa->generateKey($post['equipment']);
        // $post['encrypt'] = $rsa->encrypt($key,$arr);
        $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
        // 根据token获取用户id
        $uid = $redis->get($data['token']);
        $user = $user->find(['id'=>$uid],'nickname');
        unset($post['equipment']);
        unset($post['encrypt']);
        if ($data['platform'] == 'Android') {
            $appid  = Config('Android.appid');
            $mch_id = Config('Android.mchid');
            $key    = Config('Android.key');
        } else if ($data['platform'] == 'ios') {
            $appid  = Config('ios.appid');
            $mch_id = Config('ios.mchid');
            $key    = Config('ios.key');
        }
        $notify_url = 'https://'.$_SERVER['HTTP_HOST'].'/api/Wxpaynotify/notify';
        $wechatAppPay = new WechatAppPay($appid, $mch_id, $notify_url, $key);
        // 获取传过来的商品基本信息                         
        $params['body'] = $data['body'];                                  //商品描述
        $params['total_fee'] = $data['total_fee'];                        //订单金额 只能为整数 单位为分
        $params['trade_type'] ='APP';                                     //交易类型 JSAPI | NATIVE | APP | WAP 
        $params['out_trade_no'] = date('Ymd').str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);  //订单号
        $data =array(
        'username' => $user['nickname'],
        'body' => $params['body'],
        'totalfee' => $params['total_fee'] ,
        'time' => time(),
        'outtradeno' => $params['out_trade_no'],
        );
        $list =  $payorder->insertData($data);  
        $result = $wechatAppPay->unifiedOrder( $params );                 //result中就是返回的各种信息信息，成功的情况下也包含很重要的prepay_id 92052
        //预支付参数
        /**
        *@var TYPE_NAME $result 
        *
        */
        $data = @$wechatAppPay->getAppPayParams( $result['prepay_id'] );
        // 根据上行取得的支付参数请求支付即可
        print_r(json_encode($data));
    }

}