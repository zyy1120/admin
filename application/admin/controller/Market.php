<?php
namespace app\admin\controller;
use app\admin\model\School as Schools;
use think\Controller;
/**
* 后市场模块
*
*/
class Market extends Base
{
    public function index()
    {   
        $get    = input('param.');
        $seller = new Base('seller');
        $page   = isset($get['page']) ? $get['page'] : 1;
        $where['status'] = 0;
        $where['seller_type'] = 1 ;
        $filed = 'id,seller_name,seller_phone,seller_address,seller_img,is_exchange';
        $list  = $seller->page($where,'id asc',15,$get,'',$filed,$page); 
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

    //商家商品列表
    public function product_index()
    {   
        $get    = input('param.');
        $product = new Base('product');
        $page   = isset($get['page']) ? $get['page'] : 1;
        $where['status'] = 0;
        $where['seller_id'] = $get['id'];
        $list  = $product->page($where,'id asc',15,$get,'',$filed,$page); 
        return view('product_index',['list'=>$list]);
    }

    public function product_edit($id)
    {
        $product = new Base('product');
        $data = input('param.');
        if(request()->isPost()){
            $result = $product->updateData($data);
            if ($result) {
                return ajaxReturn(lang('action_success'), url('index'));
            }else{
                return ajaxReturn('编辑失败');
            }
        }else{
            $get  = input('param.');
            $where['id'] = $get['id'];
            $product = new Base('product');
            $list = $product->find($where);
            return view('product_edit',['list'=>$list]);
        }
    }

    public function product_create()
    {
            $data = input('post.');
        if (request()->isPost()){
            $product = new Base('product');
            $data = input('post.');
            $result = $product->insertData($data);
            cache('DB_TREE_ARETYPE', null);   //删除栏目缓存
            if ($result){
                return ajaxReturn(lang('action_success'), url('index'));
            }else{
                return ajaxReturn('添加失败');
            }
        }else{
            return view('product_create');
        }
    }
    
    public function product_delete()
    {
        if (request()->isPost()){
            $id = input('param.');
            if (isset($id) && !empty($id)){
                $where = ['id' => $id['id'],'status' => 1];
                $product = new Base('product');
                $result = $product->updateData($where);
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