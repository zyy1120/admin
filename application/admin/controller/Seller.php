<?php
namespace app\admin\controller;
use app\admin\model\School as Schools;
use think\Controller;
/**
* 商家模块
*
*/
class Seller extends Base
{
    public function index()
    {   
        $get    = input('param.');
        $seller = new Base('seller');
        $page   = isset($get['page']) ? $get['page'] : 1;
        $where['status'] = 0;
        $list  = $seller->page($where,'id asc',15,$get,'','',$page); 
        return view('index',['list'=>$list]);
    }
    
    public function edit($id)
    {
        $seller = new Base('seller');
        $data = input('param.');
        if(request()->isPost()){
            $result = $seller->updateData($data);
            if ($result) {
                return ajaxReturn(lang('action_success'), url('index'));
            }else{
                return ajaxReturn('编辑失败');
            }
        }else{
            $get  = input('param.');
            $where['id'] = $get['id'];
            $seller = new Base('seller');
            $list = $seller->find($where);
            $seller  = new Base('seller');
            $seller  = $seller->selectData();
            return view('edit',['list'=>$list,'seller'=>$seller]);
        }
    }

    public function create()
    {
            $data = input('post.');
        if (request()->isPost()){
            $seller = new Base('seller');
            $data = input('post.');
            $result = $seller->insertData($data);
            cache('DB_TREE_ARETYPE', null);   //删除栏目缓存
            if ($result){
                return ajaxReturn(lang('action_success'), url('index'));
            }else{
                return ajaxReturn('添加失败');
            }
        }else{
            $seller  = new Base('seller');
            $seller  = $seller->selectData();
            return view('create',['seller'=>$seller]);
        }
    }
    
    public function delete()
    {
        if (request()->isPost()){
            $id = input('id');
            if (isset($id) && !empty($id)){
                $where = ['id' => $id,'status' => 1];
                $seller = new Base('seller');
                $result = $seller->updateData($where);
                cache('DB_TREE_ARETYPE', null);   //删除栏目缓存
                if ($result){
                    return ajaxReturn(lang('action_success'), url('index'));
                }else{
                    return ajaxReturn('删除失败！');
                }
            }
        }
    }

}