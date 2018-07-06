<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/3/24 0024
 * Time: 15:19
 */
namespace app\api\controller;
use app\models\Bases;
use app\service\KamYellow;
use app\service\Redis;
use app\service\Rsa;

class Dynamic extends Base{
    /**
 * 动态列表
 * @return \think\response\Json
 */
    public function dynamicList(){
        if(request()->isPost()){
            $post    = input('param.');
            $rsa     = new Rsa();
            $redis   = new Redis();
            $dynamic = new Bases('dynamic');
            $agree   = new Bases('dynamicAgree');
            $order   = new Bases('order');

            /*$arr['token'] = $post['token'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);
                // 登录后
                $agree_ids = [];
                $order_ids = [];
                if($uid){
                    // 登录验证
                    self::checkLogin();
                    // 登陆后查询点过赞的动态
                    $agree_ids = $agree->selectData(['agree_id'=>$uid],'did');
                    // 登陆后查询查看过的动态
                    $order_ids = $order->selectData(['uid' => $uid,'type_id' => $order::ORDER_TYPE_HONGBAO,'status'=>$order::PAY_YES], 'dynamic_id');
                }

                $where = ['a.status'=>$dynamic::STATUS_ON,'a.is_delete'=>$dynamic::DELETE_NOT];
                $join  = [['tq_user b','a.uid = b.id','LEFT'],['tq_dynamic_image c','a.image_id = c.id','LEFT']];
                $field = "a.id as did,a.uid,b.nickname,b.thumb as portrait,a.descrip,a.tag,a.type_id,c.images,c.thumb,c.quantity,c.price as amount,a.agree_num,a.look_num,a.create_time,b.click_num";
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
                        foreach($list[$k]['images'] as $k1=>$v1){
                            if(empty($list[$k]['images'][$k1]['name'])){
                                unset($list[$k]['images'][$k1]);
                                unset($list[$k]['thumb'][$k1]);
                            }
                        }
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
            return json(self::formatSuccessResult($list));
        }
    }

    /**
     * 动态添加
     * @return \think\response\Json
     */
    public function dynamicAdd(){
        if(request()->isPost()){
            $post    = input('param.');
            $rsa     = new Rsa();
            $redis   = new Redis();
            $user    = new Bases('user');
            $dynamic = new Bases('dynamic');
            $type    = new Bases('dynamicImage');
            $statist = new Bases('statistics');
            /*// -----------------------
            $arr['token'] = $post['token'];
            $arr['price'] = $post['price'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);

                // 根据token获取用户id
                $uid = $redis->get($data['token']);

                // 图片上传
                $files = request()->file('image');
                if(empty($files)){
                    throw new \LogicException('上传文件不能为空',1024);
                }
                $path = upload('dynamic');
                $count = count($path['ori']);
                $price = explode(',',$data['price']);
                $money = 0;
                $file_detection = new KamYellow();
                foreach($path['ori'] as $k=>$v){
                    $arr_kam_yellow_picture[$k]['labels'] = $file_detection->main($path['ori'][$k]);//鉴定结果
                    $arr_kam_yellow_picture[$k]['name'] = $v;
                    $arr_kam_yellow_picture[$k]['price'] = $price[$k];
                    $money += $price[$k];
                }
                foreach($path['thumb'] as $k=>$v){
                    $arr_kam_yellow_thumb[$k]['labels'] = $arr_kam_yellow_picture[$k]['labels'];
                    $arr_kam_yellow_thumb[$k]['name']  = $v;
                    $arr_kam_yellow_thumb[$k]['price'] = $price[$k];
                }
                //检测是否有异常图片
                $is_abnormal = $dynamic::IS_ABNORMAL_NO;  //默认无异常
                foreach($arr_kam_yellow_picture as $v){
                    if(($v['labels']['confidence'] == 1 || $v['labels']['confidence'] == 2) && $v['labels']['label'] == '色情图片'){
                        $is_abnormal = $dynamic::IS_ABNORMAL_YES;
                        break;
                    }
                }
                $arr_kam_yellow_picture = json_encode($arr_kam_yellow_picture,true); //所有原图鉴黄图片组成的json串
                $arr_kam_yellow_thumb   = json_encode($arr_kam_yellow_thumb,true);   //所有缩略图的鉴黄结果
                $dynamic->startTrans();// 开启事务
                $id   = true;
                $bool = true;
                if($post['type_id'] == $dynamic::DYNAMIC_TYPE_NORMAL){
                    $id   = $type->insertData(['images'=>$arr_kam_yellow_picture,'thumb'=>$arr_kam_yellow_thumb,'quantity'=>$count,'price'=>'0']);
                    $bool = $dynamic->insertData(['uid'=>$uid,'is_abnormal'=>$is_abnormal,'type_id'=>$post['type_id'],'image_id'=>$id,'descrip'=>$post['descrip'],'create_time'=>time()]);
                }
                if($post['type_id'] == $dynamic::DYNAMIC_TYPE_HONGBAO){
                    $id = $type->insertData(['images'=>$arr_kam_yellow_picture,'thumb'=>$arr_kam_yellow_thumb,'quantity'=>$count,'price'=>$money]);
                    $bool = $dynamic->insertData(['uid'=>$uid,'is_abnormal'=>$is_abnormal,'type_id'=>$post['type_id'],'image_id'=>$id,'descrip'=>$post['descrip'],'tag'=>$post['tag'],'create_time'=>time()]);
                }
                // 用户表动态数+1
                $bool2 = $user->_setInc(['id'=>$uid],'dynamic_num');
                // 后台统计表统计模特部分数据
                $info = $user->find(['id'=>$uid],'agent_id,type_id');
                $bool3 = true;
                if($info['agent_id']){
                    $bool3 = $statist->insertData(['agent_id'=>$info['agent_id'],'uid'=>$uid,'dynamics'=>1,'images'=>$count,'create_time'=>time()]);
                }
                if(!$id || !$bool || !$bool2 || !$bool3){
                    $dynamic->rollback();
                    throw new \LogicException('操作失败',1010);
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            $dynamic->commit();
            return json(self::formatSuccessResult());
        }
    }

    /**
     * @return \think\response\Json
     * 1.2图片上传接口
     */
    public function dynamicAdd_v1_2(){
        if(request()->isPost()){
            $post    = input('param.');
            $rsa     = new Rsa();
            $redis   = new Redis();
            $user    = new Bases('user');
            $dynamic = new Bases('dynamic');
            $type    = new Bases('dynamicImage');
            $statist = new Bases('statistics');
            /*// -----------------------
            $arr['token'] = $post['token'];
            $arr['price'] = $post['price'];
            $key = $rsa->generateKey($post['equipment']);
            $post['encrypt'] = $rsa->encrypt($key,$arr);*/
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);

                // 根据token获取用户id
                $uid = $redis->get($data['token']);
                $path = json_decode(htmlspecialchars_decode($post['image']),true); //得到图片
                if(!$path){
                    throw new \LogicException('图片不能为空',1037);
                }
                $count = count($path['ori']);
                $price = explode(',',$data['price']);
                $money = 0;
                $file_detection = new KamYellow();
                foreach($path['ori'] as $k=>$v){
                    $arr_kam_yellow_picture[$k]['labels'] = $file_detection->main($path['ori'][$k]);//鉴定结果
                    $arr_kam_yellow_picture[$k]['name'] = $v;
                    $arr_kam_yellow_picture[$k]['price'] = $price[$k];
                    $money += $price[$k];
                }
                foreach($path['thumb'] as $k=>$v){
                    $arr_kam_yellow_thumb[$k]['labels'] = $arr_kam_yellow_picture[$k]['labels'];
                    $arr_kam_yellow_thumb[$k]['name']  = $v;
                    $arr_kam_yellow_thumb[$k]['price'] = $price[$k];
                }
                //检测是否有异常图片
                $is_abnormal = $dynamic::IS_ABNORMAL_NO;  //默认无异常
                foreach($arr_kam_yellow_picture as $v){
                    if(($v['labels']['confidence'] == 1 || $v['labels']['confidence'] == 2) && $v['labels']['label'] == '色情图片'){
                        $is_abnormal = $dynamic::IS_ABNORMAL_YES;
                        break;
                    }
                }
                $arr_kam_yellow_picture = json_encode($arr_kam_yellow_picture,true); //所有原图鉴黄图片组成的json串
                $arr_kam_yellow_thumb   = json_encode($arr_kam_yellow_thumb,true);   //所有缩略图的鉴黄结果
                $dynamic->startTrans();// 开启事务
                $id   = true;
                $bool = true;
                if($post['type_id'] == $dynamic::DYNAMIC_TYPE_NORMAL){
                    $id   = $type->insertData(['images'=>$arr_kam_yellow_picture,'thumb'=>$arr_kam_yellow_thumb,'quantity'=>$count,'price'=>'0']);
                    $bool = $dynamic->insertData(['uid'=>$uid,'is_abnormal'=>$is_abnormal,'type_id'=>$post['type_id'],'image_id'=>$id,'descrip'=>$post['descrip'],'create_time'=>time()]);
                }
                if($post['type_id'] == $dynamic::DYNAMIC_TYPE_HONGBAO){
                    $id = $type->insertData(['images'=>$arr_kam_yellow_picture,'thumb'=>$arr_kam_yellow_thumb,'quantity'=>$count,'price'=>$money]);
                    $bool = $dynamic->insertData(['uid'=>$uid,'is_abnormal'=>$is_abnormal,'type_id'=>$post['type_id'],'image_id'=>$id,'descrip'=>$post['descrip'],'tag'=>$post['tag'],'create_time'=>time()]);
                }
                // 用户表动态数+1
                $bool2 = $user->_setInc(['id'=>$uid],'dynamic_num');
                // 后台统计表统计模特部分数据
                $info = $user->find(['id'=>$uid],'agent_id,type_id');
                $bool3 = true;
                if($info['agent_id']){
                    $bool3 = $statist->insertData(['agent_id'=>$info['agent_id'],'uid'=>$uid,'dynamics'=>1,'images'=>$count,'create_time'=>time()]);
                }
                if(!$id || !$bool || !$bool2 || !$bool3){
                    $dynamic->rollback();
                    throw new \LogicException('操作失败',1010);
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            $dynamic->commit();
            return json(self::formatSuccessResult());
        }
    }

    /**
     * 动态点赞
     * @return \think\response\Json
     */
    public function dynamicAgree(){
        if(request()->isPost()){
            $post   = input('param.');
            $rsa    = new Rsa();
            $redis  = new Redis();
            $agree  = new Bases('dynamicAgree');
            $dynamic = new Bases('dynamic');
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

                $info = $agree->find(['did'=>$post['id'],'agree_id'=>$uid]);
                if($info){
                    throw new \LogicException('您已赞过该动态',1023);
                }

                $dinfo = $dynamic->find(['id'=>$post['id'],'status'=>$dynamic::STATUS_ON,'is_delete'=>$dynamic::DELETE_NOT]);
                if(!$dinfo){
                    throw new \LogicException('该动态不存在',1025);
                }

                $agree->startTrans();

                // 赞
                $rs  = $agree->insertData(['did'=>$post['id'],'agree_id'=>$uid,'create_time'=>time()]);
                $rs1 = $dynamic->_setInc(['id'=>$post['id']],'agree_num');  // 点赞数+1

                if(!$rs || !$rs1){
                    $agree->rollback();
                    throw new \LogicException('操作失败',1010);
                }

            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            $agree->commit();
            return json(self::formatSuccessResult());
        }
    }

    /**
     * 动态置顶
     * @return \think\response\Json
     */
    public function dynamicTop(){
        if(request()->isPost()){
            $post   = input('param.');
            $rsa    = new Rsa();
            $redis  = new Redis();
            $dynamic = new Bases('dynamic');
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);

                $top = $dynamic->value(['id'=>$post['id']],'is_top');
                // 将置顶动态取消
                $bool   = $dynamic->setField(['uid'=>$uid],['is_top'=>$dynamic::DYNAMIC_TOP_NOT]);
                $bool1  = true;
                $is_top = 0;
                if(!$top){
                    // 新添加动态置顶
                    $bool1  = $dynamic->setField(['id'=>$post['id']],['is_top'=>$dynamic::DYNAMIC_TOP_YES]);
                    $is_top = 1;
                }
                if(false === $bool || !$bool1){
                    throw new \LogicException('操作失败',1010);
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult(['is_top'=>$is_top]));
        }
    }

    /**
     * 动态删除
     * @return \think\response\Json
     */
    public function dynamicDel(){
        if(request()->isPost()){
            $post     = input('param.');
            $rsa      = new Rsa();
            $redis    = new Redis();
            $user     = new Bases('user');
            $dynamic  = new Bases('dynamic');
            $position = new Bases('dynamicPosition');
            $statist  = new Bases('statistics');
            try{
                // 登录验证
                self::checkLogin();
                // 获取解密后的数据
                $data = $rsa->decrypt($post['equipment'],$post['encrypt']);
                // 根据token获取用户id
                $uid = $redis->get($data['token']);
                $position_id = $dynamic->value(['id'=>$post['did']],'position_id');
                $is_delete = $dynamic->value(['id'=>$post['did']],'is_delete');
                if($is_delete == Bases::DELETE_YES || $is_delete == Bases::DELETE_PLAT){
                    throw new \LogicException('该动态不存在',1025);
                }
                $bool = true;
                if($position_id < 999){
                    $bool = $position->setField(['id'=>$position_id],['status'=>0]);
                }
                $bool1 = $dynamic->updateData(['id'=>$post['did'],'is_delete'=>$dynamic::DELETE_YES,'position_id'=>999]);
                $bool2 = $user->_setDec(['id'=>$uid],'dynamic_num');
                // 后台统计表插入
                $info = $dynamic->joinOne([['user b','a.uid = b.id','left']],['a.id'=>$post['did']],'a.uid,b.agent_id');
                $bool3 = true;
                if($info['agent_id']){
                    $images = $dynamic->joinOne([['dynamic_image b','a.image_id = b.id','left']],['a.id'=>$post['did']],'b.quantity');
                    $bool3 = $statist->insertData(['agent_id'=>$info['agent_id'],'uid'=>$info['uid'],'dynamics'=>-1,'images'=>-$images['quantity'],'create_time'=>time()]);
                }
                if(!$bool || !$bool1 || !$bool2 || !$bool3){
                    throw new \LogicException('操作失败',1010);
                }
            } catch (\LogicException $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult());
        }
    }

    /**
     * 动态类型
     * @return \think\response\Json
     */
    public function dynamicType(){
        $type = new Bases('dynamicType');
        $list = $type->selectData([],'id,type_name');
        return json(self::formatSuccessResult($list));
    }

    /**
     * 动态标签
     * @return \think\response\Json
     */
    public function dynamicTag(){
        $config = new Bases('config');
        $tag = $config->value(['cname'=>'label'],'option');
        $tag = explode(',',$tag);
        return json(self::formatSuccessResult($tag));
    }
}