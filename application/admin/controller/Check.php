<?php
namespace app\admin\controller;
use app\admin\model\School as Schools;
use think\Controller;
/**
* 检车模块
*
*/
class Check extends Base
{
    //个人检车
    public function inspect()
    {   
        $get = input('param.');
        $inspect = new Base('inspect');
        $page  = isset($get['page']) ? $get['page'] : 1;
        $where['status'] = 0;
        $list  = $inspect->page($where,'id asc',15,$get,'','',$page); 
        return view('inspect',['list'=>$list]);
    }
    
    public function inspect_edit($id)
    {
        $inspect = new Base('inspect');
        $data = input('post.');
        if(request()->isPost()){
            if ($data['inspect_type'] == 1) {
                if (empty($data['address'])) {
                    return ajaxReturn('上门取车请填写地址！');
                }
            }
            if ($data['is_invoice'] == 1) {
                if ($data['invoice_type'] == 0) {
                    return ajaxReturn('请选择发票类型');
                }
                if ($data['invoice_name'] == 1) {
                   if (empty($data['invoice_name']) || empty($data['invoice_number'])) {
                    return ajaxReturn('请将发票信息填写完整！');
                    }
                }
            }
            $result = $inspect->updateData($data);
            if ($result) {
                return ajaxReturn(lang('action_success'), url('index'));
            }else{
                return ajaxReturn('编辑失败');
            }
        }else{
            $get  = input('param.');
            $inspect = new Base('inspect');
            $where['id'] = $get['id'];
            $inspect = new Base('inspect');
            $list = $inspect->find($where);
            return view('inspect_edit',['list'=>$list]);
        }
    }

    public function edit($id)
    {
        $team = new Base('team');
        $data = input('param.');
        if(request()->isPost()){
            $result = $team->updateData($data);
            if ($result) {
                return ajaxReturn(lang('action_success'), url('index'));
            }else{
                return ajaxReturn('编辑失败');
            }
        }else{
            $get  = input('param.');
            $where['id'] = $get['id'];
            $team = new Base('team');
            $list = $team->find($where);
            return view('edit',['list'=>$list]);
        }
    }

    public function team()
    {   
        $get = input('param.');
        $team = new Base('team');
        $page  = isset($get['page']) ? $get['page'] : 1;
        $where['status'] = 0;
        $list  = $team->page($where,'id asc',15,$get,'','',$page); 
        return view('team',['list'=>$list]);
    }

    
    public function delete()
    {
        if (request()->isPost()){
            $id = input('id');
            if (isset($id) && !empty($id)){
                $where = ['id' => $id,'status' => 1];
                $team = new Base('team');
                $result = $team->updateData($where);
                cache('DB_TREE_ARETYPE', null);   //删除栏目缓存
                if ($result){
                    return ajaxReturn(lang('action_success'), url('index'));
                }else{
                    return ajaxReturn('删除失败！');
                }
            }
        }
    }

    public function inspect_delete()
    {
        if (request()->isPost()){
            $id = input('id');
            if (isset($id) && !empty($id)){
                $where = ['id' => $id,'status' => 1];
                $inspect = new Base('inspect');
                $result = $inspect->updateData($where);
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