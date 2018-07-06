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

class Notice extends Base {
    /**
     * 公告列表
     * @return \think\response\Json
     */
    public function noticeList(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa    = new Rsa();
            $redis  = new Redis();
            $user   = new Bases('user');
            $notice = new Bases('notice');
            $read   = new Bases('noticeRead');
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

                $info = $user->find(['id'=>$uid]);

                $ids = $read->selectData(['uid'=>$uid],'notice_id');

                // 公告列表
                $noticeList = $notice->page(['status'=>$notice::DELETE_NOT,'is_release'=>$notice::RELEASE_YES,'create_time'=>['>=',$info['create_time']]],'id desc',20,[],[],'id,title,create_time',$post['page']);
                $list = $noticeList->items();
                if($list){
                    foreach($list as $k=>$v){
                        $list[$k]['is_read'] = $notice::READ_NOT;
                        $list[$k]['url']     = "https://".$_SERVER['HTTP_HOST'].DS."api/notice/noticeShow?id=".$v['id'];
                        if($ids){
                            foreach($ids as $k1=>$v1){
                                if($v['id'] == $v1['notice_id']){
                                    $list[$k]['is_read'] = $notice::READ_YES;
                                }
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
     * 公告详情
     * @return \think\response\Json|\think\response\View
     */
    public function noticeShow(){
        if(request()->isPost()){
            $post   = input('param.');
            $rsa    = new Rsa();
            $redis  = new Redis();
            $read   = new Bases('noticeRead');
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

                $rinfo = $read->find(['uid'=>$uid,'notice_id'=>$post['id']]);
                if(!$rinfo){
                    $bool = $read->insertData(['uid'=>$uid,'notice_id'=>$post['id'],'create_time'=>time()]);
                    if(!$bool){
                        throw new \LogicException('操作失败',1010);
                    }
                }
                $url = ['url'=>"https://".$_SERVER['HTTP_HOST'].DS."api/notice/noticeShow?id=".$post['id']];
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($url));
        }
        $notice = new Bases('notice');
        $get  = input('param.');
        $info = $notice->find(['id'=>$get['id']]);
        if(!$info){
            throw new \LogicException('公告不存在',1030);
        }
        $info['content'] = html_entity_decode($info['content']);
        return view('notice/noticeShow',['info'=>$info]);
    }
}