<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/3/28 0028
 * Time: 9:23
 */
namespace app\api\controller;
use app\models\Bases;
use app\service\Redis;
use app\service\Rsa;

class Ucenter extends Base{
    /**
     * 个人信息
     * @return \think\response\Json
     */
    public function personal(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $user  = new Bases('user');
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

                $info = $user->joinOne([['tq_wechat b','a.id = b.uid','LEFT'],['tq_account c','a.id = c.uid','LEFT']],['a.id'=>$uid],'a.id as uid,a.type_id,a.nickname,a.thumb as portrait,a.sex,a.dynamic_num,a.follow_num,a.fans_num,b.status,c.account,c.mili,a.is_shell,a.sign');
                if($info['status'] === null ){
                    $info['status'] = 0;
                }
                $info['account'] = sprintf('%.2f',$info['account']);

            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult($info));
        }
    }

    /**
     * 修改用户资料
     * @return \think\response\Json
     */
    public function personalSave(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $user  = new Bases('user');
            /*// -----------------------
            $arr['token'] = $post['token'];
            $arr['nickname'] = $post['nickname'];
            $arr['sex'] = $post['sex'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);

                // 根据token获取用户id
                $uid = $redis->get($data['token']);

                $info = $user->find(['nickname'=>$data['nickname']]);
                if($info && $info['id'] != $uid){
                    throw new \LogicException('该昵称已存在',1011);
                }

                $files = request()->file('image');
                if($files){
                    // 上传头像
                    $portrait = upload('portrait');
                    if(!$portrait){
                        throw new \LogicException('上传失败',1022);
                    }
                    $data['portrait'] = $portrait['ori'][0];
                    $data['thumb']    = $portrait['thumb'][0];
                }
                $data['id'] = $uid;
                unset($data['token']);
                $bool = $user->updateData($data);
                if(false === $bool) {
                    throw new \LogicException('操作失败',1010);
                }

            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult());
        }
    }

    public function personalSaveV1_2(){
        if(request()->isPost()){
            $post  = input('param.');
            $rsa   = new Rsa();
            $redis = new Redis();
            $user  = new Bases('user');
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);

                // 根据token获取用户id
                $uid = $redis->get($data['token']);

                $info = $user->find(['nickname'=>$data['nickname']]);
                if($info && $info['id'] != $uid){
                    throw new \LogicException('该昵称已存在',1011);
                }

                if($post['image']){
                    $image = json_decode(htmlspecialchars_decode($post['image']),true);
                    $arr['portrait'] = $image['ori'][0];
                    $arr['thumb'] = $image['thumb'][0];
                }
                if($data['nickname']){
                    $arr['nickname'] = $data['nickname'];
                }
                $arr['id'] = $uid;
                $arr['sign'] = $post['sign'];
                $bool = $user->updateData($arr);
                if(false === $bool) {
                    throw new \LogicException('操作失败',1010);
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult());
        }
    }

    /**
     * 个人中心
     * @return \think\response\Json
     */
    public function uCenter(){
        if (request()->isPost()) {
            $post    = input('param.');
            $rsa     = new Rsa();
            $redis   = new Redis();
            $user    = new Bases('user');
            $dynamic = new Bases('dynamic');
            $follow  = new Bases('follow');
            $agree   = new Bases('dynamicAgree');
            $order   = new Bases('order');
            $wechat  = new Bases('wechat');
            $wechat_buy = new Bases('wechat_buy');
            /*// -----------------------
            $arr['token'] = $post['token'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key, $arr);*/
            try {
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'], $post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);
                $agree_ids = [];
                $order_ids = [];
                if($uid){
                    // 登录验证
                    self::checkLogin();
                    // 查询点过赞的动态
                    $agree_ids  = $agree->selectData(['agree_id' => $uid], 'did');
                    if(!empty($post['id'])){
                        // 查询购买过的动态
                        $order_ids  = $order->selectData(['uid' => $uid, 'type_id' => $order::ORDER_TYPE_HONGBAO,'status'=>$order::PAY_YES], 'dynamic_id');
                        $bool = $user->_setInc(['id'=>$post['id']],'click_num');
                        if(!$bool){
                            throw new \LogicException('操作失败',1010);
                        }
                    }

                    // 查询用户信息
                    $id = empty($post['id']) ? $uid : $post['id'];
                    $list['user'] = $user->find(['id' => $id], 'id uid,nickname,thumb,portrait,sex,dynamic_num,follow_num,fans_num,sign,click_num');
                    $list['user']['url'] = "https://".$_SERVER['HTTP_HOST'].DS."wechat/h5/personal?uid=".$uid;
                    if(!empty($post['id'])){
                        // 判断是否关注
                        $status = $follow->value(['follow_id'=>$post['id'],'fans_id'=>$uid],'status');
                        // 处理安卓无法接受null
                        $list['user']['is_follow'] = $status === null ? 0 : $status;
                        // 查询用户是否购买过该用微信
                        $bool = $wechat_buy->find(['uid'=>$uid,'beneficiary_id'=>$post['id'],'is_pay'=>$wechat_buy::PAY_YES]);
                        $list['user']['is_buy_wechat'] = empty($bool) ? 0 : 1;
                        $list['user']['url'] = "https://".$_SERVER['HTTP_HOST'].DS."wechat/h5/personal?uid=".$post['id'];
                        // 判断是否出售微信，出售微信价格
                        $wechat_info = $wechat->find(['uid'=>$post['id']]);
                        if(!$wechat_info || $wechat_info['status'] == $wechat::SELL_NOT){
                            if($list['user']['is_buy_wechat']){
                                // 如果购买过微信，下架后也返回微信号
                                $list['user']['wechat'] = $wechat_info['wechat'];
                            }
                            $list['user']['is_sell'] = 0;
                        }
                        if($wechat_info && $wechat_info['status'] == $wechat::SELL_YES){
                            $list['user']['is_sell'] = 1;
                            $list['user']['wechat_price'] = $wechat_info['money'];
                            $list['user']['wechat']  = $wechat_info['wechat'];
                        }
                        // 查看指定用户动态
                        $id = $post['id'];
                    }
                } else {
                    $id = $post['id'];
                    $bool = $user->_setInc(['id'=>$id],'click_num');
                    if(!$bool){
                        throw new \LogicException('操作失败',1010);
                    }
                    $list['user'] = $user->find(['id' => $id], 'nickname,thumb,portrait,sex,dynamic_num,follow_num,fans_num,sign,click_num');
                    $list['user']['is_buy_wechat'] = 0;
                    $list['user']['url'] = "https://".$_SERVER['HTTP_HOST'].DS."wechat/h5/personal?uid=".$post['id'];
                    // 判断是否出售微信，出售微信价格
                    $wechat_info = $wechat->find(['uid'=>$post['id']]);
                    if(!$wechat_info || $wechat_info['status'] == $wechat::SELL_NOT){
                        if($list['user']['is_buy_wechat']){
                            // 如果购买过微信，下架后也返回微信号
                            $list['user']['wechat'] = $wechat_info['wechat'];
                        }
                        $list['user']['is_sell'] = 0;
                    }
                    if($wechat_info && $wechat_info['status'] == $wechat::SELL_YES){
                        $list['user']['is_sell'] = 1;
                        $list['user']['wechat_price'] = $wechat_info['money'];
                        $list['user']['wechat']  = $wechat_info['wechat'];
                    }
                }

                $where = ['a.uid'=>$id,'a.status' => $dynamic::STATUS_ON, 'a.is_delete' => $dynamic::DELETE_NOT];
                $join = [['tq_user b', 'a.uid = b.id', 'LEFT'], ['tq_dynamic_image c', 'a.image_id = c.id', 'LEFT']];
                $field = "a.id as did,a.uid,a.is_top,a.position_id,b.nickname,b.portrait,b.thumb as portrait_thumb,a.descrip,a.tag,a.type_id,c.images,c.thumb,a.agree_num,a.look_num,a.create_time,c.price as amount,c.quantity";
                $dynamicList = $dynamic->page($where, 'a.is_top desc,a.id desc', 20, [], $join, $field,$post['page']);
                $list['dynamic'] = $dynamicList->items();
                if($list['dynamic']) {
                    foreach ($list['dynamic'] as $k => $v) {
                        $list['dynamic'][$k]['is_agree'] = $dynamic::AGREE_NOT;
                        $list['dynamic'][$k]['is_look']  = empty($uid) ? $order::READ_NOT : ($uid == $v['uid'] ? $order::READ_YES : $order::READ_NOT);
                        $list['dynamic'][$k]['is_recommend']  = $order::RECOMMEND_NOT;
                        $list['dynamic'][$k]['images']      = empty(json_decode($v['images'],true)) ? [] : json_decode($v['images'],true);
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

                        if($v['position_id'] < 999){
                            $list['dynamic'][$k]['is_recommend']  = $order::RECOMMEND_YES;
                        }
                        if ($agree_ids && $uid) {
                            // 该用户所有点赞动态，循环拿出来
                            foreach ($agree_ids as $v1) {
                                if ($v['did'] == $v1['did']) {
                                    $list['dynamic'][$k]['is_agree'] = $dynamic::AGREE_YES;
                                }
                            }
                        }
                        if ($order_ids && !empty($post['id']) && $uid) {
                            // 该用户所有查看的动态，循环拿出来
                            foreach ($order_ids as $v1) {
                                if ($v['did'] == $v1['dynamic_id']) {
                                    $list['dynamic'][$k]['is_look'] = $order::READ_YES;
                                }
                            }
                        }
                    }
                }
            }catch(\LogicException $e){
                return json(self::formatResult($e->getCode(), $e->getMessage()));
            }
            return json(self::formatSuccessResult($list));
        }
    }
}