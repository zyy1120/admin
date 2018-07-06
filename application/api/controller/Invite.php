<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/3/29 0029
 * Time: 9:45
 */
namespace app\api\controller;
use app\models\Bases;
use app\service\Redis;
use app\service\Rsa;

class Invite extends Base {
    /**
     * 推广列表
     * @return \think\response\Json
     */
    public function inviteList(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $invite = new Bases('invite');
            $type   = new Bases('userType');
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

                $list['ratio'] = $type->value(['id'=>Bases::USER_TYPE_AGENT],'ratio');

                $list['QR'] = "https://".$_SERVER['HTTP_HOST'].DS."wechat/register/register?uid=".$uid;

                $inviteList = $invite->page(['a.inviter_id'=>$uid],'a.id desc',20,[],[['tq_user b','a.invitee_id = b.id','LEFT']],'b.username,b.nickname,b.portrait,a.inviter_money,a.create_time',$post['page']);
                $list['invite'] = $inviteList->items();
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($list));
        }
    }


    /**
     * 邀请朋友
     * @return \think\response\Json
     */
    public function inviteUserV1_2(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $invite = new Bases('inviteFriend');
            $conf   = new Bases('config');
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

                $list['invite_mili'] = $conf->value(['cname'=>'invite_mili'],'option');
                $list['cost_mili'] = $conf->value(['cname'=>'invite_cost'],'option');
                $list['QR'] = "https://".$_SERVER['HTTP_HOST'].DS."wechat/register/inviteFriend?uid=".$uid;

                $inviteList = $invite->page(['a.inviter_id'=>$uid],'a.id desc',20,[],[['tq_user b','a.invitee_id = b.id','LEFT']],'b.username,a.create_time,a.status',$post['page']);
                $list['invite'] = $inviteList->items();
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($list));
        }
    }
}