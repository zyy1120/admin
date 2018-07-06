<?php
namespace app\api\controller;
use app\models\Bases;

class Wxpaynotify {

	public function notify(){
        //接收异步回调的返回值
        $xml =  file_get_contents('php://input');
        //xml转array
        $data = json_decode(json_encode(simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NOCDATA)),TRUE);
        //回调数据中获得的订单id（唯一)
        $out_trade_no = isset($data['out_trade_no'])?$data['out_trade_no']:"";
        $transaction_id = isset($data['transaction_id'])?$data['transaction_id']:"";
        $payorder  = new Bases('payorder');
        //查询有无订单记录
        $order = $payorder->find('outtradeno ='.$out_trade_no);
        if ($order) {
          //修改订单支付状态
           $res = $payorder->setField(['outtradeno'=>$out_trade_no],['status'=>1]);
        }  
        //返回码1是成功
        if($res==1){
            //更新字段的值
            $order=$payorder->setField(['outtradeno'=>$out_trade_no],['transactionid'=>$transaction_id,'time'=>time()]);  
        }
    }
}