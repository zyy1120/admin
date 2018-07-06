<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/3/23 0023
 * Time: 16:13
 */
namespace app\api\controller;
use app\models\Bases;
use app\models\Models;
use app\service\Redis;
use app\service\Rsa;

class Register extends Base{
    /**
     * App注册第一步
     * @return \think\response\Json
     */
    public function registerStepOne(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $user  = new Bases('user');
            try{
                // 验证参数
                if(!isset($post['encrypt']) || !isset($post['equipment'])){
                    throw new \LogicException('参数错误',1020);
                }
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);

                // 场景验证
                $validate = $this->validate($data,'User.registerStepOne');
                if(true !== $validate){
                    throw new \LogicException($validate,1011);
                }

                // 验证码不能为空
                if(!$post['code']){
                    throw new \LogicException('验证码不能为空',1015);
                }

                // 获取用户信息
                $info = $user->find(['username' => $data['username']]);
                if($info){
                    throw new \LogicException('您的帐号已存在，请直接登录',1017);
                }

                // 比对验证码
                $code = $redis->get($data['username'].'register');
                if($post['code'] != $code || empty($code)){
                    throw new \LogicException('验证码有误',1016);
                }

                $add = [
                    'username'    => $data['username'],
                    'password'    => password_hash($data['password'],true),
                    'status'      => $user::STATUS_NOT_ACTIVE,
                    'create_time' => time(),
                ];

                // 注册成功
                $uid = $user->insertData($add);
                if(!$uid){
                    throw new \LogicException('操作失败',1010);
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult(['uid'=>$uid]));
        }
    }

    /**
     * App注册第二步
     * @return \think\response\Json
     */
    public function registerStepTwo(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $user  = new Bases('user');
            $account = new Bases('account');
            try{
                // 验证参数
                if(!isset($post['encrypt']) || !isset($post['equipment'])){
                    throw new \LogicException('参数错误',1020);
                }
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);

                $uid = $data['uid'];
                unset($data['uid']);

                // 场景验证
                $validate = $this->validate($data,'User.registerStepTwo');
                if(true !== $validate){
                    throw new \LogicException($validate,1011);
                }

                // 获取用户信息
                $info = $user->find(['id' => $uid]);
                if(!$info){
                    throw new \LogicException('该用户尚未注册',1012);
                }

                // 图片上传
                $files = request()->file('image');
                if(empty($files)){
                    throw new \LogicException('上传文件不能为空',1024);
                }
                $portrait = upload('portrait');

                $save = [
                    'id'       => $uid,
                    'nickname' => $data['nickname'],
                    'sex'      => $data['sex'],
                    'portrait' => $portrait['ori'][0],
                    'thumb'    => $portrait['thumb'][0],
                    'status'   => $user::STATUS_ON,
                    'version'  => $post['version'],
                    'platform' => strtolower($post['platform']) == 'ios' ? Bases::IOS : Bases::ANDROID,
                    'last_login_time' =>time()
                ];

                $user->startTrans();
                // 激活用户完善信息
                $bool  = true;
                $bool1 = true;
                $bool2 = $account->find(['uid'=>$uid]);
                if(!$bool2){
                    $bool = $user->updateData($save);
                    // 开户
                    $bool1 = $account->insertData(['uid'=>$uid]);
                }

                if(!$bool || !$bool1){
                    $user->rollback();
                    throw new \LogicException('操作失败',1010);
                }

                // 生成token
                $token = self::setToken($post['equipment'],$info['username'],$info['password']);

                // 将token存入redis
                $redis->set($token,30*24*3600,$uid);

                // 将设备号存入redis
                $redis->set($uid,30*24*3600,$post['equipment']);

                // 返回数据
                $result = ['token' => $token,'uid' => $uid,'type' =>$info['type_id']];

            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            $user->commit();
            return json(self::formatSuccessResult($result));
        }
    }

    public function registerStepTwoV1_2(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $user  = new Bases('user');
            $account = new Bases('account');
            try{
                // 验证参数
                if(!isset($post['encrypt']) || !isset($post['equipment']) || !isset($post['from']) || !isset($post['version']) || !isset($post['platform'])){
                    throw new \LogicException('参数错误',1020);
                }
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);

                $uid = $data['uid'];
                unset($data['uid']);

                // 场景验证
                $validate = $this->validate($data,'User.registerStepTwo');
                if(true !== $validate){
                    throw new \LogicException($validate,1011);
                }

                // 获取用户信息
                $info = $user->find(['id' => $uid]);
                if(!$info){
                    throw new \LogicException('该用户尚未注册',1012);
                }

                $image = json_decode(htmlspecialchars_decode($post['image']),true);

                $save = [
                    'id'       => $uid,
                    'nickname' => $data['nickname'],
                    'sex'      => $data['sex'],
                    'portrait' => $image['ori'][0],
                    'thumb'    => $image['thumb'][0],
                    'sign'     => $post['sign'],
                    'status'   => $user::STATUS_ON,
                    'version'  => $post['version'],
                    'from'     => $post['from'],
                    'platform' => strtolower($post['platform']) == 'ios' ? Bases::IOS : Bases::ANDROID,
                    'last_login_time' =>time()
                ];

                $user->startTrans();
                // 激活用户完善信息
                $bool  = true;
                $bool1 = true;
                $bool2 = $account->find(['uid'=>$uid]);
                if(!$bool2){
                    $bool = $user->updateData($save);
                    // 开户
                    $bool1 = $account->insertData(['uid'=>$uid]);
                }
                $bool2 = true;
                if($info['type_id'] == Bases::USER_TYPE_MODEL){
                    // 统计表
                    $statistics = new Bases('statistics');
                    $bool2 = $statistics->insertData(['agent_id'=>$info['agent_id'],'uid'=>$uid,'create_time'=>time()]);
                }


                if(!$bool || !$bool1 || !$bool2){
                    $user->rollback();
                    throw new \LogicException('操作失败',1010);
                }

                // 生成token
                $token = self::setToken($post['equipment'],$info['username'],$info['password']);

                // 将token存入redis
                $redis->set($token,30*24*3600,$uid);

                // 将设备号存入redis
                $redis->set($uid,30*24*3600,$post['equipment']);

                // 返回数据
                $result = ['token' => $token,'uid' => $uid,'type' =>$info['type_id']];

            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            $user->commit();
            return json(self::formatSuccessResult($result));
        }
    }
}