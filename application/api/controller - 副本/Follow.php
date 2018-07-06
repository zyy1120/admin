<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/3/24 0024
 * Time: 14:23
 */
namespace app\api\controller;
use app\models\Bases;
use app\service\Redis;
use app\service\Rsa;

class Follow extends Base {
    /**
     * 关注列表
     * @return \think\response\Json
     */
    public function followDynamicList(){
        if(request()->isPost()){
            $post    = input('param.');
            $rsa     = new Rsa();
            $redis   = new Redis();
            $dynamic = new Bases('dynamic');
            $agree   = new Bases('dynamicAgree');
            $order   = new Bases('order');
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

                // 登陆后查询点过赞的动态
                $agree_ids = $agree->selectData(['agree_id'=>$uid],'did');
                // 登陆后查询查看过的动态
                $order_ids = $order->selectData(['uid'=>$uid,'type_id'=>$order::ORDER_TYPE_HONGBAO,'status'=>$order::PAY_YES],'dynamic_id');

                $where = ['d.fans_id'=>$uid,'a.status'=>$dynamic::STATUS_ON,'a.is_delete'=>$dynamic::DELETE_NOT,'d.status'=>$dynamic::FOLLOW_YES];
                $join  = [['tq_user b','a.uid = b.id','LEFT'],['tq_dynamic_image c','a.image_id = c.id','LEFT'],['tq_follow d','a.uid = d.follow_id','RIGHT']];
                $field = "a.id as did,a.uid,b.nickname,b.thumb as portrait,a.descrip,a.tag,a.type_id,c.images,c.thumb,c.price as amount,c.quantity,a.agree_num,a.look_num,a.create_time,b.click_num";
                $dynamicList = $dynamic->page($where,'a.position_id asc ,a.id desc',20,[],$join,$field,$post['page']);
                $list = $dynamicList->items();
                if($list){
                    // 循环处理数据
                    foreach($list as $k => $v){
                        $list[$k]['is_agree']    = $dynamic::AGREE_NOT;
                        $list[$k]['is_look']     = $v['uid'] == $uid ? $order::READ_YES : $order::READ_NOT;
                        $list[$k]['images']      = empty(json_decode($v['images'],true)) ? [] : json_decode($v['images'],true);
                        $list[$k]['thumb']       = empty(json_decode($v['thumb'],true))  ? [] : json_decode($v['thumb'],true);
                        $list[$k]['tag']         = empty($v['tag']) ? [] : explode(',',$v['tag']);
                        $list[$k]['create_time'] = format_date1($v['create_time']);
                        sort($list[$k]['images']);
                        sort($list[$k]['thumb']);;
                        if($agree_ids){
                            // 该用户所有点赞动态，循环拿出来
                            foreach($agree_ids as $v1){
                                if($v['did'] == $v1['did']){
                                    $list[$k]['is_agree'] = $dynamic::AGREE_YES;
                                }
                            }
                        }
                        if($order_ids && $v['uid'] != $uid){
                            // 该用户所有查看对象，循环拿出来
                            foreach($order_ids as $v1){
                                if($v['did'] == $v1['dynamic_id']){
                                    $list[$k]['is_look'] = $order::READ_YES;
                                }
                            }
                        }
                    }
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            //var_dump(json_encode(self::formatSuccessResult($list),true));return;
            return json(self::formatSuccessResult($list));
        }
    }

    /**
     * 关注
     * @return \think\response\Json
     */
    public function follow(){
        if(request()->isPost()){
            $post   = input('param.');
            $rsa    = new Rsa();
            $redis  = new Redis();
            $user   = new Bases('user');
            $follow = new Bases('follow');
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

                $follow->startTrans();
                // 判断是否关注
                $is_follow = $follow->find(['follow_id'=>$post['uid'],'fans_id'=>$uid]);
                $bool = true;
                if($is_follow){
                    // 已关注过
                    if($is_follow['status'] == $follow::FOLLOW_YES){
                        // 取消关注
                        $rs  = $follow->setField(['follow_id'=>$post['uid'],'fans_id'=>$uid],['status'=>$follow::FOLLOW_NOT]);
                        $rs1 = $user->_setDec(['id'=>$uid],'follow_num');// 关注数-1
                        $rs2 = $user->_setDec(['id'=>$post['uid']],'fans_num');// 粉丝数-1
                        $flag = $follow::FOLLOW_NOT;
                    } else {
                        // 关注
                        $rs  = $follow->updateData(['status'=>$follow::FOLLOW_YES,'create_time'=>time()],['follow_id'=>$post['uid'],'fans_id'=>$uid]);
                        $rs1 = $user->_setInc(['id'=>$uid],'follow_num');// 关注数+1
                        $rs2 = $user->_setInc(['id'=>$post['uid']],'fans_num');// 粉丝数+1
                        $flag = $follow::FOLLOW_YES;
                        // 发送站内消息
                        $bool = $message->insertData(['uid'=>$post['uid'],'send_id'=>$uid,'content'=>'关注了你','create_time'=>time()]);
                    }
                } else {
                    // 从未关注
                    $rs  = $follow->insertData(['follow_id'=>$post['uid'],'fans_id'=>$uid,'create_time'=>time()]);
                    $rs1 = $user->_setInc(['id'=>$uid],'follow_num');// 关注数+1
                    $rs2 = $user->_setInc(['id'=>$post['uid']],'fans_num');// 粉丝数+1
                    $flag = $follow::FOLLOW_YES;
                    // 发送站内消息
                    $bool = $message->insertData(['uid'=>$post['uid'],'send_id'=>$uid,'content'=>'关注了你','create_time'=>time()]);
                }

                if(false === $is_follow || !$rs || !$rs1 || !$rs2 || !$bool){
                    $follow->rollback();
                    throw new \LogicException('操作失败',1010);
                }

            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            $follow->commit();
            return json(self::formatSuccessResult(['is_follow'=>$flag]));
        }
    }

    /**
     * 我的关注列表
     * @return \think\response\Json
     */
    public function followList(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $follow = new Bases('follow');
            /*// -----------------------
            $arr['token'] = $post['token'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                //self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);

                // 判断是否关注
                $follow_ids = $follow->selectData(['fans_id'=>$uid,'status'=>$follow::FOLLOW_YES],'follow_id');
                $followList = $follow->page(['a.fans_id'=>$uid,'a.status'=>$follow::FOLLOW_YES],'a.id desc',20,[],[['tq_user b','a.follow_id = b.id','LEFT']],'a.follow_id,a.create_time as follow_time,b.nickname,b.portrait,b.fans_num',$post['page']);
                $list = $followList->items();
                // 循环处理数据
                foreach($list as $k => $v){
                    $list[$k]['is_follow']  = $follow::FOLLOW_NOT;
                    if($follow_ids){
                        // 该用户有关注对象，循环拿出来
                        foreach($follow_ids as $v1){
                            if($v['follow_id'] == $v1['follow_id']){
                                $list[$k]['is_follow'] = $follow::FOLLOW_YES;
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
     * 我的粉丝列表
     * @return \think\response\Json
     */
    public function fansList(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $follow = new Bases('follow');
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

                $list = $follow->page(['a.follow_id'=>$uid,'a.status'=>$follow::FOLLOW_YES],'a.id desc',20,[],[['tq_user b','a.fans_id = b.id','LEFT']],'a.fans_id,b.nickname,b.portrait,b.fans_num',$post['page']);

            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($list->items()));
        }
    }
}