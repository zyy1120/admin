<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/5/11 0011
 * Time: 11:03
 */
namespace app\api\controller;
use app\models\Bases;
use app\service\Rsa;
use app\service\Redis;

class Apple extends Base {
    /**
     * 21000 App Store不能读取你提供的JSON对象
     * 21002 receipt-data域的数据有问题
     * 21003 receipt无法通过验证
     * 21004 提供的shared secret不匹配你账号中的shared secret
     * 21005 receipt服务器当前不可用
     * 21006 receipt合法，但是订阅已过期。服务器接收到这个状态码时，receipt数据仍然会解码并一起发送
     * 21007 receipt是Sandbox receipt，但却发送至生产系统的验证服务
     * 21008 receipt是生产receipt，但却发送至Sandbox环境的验证服务
     *
     * $receipt  苹果返回的支付凭证，ios客户端base64加密后的长字符串
     * $sandbox  为1时$url为测试地址，为0时为正试地址
     * $orderid  苹果订单号
     */
    public function appleVerify(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $recharge = new Bases('recharge');
            $product = new Bases('recharge_type');
            $receipt = $post['receipt'];
            $sandbox = $post['sandbox'];
            $orderid = $post['transaction_id'];
            /*// -----------------------
            $arr['token'] = $post['token'];
            $arr['product_id'] = $post['product_id'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                //self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);

                // 创建订单
                $info = $recharge->find(['trade_num'=>$orderid]);
                if($info){
                    throw new \LogicException('订单已存在',1032);
                }

                /**
                 * 获取产品信息
                 * 苹果支付不直接传金额，以产品形式
                 */
                $recharge_info = $product->find(['product_id'=>$data['product_id']]);

                $recharge->startTrans();

                // 添加充值记录
                $order_num = date('YmdHis').rand(00001,99999);   // 生成订单号
                $rechargeAdd = [
                    'uid'            => $uid,
                    'recharge_money' => $recharge_info['type_money'],
                    'plat_money'     => $recharge_info['type_money'] * 0.7,
                    'mili'           => $recharge_info['mili'],
                    'payment'        => Bases::PAY_METHOD_APPLE,
                    'order_num'      => $order_num,
                    'trade_num'      => $orderid,
                    'platform'       => Bases::IOS,
                    'create_time'    => time()
                ];
                $bool = $recharge->insertData($rechargeAdd);

                //去苹果进行二次验证，防止收到的是伪造的数据
                $result = $this->appleReceipt($receipt,$sandbox);
                if(!is_array($result)){
                    throw new \LogicException('操作失败',1010);
                }

                //没有错误就进行业务逻辑的处理，订单设置成已支付，给用户加钱
                // 修改充值状态
                $rechargeSave = [
                    'pay_time'  => date('Y-m-d H:i:s',time()),
                    'status'    => Bases::PAY_YES
                ];
                $bool1 = $recharge->updateData($rechargeSave,['trade_num' => $result['transaction_id']]);

                // 账户添加米粒
                $account = new Bases('account');
                $bool2 = $account->_setInc(['uid' => $uid],'mili',$recharge_info['mili']);

                if (!$bool || !$bool1 || !$bool2) {
                    $recharge->rollback();
                    throw new \LogicException('操作失败',1010);
                }
            } catch (\Exception $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            $recharge->commit();
            return json(self::formatSuccessResult());
        }
    }

    /**
     * 去苹果服务器二次验证
     * @param $receipt
     * @param $sandbox
     * @return array
     */
    protected function appleReceipt($receipt,$sandbox){
        $postData = json_encode(
            array('receipt-data' => $receipt)
        );
        //正式购买地址 沙盒购买地址
        $url_buy     = "https://buy.itunes.apple.com/verifyReceipt";
        $url_sandbox = "https://sandbox.itunes.apple.com/verifyReceipt";
        $url = $sandbox ? $url_sandbox : $url_buy;

        //简单的curl
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        curl_close($ch);
        if ($errno != 0) {
            throw new \LogicException('请求超时，请稍后重试',1033);
        }
        /**
         * $data['status']==0  成功
         * $data['receipt']['in_app'][0]['transaction_id']  苹果订单号
         * $data['receipt']['in_app'][0]['product_id'];  商品价格
         */
        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \LogicException('苹果返回数据有误，请稍后重试',1034);
        }
        if (!isset($data['status']) || $data['status'] != 0) {
            throw new \LogicException('购买失败',1035);
        }
        //返回产品的信息
        return $data['receipt'];
    }
}