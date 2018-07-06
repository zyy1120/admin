<?php
namespace app\admin\controller;
use app\admin\model\School as Schools;
use think\Controller;
/**
* 商家模块
*
*/
class Vehicle extends Base
{
    public function index()
    {   
        $get    = input('param.');
        $vehicle = new Base('vehicle');
        $join = [
            ['seller b','a.business_id=b.id','left'],
        ];
        $field = "a.*,b.seller_name";
        $page   = isset($get['page']) ? $get['page'] : 1;
        $list  = $vehicle->page($where,'a.id asc',15,$get,$join,$field,$page); 
        return view('index',['list'=>$list]);
    }
    
    public function edit($id)
    {
        $vehicle = new Base('vehicle');
        $data = input('param.');
        if(request()->isPost()){
            if ($data['is_group'] == 1) {
                if ( empty($data['number'])) {
                    return ajaxReturn('团购产品的应参团人数不能为空！');
                }
                if ( empty($data['endtime'])) {
                    return ajaxReturn('团购产品的参团剩余时间不能为空！');
                }
            }
            $result = $vehicle->updateData($data);
            if ($result) {
                return ajaxReturn(lang('action_success'), url('index'));
            }else{
                return ajaxReturn('编辑失败');
            }
        }else{
            $get  = input('param.');
            $where['id'] = $get['id'];
            $vehicle = new Base('vehicle');
            $list = $vehicle->find($where);
            $seller  = new Base('seller');
            $seller  = $seller->selectData();
            return view('edit',['list'=>$list,'seller'=>$seller]);
        }
    }

    public function create()
    {
        if (request()->isPost()){
            $vehicle = new Base('vehicle');
            $data = input('post.');
            if ($data['is_group'] == 1) {
                if ( empty($data['number'])) {
                    return ajaxReturn('团购产品的应参团人数不能为空！');
                }
                if ( empty($data['endtime'])) {
                    return ajaxReturn('团购产品的参团剩余时间不能为空！');
                }
            }
            $result = $vehicle->insertData($data);
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
                $where = ['id' => $id];
                $vehicle = new Base('vehicle');
                $result = $vehicle->deleteByWhere($where);
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
    * 团购列表
    */
    public function purchase()
    {
        $get    = input('param.');
        $vehicle = new Base('vehicle');
        $join = [
            ['student b','a.id = b.is_purchase','left'],
        ];
        $field = "a.vehicle_type,b.name,b.iphone,a.number,a.current";
        $page   = isset($get['page']) ? $get['page'] : 1;
        $list  = $vehicle->page($where,'a.id asc',15,$get,$join,$field,$page); 
        return view('purchase',['list'=>$list]);

    }

}