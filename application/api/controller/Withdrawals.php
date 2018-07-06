<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/4/10 0010
 * Time: 10:26
 */
namespace app\api\controller;
use app\models\Bases;
use app\service\Redis;
use app\service\Rsa;

class Withdrawals extends Base{
    /**
     * 提现页面
     * @return \think\response\Json
     */
    public function withdrawalsTo(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $bank  = new Bases('bank');
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
                $info = $bank->find(['uid'=>$uid],'bank_account,bank_name');
                if(false === $info){
                    throw new \LogicException('操作失败',1010);
                }
                // 安卓数据结构不同的问题（wtf..）
                if(empty($info)){
                    $info['bank_name'] = '';
                    $info['bank_account'] = '';
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($info));
        }
    }

    /**
     * 提现
     * @return \think\response\Json
     */
    public function withdrawals(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $account = new Bases('account');
            $record  = new Bases('orderRecord');
            $withdrawals  = new Bases('withdrawals');
            $time = time();
            /*// -----------------------
            $arr['token'] = $post['token'];
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

                $account->startTrans();

                $info = $account->find(['uid'=>$uid],'balance,frozen');
                if(false === $info){
                    throw new \LogicException('操作失败',1010);
                }
                if($data['money'] > $info['balance']){
                    throw new \LogicException('可提现余额不足',1029);
                }
                $balance = $info['balance'] - $data['money'];
                $frozen  = $info['frozen']  + $data['money'];
                // 发出提现申请
                $result = $withdrawals->insertData(['uid'=>$uid,'money'=>$data['money'],'balance'=>$balance,'status'=>$withdrawals::AUDIT_PLATFORM_NOT,'create_time'=>$time]);
                // 冻结提现金额
                $result1 = $account->updateData(['balance'=>$balance,'frozen'=>$frozen],['uid'=>$uid]);

                // 交易记录表添加交易记录
                $bool = $record->insertData(['uid'=>$uid,'money'=>-$data['money'],'record_type'=>'已提交提现申请','status'=>1,'create_time'=>$time]);

                if(false === $info || !$result || !$result1 || !$bool){
                    $account->rollback();
                    throw new \LogicException('操作失败',1010);
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            $account->commit();
            return json(self::formatSuccessResult());
        }
    }
}