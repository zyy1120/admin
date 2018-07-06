<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 2017/3/28 0028
 * Time: 12:21
 */
namespace app\api\controller;
use app\models\Bases;
use app\service\Redis;
use app\service\Rsa;

class Wechat extends Base{
    /**
     * 微信出售
     * @return \think\response\Json
     */
    public function wechatSell(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $wechat = new Bases('wechat');
            /*// -----------------------
            $arr['token'] = $post['token'];
            $arr['wechat'] = $post['wechat'];
            $arr['money'] = $post['money'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);

                $info = $wechat->find(['uid'=>$uid]);
                if($info){
                    $bool = $wechat->updateData(['wechat'=>$data['wechat'],'money'=>$data['money'],'status'=>$wechat::SELL_YES],['uid'=>$uid]);
                } else {
                    $data['uid'] = $uid;
                    unset($data['token']);
                    $bool = $wechat->insertData($data);
                }
                if(!$bool){
                    throw new \LogicException('操作失败',1010);
                }

            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult());
        }
    }

    /**
     * 微信下架
     * @return \think\response\Json
     */
    public function wechatOffShel(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $wechat = new Bases('wechat');
            /*// -----------------------
            $arr['token'] = $post['token'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);

                $bool = $wechat->setField(['uid'=>$uid],['status'=>$wechat::SELL_NOT]);
                if(!$bool){
                    throw new \LogicException('操作失败',1010);
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult());
        }
    }

    /**
     * 微信信息
     * @return \think\response\Json
     */
    public function wechatInfo(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $wechat = new Bases('wechat');
            /*// -----------------------
            $arr['token'] = $post['token'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);

                $info = $wechat->find(['uid'=>$uid]);
                if(!$info){
                    $info = '';
                }

            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($info));
        }
    }

    /**
     * 微信修改
     * @return \think\response\Json
     */
    public function wechatSave(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $wechat = new Bases('wechat');
            /*// -----------------------
            $arr['token'] = $post['token'];
            $arr['wechat'] = $post['wechat'];
            $arr['money'] = $post['money'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);

                $data['id'] = $uid;
                unset($data['token']);
                $bool = $wechat->updateData($data);
                if(false === $bool){
                    throw new \LogicException('操作失败',1010);
                }

            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult());
        }
    }

    /**
     * 微信查看
     * @return \think\response\Json
     */
    public function wechatShow(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $order = new Bases('order');
            /*// -----------------------
            $arr['token'] = $post['token'];
            $arr['uid'] = $post['uid'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);

                $info = $order->joinOne([['tq_wechat b','a.beneficiary_id = b.uid']],['a.uid'=>$uid,'a.beneficiary_id'=>$data['uid'],'a.type_id'=>$order::ORDER_TYPE_WECHAT,'a.status'=>$order::PAY_YES],'b.wechat');
                if(!$info){
                    throw new \LogicException('该微信不存在',1028);
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($info));
        }
    }

    /**
     * 微信价格列表
     * @return \think\response\Json
     */
    public function wechatPrice(){
        $config = new Bases('config');
        $price = $config->value(['cname'=>'hongbao'],'option');
        return json(self::formatSuccessResult(['price'=>explode(',',$price)]));
    }

    /**
     * 微信出售记录
     * @return \think\response\Json
     */
    public function wechatRecord(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $order = new Bases('order');
            $wechat_buy = new Bases('wechatBuy');
            // -----------------------
            /*$arr['token'] = $post['token'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);

                $orderList = $wechat_buy->page(['a.beneficiary_id'=>$uid,'a.is_pay'=>$order::PAY_YES],'a.id desc',20,'',[['tq_user b','a.uid = b.id','LEFT'],['tq_order c','a.oid = c.id','LEFT']],'b.nickname,b.portrait,c.mili as order_amount,c.create_time,a.wechat,a.status as is_friend',$post['page']);
                $list = $orderList->items();
                foreach($list as $k => $v){
                    $list[$k]['create_time'] = format_date($v['create_time']);
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($list));
        }
    }
}