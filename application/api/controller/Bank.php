<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/3/29 0029
 * Time: 11:56
 */
namespace app\api\controller;
use app\models\Bases;
use app\service\Redis;
use app\service\Rsa;

class Bank extends Base{
    /**
     * 添加银行卡
     * @return \think\response\Json
     */
    public function bankAdd(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $bank  = new Bases('bank');
            /*// -----------------------
            $arr['token']       = $post['token'];
            $arr['id_card']      = $post['id_card'];
            $arr['bank_account'] = $post['bank_account'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);
                unset($post['equipment']);
                unset($post['encrypt']);
                $post['id_card'] = strtolower($data['id_card']);
                $post['bank_account'] = $data['bank_account'];

                // 场景验证
                $validate = $this->validate($post,'Bank.bankAdd');
                if(true !== $validate){
                    throw new \LogicException($validate,1011);
                }

                $post['uid'] = $uid;
                // 添加
                $result = $bank->insertData($post);
                if(!$result){
                    throw new \LogicException('操作失败',1010);
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult());
        }
    }

    /**
     * 银行卡信息
     * @return \think\response\Json
     */
    public function bankInfo(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $bank  = new Bases('bank');
            /*// -----------------------
            $arr['token']       = $post['token'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid  = $redis->get($data['token']);
                $info = $bank->find(['uid'=>$uid]);
                if(false === $info){
                    throw new \LogicException('操作失败',1010);
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($info));
        }
    }

    /**
     * 修改银行卡信息
     * @return \think\response\Json
     */
    public function bankSave(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $bank  = new Bases('bank');
            /*// -----------------------
            $arr['token']       = $post['token'];
            $arr['id_card']      = $post['id_card'];
            $arr['bank_account'] = $post['bank_account'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);
                unset($post['token']);
                unset($post['equipment']);
                unset($post['encrypt']);

                // 场景验证
                $validate = $this->validate($post,'Bank.bankAdd');
                if(true !== $validate){
                    throw new \LogicException($validate,1011);
                }

                $post['id_card'] = strtolower($data['id_card']);

                // 添加
                $result = $bank->updateData($post,['uid'=>$uid]);
                if(!$result){
                    throw new \LogicException('操作失败',1010);
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult());
        }
    }
}