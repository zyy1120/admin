<?php
namespace app\api\controller;
use app\models\Bases;

class Alipaynotify {
    /**
     * android订单支付完成后回写数据库(支付宝支付回调)
     */
    public function notify(){
        $get = input('param.');
        $order = new Bases('order');
        $recharge = new Bases('recharge');

        /*$get['total_amount'] = '1';
        $get['trade_no'] = '2017051721001004960255269833';
        $get['out_trade_no'] = '2017051711540690079';
        $get['notify_time'] = '2017-04-25 09:28:46';
        $get['gmt_payment'] = '2017-04-25 09:28:46';
        $get['trade_status'] = 'TRADE_SUCCESS';*/


        $info = $recharge->find(['order_num' => $get['out_trade_no']]);
        // 订单支付完成，输出声明
        if ($info['status'] == Bases::PAY_YES) {
            echo 'success';exit;
        }

        $order->startTrans();
        // 添加回调日志
        $logAdd = [
            'total_amount' => $get['total_amount'],
            'trade_no'     => $get['trade_no'],
            'out_trade_no' => $get['out_trade_no'],
            'notify_time'  => time(),
            'gmt_payment'  => $get['gmt_payment'],
            'trade_status' => $get['trade_status'],
        ];
        $log = new Bases('notifyLog');
        $bool = $log->insertData($logAdd);
        if(!$bool){
            $order->rollback();
        }

        // 修改充值状态
        $rechargeSave = [
            'trade_num' => $get['trade_no'],
            'pay_time' => $get['gmt_payment'],
            'status' => Bases::PAY_YES,
        ];
        $recharge = new Bases('recharge');
        $bool1 = $recharge->updateData($rechargeSave, ['order_num' => $get['out_trade_no']]);

        // 账户添加米粒
        $account = new Bases('account');
        $bool2 = $account->_setInc(['uid' => $info['uid']], 'mili', $info['mili']);

        // 安卓直接充值不执行
        $is_set = $order->find(['order_num' => $get['out_trade_no']]);
        if($is_set){
            $order_class = new Order();
            $data['uid'] = $is_set['uid'];
            $data['mili'] = $is_set['mili'];
            $data['price'] = $get['total_amount'];
            $data['order_num'] = $get['out_trade_no'];
            $data['trade_no'] = $get['trade_no'];
            $data['pay_time'] = time();
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
        echo 'success';exit;
    }
}

