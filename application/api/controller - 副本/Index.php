<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/3/24 0024
 * Time: 9:31
 */
namespace app\api\controller;
use app\models\Bases;
use app\service\Redis;
use app\service\Rsa;

class Index extends Base{
    /**
     * 首页
     * @return \think\response\Json
     */
    public function index(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $user  = new Bases('user');
            $follow   = new Bases('follow');
            $carousel = new Bases('carousel');
            /*$arr['token'] = $post['token'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);
                // 登录后相关处理
                $follow_ids   = [];
                if($uid){
                    // 登录验证
                    self::checkLogin();
                    // 登陆后查询判断是否关注
                    $follow_ids = $follow->selectData(['fans_id'=>$uid,'status'=>$follow::FOLLOW_YES],'follow_id');
                }

                // 轮播图
                $list['carousel'] = $carousel->selectData(['status'=>$carousel::STATUS_ON],'uid,type_id,image_path,url','orderby asc',4);

                // 用户信息
                $userList = $user->page(['status'=>$user::STATUS_ON,'type_id'=>['neq',$user::USER_TYPE_NORMAL]],'position_id asc,fans_num desc,id desc',12,[],[],'id,nickname,portrait,fans_num,position_id as recommend',$post['page']);
                $list['user'] = $userList->items();
                // 循环处理数据
                foreach($list['user'] as $k => $v){
                    $list['user'][$k]['is_follow']  = $follow::FOLLOW_NOT;
                    if($follow_ids){
                        // 该用户有关注对象，循环拿出来
                        foreach($follow_ids as $v1){
                            if($v['id'] == $v1['follow_id']){
                                $list['user'][$k]['is_follow'] = $user::FOLLOW_YES;
                            }
                        }
                    }
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($list));
        }
    }

    public function indexV1_2(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $user  = new Bases('user');
            $follow   = new Bases('follow');
            $carousel = new Bases('carousel');
            try{
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);
                // 登录后相关处理
                $follow_ids   = [];
                if($uid){
                    // 登录验证
                    self::checkLogin();
                    // 登陆后查询判断是否关注
                    $follow_ids = $follow->selectData(['fans_id'=>$uid,'status'=>$follow::FOLLOW_YES],'follow_id');
                }

                // 轮播图
                $list['carousel'] = $carousel->selectData(['status'=>$carousel::STATUS_ON],'uid,type_id,image_path,url','orderby asc');

                // 推荐模特
                $list['recommend'] = $user->selectData(['status'=>$user::STATUS_ON,'type_id'=>['neq',$user::USER_TYPE_NORMAL],'position_id'=>['<',999]],'id,nickname,portrait,click_num','position_id asc');
                // 用户信息
                $userList = $user->page(['status'=>$user::STATUS_ON,'type_id'=>['neq',$user::USER_TYPE_NORMAL],'position_id'=>999],'fans_num desc,id desc',12,[],[],'id,nickname,portrait,fans_num,click_num',$post['page']);
                $list['user'] = $userList->items();
                // 循环处理数据
                foreach($list['user'] as $k => $v){
                    $list['user'][$k]['is_follow']  = $follow::FOLLOW_NOT;
                    if($follow_ids){
                        // 该用户有关注对象，循环拿出来
                        foreach($follow_ids as $v1){
                            if($v['id'] == $v1['follow_id']){
                                $list['user'][$k]['is_follow'] = $user::FOLLOW_YES;
                            }
                        }
                    }
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($list));
        }
    }

    /**
     * 最新模特
     * @return \think\response\Json
     */
    public function newModelV1_2(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $user  = new Bases('user');
            $follow   = new Bases('follow');
            try{
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);
                // 登录后相关处理
                $follow_ids   = [];
                if($uid){
                    // 登录验证
                    self::checkLogin();
                    // 登陆后查询判断是否关注
                    $follow_ids = $follow->selectData(['fans_id'=>$uid,'status'=>$follow::FOLLOW_YES],'follow_id');
                }

                // 用户信息
                $userList = $user->page(['status'=>$user::STATUS_ON,'type_id'=>['neq',$user::USER_TYPE_NORMAL]],'id desc',12,[],[],'id,nickname,portrait,fans_num,click_num',$post['page']);
                $list['user'] = $userList->items();
                // 循环处理数据
                foreach($list['user'] as $k => $v){
                    $list['user'][$k]['is_follow']  = $follow::FOLLOW_NOT;
                    if($follow_ids){
                        // 该用户有关注对象，循环拿出来
                        foreach($follow_ids as $v1){
                            if($v['id'] == $v1['follow_id']){
                                $list['user'][$k]['is_follow'] = $user::FOLLOW_YES;
                            }
                        }
                    }
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($list));
        }
    }

    /**
     * 未读消息数
     * @return \think\response\Json
     */
    public function messageNum(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $user  = new Bases('user');
            $notice   = new Bases('notice');
            $message  = new Bases('message');
            $read     = new Bases('notice_read');
            /*// -----------------------
            $arr['token'] = $post['token'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);
                $info = $user->find(['id'=>$uid]);
                $count = 0;
                if($uid){
                    // 登录验证
                    self::checkLogin();
                    // 登录后用户已读消息
                    $reads   = $read->selectData(['uid'=>$uid]);
                    $notices = $notice->selectData(['is_release'=>$notice::RELEASE_YES,'status'=>Bases::DELETE_NOT,'create_time'=>['>=',$info['create_time']]]);
                    $message = $message->selectData(['uid'=>$uid,'status'=>$message::READ_NOT]);
                    $read_count    = count($reads);
                    $notice_count  = count($notices);
                    $message_count = count($message);
                    $count = $notice_count - $read_count + $message_count;
                }

                // 未读消息数量
                $list['notice_num'] = $count;
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($list));
        }
    }

    /**
     * 关于我们
     * @return \think\response\View
     */
    public function about(){
        return view('index/about');
    }

    /**
     * 隐私声明啊
     * @return \think\response\View
     */
    public function privacy(){
        return view('index/privacy');
    }

    /**
     * 用户协议
     */
    public function agreement(){
     return view('index/agreement');
    }
}