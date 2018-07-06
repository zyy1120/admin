<?php
namespace app\admin\controller;
use app\admin\model\School as Schools;
use think\Controller;
/**
* 驾校模块
*
*/
class School extends Base
{
    public function index()
    {   
        $get = input('param.');
        $school = new Base('school');
        $page  = isset($get['page']) ? $get['page'] : 1;
        $where['status'] = 0;
        $list  = $school->page($where,'id asc',15,$get,'','',$page); 
        return view('index',['list'=>$list]);
    }
    
    public function edit($id)
    {
        $school = new Base('school');
        $data = input('param.');
        if(request()->isPost()){
            $result = $school->updateData($data);
            if ($result) {
                return ajaxReturn(lang('action_success'), url('index'));
            }else{
                return ajaxReturn('编辑失败');
            }
        }else{
            $get  = input('param.');
            $where['id'] = $get['id'];
            $school = new Base('school');
            $list = $school->find($where);
            $school  = new Base('school');
            $school  = $school->selectData();
            return view('edit',['list'=>$list,'school'=>$school]);
        }
    }

    public function create()
    {
            $data = input('post.');
        if (request()->isPost()){
            $school = new Base('school');
            $data = input('post.');
            if (!preg_match('/^([\xe4-\xe9][\x80-\xbf]{2}){2,5}$/', $data['school_name'])) {
                    return ajaxReturn('请输入2-5位的中文姓名');
                }
            if(!preg_match("/^1[34578]{1}\d{9}$/",$data['school_phone'])){
                return ajaxReturn('请输入正确的手机号码');
            }
            $result = $school->insertData($data);
            cache('DB_TREE_ARETYPE', null);   //删除栏目缓存
            if ($result){
                return ajaxReturn(lang('action_success'), url('index'));
            }else{
                return ajaxReturn('添加失败');
            }
        }else{
            $school  = new Base('school');
            $school  = $school->selectData();
            return view('create',['school'=>$school]);
        }
    }
    
    public function delete()
    {
        if (request()->isPost()){
            $id = input('id');
            if (isset($id) && !empty($id)){
                $where = ['id' => $id,'status' => 1];
                $school = new Base('school');
                $result = $school->updateData($where);
                cache('DB_TREE_ARETYPE', null);   //删除栏目缓存
                if ($result){
                    return ajaxReturn(lang('action_success'), url('index'));
                }else{
                    return ajaxReturn('删除失败！');
                }
            }
        }
    }

    /**
    * 价目表模块
    *
    */
    public function price()
    {
        if (request()->isGet()){
            $data = input('param.');
            $school = new Base('school');
            $price = new Base('price');
            $where['id'] = $data['id'];
            $price_id = $school->find($where,'price_id');
            $page  = isset($get['page']) ? $get['page'] : 1;
            $where['status'] = 0;
            $map = ['id'=>['in',$price_id['price_id']]];
            $list  = $price->page($map,'id asc',15,$data,'','',$page); 
            return view('price',['list'=>$list]);
        }
    }

    public function create_price()
    {
        if (request()->isPost()){
            $price = new Base('price');
            $data = input('post.');
            $datas['money']     = $data['money'];
            $datas['card_type'] = $data['card_type'];
            $datas['content']   = $data['content'];
            $datas['status']    = $data['status'];
            $result = $price->insertData($datas);
            cache('DB_TREE_ARETYPE', null);   //删除栏目缓存
            if ($result){
                $school = new Base('school');
                $where['id'] = $data['school_id'] ;
                $price_id = $school->find($where,'price_id');
                $price_id = $price_id['price_id'].','.$result;
                $school->setField(['id'=> $data['school_id']],['price_id'=>$price_id ]);
                return ajaxReturn(lang('action_success'), url('index'));
            }else{
                return ajaxReturn('添加失败');
            }
        }else{
            return view('create_price');
        }
    }
    
    public function delete_price()
    {
        if (request()->isPost()){
            $id = input('id');
            if (isset($id) && !empty($id)){
                $where = [ 'id' =>  $id,'status' => 1 ];
                $price = new Base('price');
                $result = $price->deleteByWhere($where);
                cache('DB_TREE_ARETYPE', null);   //删除栏目缓存
                if ($result){
                    return ajaxReturn(lang('action_success'), url('index'));
                }else{
                    return ajaxReturn('删除失败！');
                }
            }
        }
    }

    public function edit_price($id)
    {
        $price = new Base('price');
        $data = input('param.');
        if(request()->isPost()){
            $result = $price->updateData($data);
            if ($result) {
                return ajaxReturn(lang('action_success'), url('index'));
            }else{
                return ajaxReturn('编辑失败');
            }
        }else{
            $get  = input('param.');
            $where['id'] = $get['id'];
            $price = new Base('price');
            $list = $price->find($where);
            return view('edit_price',['list'=>$list]);
        }
    }
}