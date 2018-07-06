<?php
namespace app\admin\controller;
use app\admin\model\School as Schools;
use think\Controller;
/**
* 车险模块
*
*/
class Insurance extends Base
{
    public function index()
    {   
        $get    = input('param.');
        $student = new Base('student');
        $where['is_insurance'] = 1;
        $where['insurance_status'] = 0;
        $field = 'id,name,iphone,license';
        $page   = isset($get['page']) ? $get['page'] : 1;
        $list  = $student->page($where,'a.id asc',15,$get,$join,$field,$page); 
        return view('index',['list'=>$list]);
    }
    
    public function edit($id)
    {
        $student = new Base('student');
        $data = input('param.');
        if(request()->isPost()){
            $result = $student->updateData($data);
            if ($result) {
                return ajaxReturn(lang('action_success'), url('index'));
            }else{
                return ajaxReturn('编辑失败');
            }
        }else{
            $where['id'] = $data['id'];
            $student = new Base('student');
            $list = $student->find($where);
            return view('edit',['list'=>$list]);
        }
    }
    
    public function delete()
    {
        if (request()->isPost()){
            $id = input('id');
            if (isset($id) && !empty($id)){
                $where = ['id' => $id,'insurance_status' => 1 ];
                $student = new Base('student');
                $result = $student->updateData($where);
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