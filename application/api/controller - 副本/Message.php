<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/3/24 0024
 * Time: 13:32
 */
namespace app\api\controller;
use app\models\Bases;
use app\service\Redis;
use app\service\Rsa;

class Message extends Base  {
    /**
     * 消息列表
     * @return \think\response\Json
     */
    public function messageList(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa    = new Rsa();
            $redis  = new Redis();
            $message = new Bases('message');
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

                // 点击消息自动标记所有消息为阅读状态
                $bool = $message->setField(['uid'=>$uid],['status'=>$message::READ_YES]);
                if(false === $bool){
                    throw new \LogicException('操作失败',1010);
                }

                // 消息列表
                $messageList = $message->page(['a.uid'=>$uid,'a.is_delete'=>$message::DELETE_NOT],'id desc',20,[],[['tq_user b','a.send_id = b.id','LEFT']],'a.id,b.nickname,b.portrait,a.content,a.create_time',$post['page']);
                $list = $messageList->items();
                foreach($list as $k => $v){
                    if(empty($v['nickname'])){
                        $list[$k]['nickname'] = '';
                    }
                    $list[$k]['create_time'] = format_date($v['create_time']);
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($list));
        }
    }
}