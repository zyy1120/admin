<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/3/23 0023
 * Time: 9:49
 */
namespace app\api\controller;
use app\models\Bases;
use app\service\Redis;
use app\service\Rsa;

class Login extends Base{
    /**
     * APP登录
     * */
    public function login(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $user  = new Bases('user');
            /*// -----------------------
            $arr['username'] = $post['username'];
            $arr['password'] = $post['password'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 验证参数
                if(!isset($post['encrypt']) || !isset($post['equipment'])){
                    throw new \LogicException('参数错误',1020);
                }

                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);

                // 登录场景验证
                $validate = $this->validate($data,'User.login');
                if(true !== $validate){
                    throw new \LogicException($validate,1011);
                }

                // 获取用户信息
                $data['user'] = $user->find(['username' => $data['username']]);

                if(empty($data['user']['platform'])){
                    $save = [
                        'version'     => $post['version'],
                        'platform'    => strtolower($post['platform']) == 'ios' ? Bases::IOS : Bases::ANDROID,
                        'last_login_time' =>time()
                    ];
                    $data['bool'] = $user->updateData($save,['id'=>$data['user']['id']]);
                } else {
                    // 保存用户登录时间
                    $data['bool'] = $user->setField(['id'=>$data['user']['id']],['last_login_time'=>time()]);
                }

                // 抛出异常信息
                $this->errorMsg($data,$user);

                // 生成token
                $token = self::setToken($post['equipment'],$data['username'],$data['user']['password']);

                // 将token存入redis
                $redis->set($token,30*24*3600,$data['user']['id']);

                // 将设备号存入redis
                $redis->set($data['user']['id'],30*24*3600,$post['equipment']);

                // 返回数据
                $result = ['token' => $token,'uid' => $data['user']['id'],'type'=> $data['user']['type_id'],'status'=>$data['user']['status']];

            }catch(\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($result));
        }
    }

    public function loginV1_2(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $user  = new Bases('user');
            try{
                // 验证参数
                if(!isset($post['encrypt']) || !isset($post['equipment']) || !isset($post['from']) || !isset($post['version']) || !isset($post['platform'])){
                    throw new \LogicException('参数错误',1020);
                }

                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);

                // 登录场景验证
                $validate = $this->validate($data,'User.login');
                if(true !== $validate){
                    throw new \LogicException($validate,1011);
                }

                // 获取用户信息
                $data['user'] = $user->find(['username' => $data['username']]);

                if(empty($data['user']['platform'])){
                    $save = [
                        'version'     => $post['version'],
                        'platform'    => strtolower($post['platform']) == 'ios' ? Bases::IOS : Bases::ANDROID,
                        'last_login_time' =>time()
                    ];
                    $data['bool'] = $user->updateData($save,['id'=>$data['user']['id']]);
                } else {
                    // 保存用户登录时间
                    $data['bool'] = $user->setField(['id'=>$data['user']['id']],['last_login_time'=>time()]);
                }

                // 抛出异常信息
                $this->errorMsg($data,$user);

                // 添加来源
                if(empty($data['user']['from'])){
                    $bool1 = $user->setField(['id'=>$data['user']['id']],['from'=>$post['from']]);
                    if(!$bool1){
                        throw new \LogicException('操作失败',1010);
                    }
                }

                // 生成token
                $token = self::setToken($post['equipment'],$data['username'],$data['user']['password']);

                // 将token存入redis
                $redis->set($token,30*24*3600,$data['user']['id']);

                // 将设备号存入redis
                $redis->set($data['user']['id'],30*24*3600,$post['equipment']);

                // 返回数据
                $result = ['token' => $token,'uid' => $data['user']['id'],'type'=> $data['user']['type_id'],'status'=>$data['user']['status']];

            }catch(\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($result));
        }
    }

    /**
     * APP退出
     */
    public function logout(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            try{
                // 验证参数
                if(!isset($post['encrypt']) || !isset($post['equipment'])){
                    throw new \LogicException('参数错误',1020);
                }

                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);

                // 清空token
                $redis->del($data['token']);

            } catch (\LogicException $e){
               return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult());
        }
    }

    /**
     * 忘记密码
     */
    public function forgetPassword(){
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
                $validate = $this->validate($data,'User.forgetPassword');
                if(true !== $validate){
                    throw new \LogicException($validate,1011);
                }

                // 验证码不能为空
                if(!$post['code']){
                    throw new \LogicException('验证码不能为空',1015);
                }

                // 获取用户信息
                $info = $user->find(['username' => $data['username']]);
                if(!$info){
                    throw new \LogicException('该用户不存在',1012);
                }

                // 比对验证码
                $code = $redis->get($data['username'].'forgetPassword');
                if($post['code'] != $code || empty($code)){
                    throw new \LogicException('验证码错误',1016);
                }

                // 修改密码
                $bool = $user->updateData(['id'=>$info['id'],'password'=>password_hash($data['password'],true)]);
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
     * 抛出异常信息
     * @param $data
     * @param $user
     */
    protected function errorMsg($data,$user){
        if(!$data['user']){
            throw new \LogicException('该用户尚未注册',1012);
        }
        if($data['user']['status'] == $user::STATUS_OFF){
            throw new \LogicException('该账户已锁定',1013);
        }
        if(!password_verify($data['password'],$data['user']['password'])){
            throw new \LogicException('请输入正确的密码',1014);
        }
    }
}