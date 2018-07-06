<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/3/29 0029
 * Time: 11:06
 */
namespace app\api\controller;
use app\models\Bases;
use app\service\Redis;
use app\service\Rsa;

class Search extends Base{
    /**
     * 搜索推荐
     * @return \think\response\Json
     */
    public function searchRecommend(){
        $config = new Bases('config');
        try{
            $label = $config->value(['cname'=>'search_label'],'option');
            $label = explode(',',$label);
        } catch (\LogicException $e){
            return json(self::formatResult($e->getCode(),$e->getMessage()));
        }
        return json(self::formatSuccessResult(['label'=>$label]));
    }

    /**
     * 搜索
     * @return \think\response\Json
     */
    public function search(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $user  = new Bases('user');
            $follow = new Bases('follow');
            $dynamic = new Bases('dynamic');
            $order   = new Bases('order');
            /*// -----------------------
            $arr['token'] = $post['token'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);

                $list = [];
                if($uid){
                    // 登录验证
                    self::checkLogin();
                    if($post['search']){
                        // 登陆后查询查看过的动态
                        $order_ids = $order->selectData(['uid' => $uid,'type_id' => $order::ORDER_TYPE_HONGBAO,'status'=>$order::PAY_YES], 'dynamic_id');
                        // 判断用户是否关注
                        $follow_ids = $follow->selectData(['fans_id'=>$uid,'status'=>$follow::FOLLOW_YES],'follow_id');

                        // 搜索查询用户信息
                        $list['user'] = $user->selectData(['nickname'=>$post['search'],'status'=>$user::STATUS_ON],'id as uid,nickname,thumb as portrait,fans_num');
                        // 循环处理数据
                        foreach($list['user'] as $k => $v){
                            $list['user'][$k]['is_follow']  = $follow::FOLLOW_NOT;
                            if($follow_ids){
                                // 该用户有关注对象，循环拿出来
                                foreach($follow_ids as $v1){
                                    if($v['uid'] == $v1['follow_id']){
                                        $list['user'][$k]['is_follow'] = $user::FOLLOW_YES;
                                    }
                                }
                            }
                        }
                        // 搜索查询动态信息
                        $where = ['a.status'=>$dynamic::STATUS_ON,'a.is_delete'=>$dynamic::DELETE_NOT,'a.tag'=>['like','%'.$post['search'].'%']];
                        $join  = [['tq_dynamic_image b','a.image_id = b.id','LEFT'],['tq_user c','a.uid = c.id','LEFT']];
                        $field = 'a.id as dynamic_id,a.uid,a.descrip,a.tag,a.agree_num,a.look_num,a.create_time,b.quantity,b.price as amount,c.nickname,c.thumb as portrait,b.images,b.thumb,c.click_num';
                        $dynamicList = $dynamic->page($where,'a.position_id asc ,a.id desc',20,[],$join,$field,$post['page']);
                        $list['dynamic'] = $dynamicList->items();
                        foreach($list['dynamic'] as $k=>$v){
                            /*$tag = explode(',',$v['tag']);
                            if(!in_array($post['search'],$tag)){
                                unset($list['dynamic'][$k]);continue;
                            }*/
                            $list['dynamic'][$k]['is_look']     = $v['uid'] == $uid ? $order::READ_YES : $order::READ_NOT;
                            $list['dynamic'][$k]['images']      = empty(json_decode($v['images'],true))  ? [] : json_decode($v['images'],true);
                            $list['dynamic'][$k]['thumb']       = empty(json_decode($v['thumb'],true))  ? [] : json_decode($v['thumb'],true);
                            $list['dynamic'][$k]['tag']         = empty($v['tag']) ? [] : explode(',',$v['tag']);
                            $list['dynamic'][$k]['create_time'] = format_date1($v['create_time']);

                            foreach($list['dynamic'][$k]['images'] as $k1=>$v1){
                                if(empty($list['dynamic'][$k]['images'][$k1]['name'])){
                                    unset($list['dynamic'][$k]['images'][$k1]);
                                    unset($list['dynamic'][$k]['thumb'][$k1]);
                                }
                            }
                            sort($list['dynamic'][$k]['images']);
                            sort($list['dynamic'][$k]['thumb']);;

                            if($order_ids  && $v['uid'] != $uid){
                                // 该用户所有查看对象，循环拿出来
                                foreach($order_ids as $v1){
                                    if($v['dynamic_id'] == $v1['dynamic_id']){
                                        $list['dynamic'][$k]['is_look'] = $order::READ_YES;
                                    }
                                }
                            }
                        }
                        rsort($list['dynamic']);
                    }
                } else {
                    if($post['search']){
                        // 搜索查询用户信息
                        $list['user'] = $user->selectData(['nickname'=>$post['search'],'status'=>$user::STATUS_ON],'id as uid,nickname,thumb as portrait,fans_num');
                        // 循环处理数据
                        foreach($list['user'] as $k => $v){
                            $list['user'][$k]['is_follow']  = $follow::FOLLOW_NOT;
                        }

                        // 搜索查询动态信息
                        $where = ['a.status'=>$dynamic::STATUS_ON,'a.is_delete'=>$dynamic::DELETE_NOT,'a.tag'=>['like','%'.$post['search'].'%']];
                        $join  = [['tq_dynamic_image b','a.image_id = b.id','LEFT'],['tq_user c','a.uid = c.id','LEFT']];
                        $field = 'a.id as dynamic_id,a.uid,a.descrip,a.tag,a.agree_num,a.look_num,a.create_time,b.quantity,b.price as amount,c.nickname,c.thumb as portrait,b.images,b.thumb,c.click_num';
                        $dynamicList = $dynamic->page($where,'a.position_id asc ,a.id desc',20,[],$join,$field,$post['page']);
                        $list['dynamic'] = $dynamicList->items();
                        foreach($list['dynamic'] as $k=>$v){
                            /*$tag = explode(',',$v['tag']);
                            if(!in_array($post['search'],$tag)){
                                unset($list['dynamic'][$k]);continue;
                            }*/
                            $list['dynamic'][$k]['is_look']  = $order::READ_NOT;
                            $list['dynamic'][$k]['images'] = empty(json_decode($v['images'],true))  ? [] : json_decode($v['images'],true);
                            $list['dynamic'][$k]['thumb']  = empty(json_decode($v['thumb'],true))  ? [] : json_decode($v['thumb'],true);
                            $list['dynamic'][$k]['tag']    = empty($v['tag']) ? [] : explode(',',$v['tag']);
                            $list['dynamic'][$k]['create_time'] = format_date1($v['create_time']);
                        }
                        rsort($list['dynamic']);
                    }
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($list));
        }
    }

    /**
     * 动态详情
     * @return \think\response\Json
     */
    public function searchDynamicShow(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $order = new Bases('order');
            $dynamic = new Bases('dynamic');
            $agree   = new Bases('dynamicAgree');
            /*// -----------------------
            $arr['token'] = $post['token'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);
                if($uid){
                    // 登录验证
                    self::checkLogin();
                    // 登陆后查询点过赞的动态
                    $is_agree = $agree->value(['agree_id'=>$uid,'did'=>$post['did']],'id');
                    // 登陆后查询查看过的动态
                    $is_look  = $order->value(['uid' => $uid,'dynamic_id'=>$post['did'],'type_id' => $order::ORDER_TYPE_HONGBAO,'status'=>$order::PAY_YES], 'id');

                    $info = $dynamic->joinOne([['tq_dynamic_image b','a.image_id = b.id','LEFT'],['tq_user c','a.uid = c.id','LEFT']],['a.id'=>$post['did']],'a.id as dynamic_id,a.uid,a.descrip,a.tag,a.agree_num,a.look_num,a.create_time,b.quantity,b.price as amount,c.nickname,c.thumb as portrait,b.images,b.thumb,c.click_num');
                    $info['is_agree'] = empty($is_agree) ? $dynamic::AGREE_NOT : $dynamic::AGREE_YES;
                    $info['is_look']  = $info['uid'] == $uid ? $order::READ_YES : (empty($is_look) ? $order::READ_NOT : $order::READ_YES);
                    $info['images'] = empty(json_decode($info['images'],true))  ? [] : json_decode($info['images'],true);
                    $info['thumb']  = empty(json_decode($info['thumb'],true))  ? [] : json_decode($info['thumb'],true);
                    $info['tag']    = empty($info['tag']) ? [] : explode(',',$info['tag']);
                    $info['create_time'] = format_date1($info['create_time']);
                } else {
                    $info = $dynamic->joinOne([['tq_dynamic_image b','a.image_id = b.id','LEFT'],['tq_user c','a.uid = c.id','LEFT']],['a.id'=>$post['did']],'a.id as dynamic_id,a.uid,a.descrip,a.tag,a.agree_num,a.look_num,a.create_time,b.quantity,b.price as amount,c.nickname,c.thumb as portrait,b.images,b.thumb,c.click_num');
                    $info['is_agree'] = $dynamic::AGREE_NOT;
                    $info['is_look']  = $order::READ_NOT;
                    $info['images'] = empty(json_decode($info['images'],true))  ? [] : json_decode($info['images'],true);
                    $info['thumb']  = empty(json_decode($info['thumb'],true))  ? [] : json_decode($info['thumb'],true);
                    $info['tag']    = empty($info['tag']) ? [] : explode(',',$info['tag']);
                    $info['create_time'] = format_date1($info['create_time']);
                }
                foreach($info['images'] as $k=>$v){
                    if(empty($v['name'])){
                        unset($info['images'][$k]);
                        unset($info['thumb'][$k]);
                    }
                }
                sort($info['images']);
                sort($info['thumb']);
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($info));
        }
    }

    /**
     * 搜索空推荐
     * @return \think\response\Json
     */
    public function recommendV1_2(){
        try{
            $user = new Bases('user');
            $list = $user->selectData(['status'=>$user::STATUS_ON,'type_id'=>['neq',$user::USER_TYPE_NORMAL]],'id,nickname,thumb as portrait,click_num','id desc',20);
            shuffle($list);
            $list = array_slice($list,1,5);
        } catch (\LogicException $e){
            return json(self::formatResult($e->getCode(),$e->getMessage()));
        }
        return json(self::formatSuccessResult($list));
    }
}