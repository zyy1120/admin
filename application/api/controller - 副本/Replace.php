<?php
namespace app\api\controller;
use app\service\YiLian;

class Replace extends Base {

    public function pay(){
        $yilian = new YiLian;
        $data = array(
            'ACC_NO'=>'6227003811930123771',
            'ACC_NAME'=>'笪飞亚',
            'ID_NO'=>'',
            'MOBILE_NO'=>'',
        //    'ACC_PROVINCE'=>'',
        //    'ACC_CITY'=>'',
            'AMOUNT'=>'1000.00',
            'CNY'=>'CNY',
            'PAY_STATE'=>'',
            'MER_ORDER_NO'=>'123456'
        );
        $res = $yilian->pay($data);
        var_dump($res);
    }
}