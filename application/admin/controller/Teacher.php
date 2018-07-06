<?php
namespace app\admin\controller;
use app\admin\model\School as Schools;
use think\Controller;
/**
* 教练模块
*
*/
class Teacher extends Base
{
    public function index()
    {   
        $get = input('param.');
        $teacher = new Base('teacher');
        $page  = isset($get['page']) ? $get['page'] : 1;
        $where['status'] = 0;
        $list  = $teacher->page($where,'id asc',15,$get,'','',$page); 
        return view('index',['list'=>$list]);
    }
    
    public function edit($id)
    {
        $teacher = new Base('teacher');
        $data = input('param.');
        if(request()->isPost()){
            if (!preg_match('/^([\xe4-\xe9][\x80-\xbf]{2}){2,5}$/', $data['teacher_name'])) {
                return ajaxReturn('请输入2-5位的中文姓名');
            }
            if(!preg_match("/^1[34578]{1}\d{9}$/",$data['teacher_phone'])){
                return ajaxReturn('请输入正确的手机号码');
            }
            $result = $teacher->updateData($data);
            if ($result) {
                return ajaxReturn(lang('action_success'), url('index'));
            }else{
                return ajaxReturn('编辑失败');
            }
        }else{
            $get  = input('param.');
            $where['id'] = $get['id'];
            $teacher = new Base('teacher');
            $list = $teacher->find($where);
            $school  = new Base('school');
            $school  = $school->selectData();
            return view('edit',['list'=>$list,'school'=>$school]);
        }
    }

    public function create()
    {
            $data = input('post.');
        if (request()->isPost()){
            $teacher = new Base('teacher');
            $data = input('post.');
            if (!preg_match('/^([\xe4-\xe9][\x80-\xbf]{2}){2,5}$/', $data['teacher_name'])) {
                    return ajaxReturn('请输入2-5位的中文姓名');
                }
            if(!preg_match("/^1[34578]{1}\d{9}$/",$data['teacher_phone'])){
                return ajaxReturn('请输入正确的手机号码');
            }
            $result = $teacher->insertData($data);
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
                $teacher = new Base('teacher');
                $result = $teacher->updateData($where);
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