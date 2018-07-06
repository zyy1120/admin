<?php
namespace app\admin\controller;
use app\admin\model\School as Schools;
use think\Controller;
/**
* 会员模块
*
*/
class Student extends Base
{
    public function index()
    {   
        $where = [];
        if (input('get.search')){
            $where['a.iphone'] = ['like', '%'.input('get.search').'%'];
        }
        $get = input('param.');
        $studentModel = new Base('student');
        $join = [
            ['school c','a.school_id=c.id','left'],
            ['teacher b' ,'a.teacher_id = b.id','left'],
            ['price e' ,'a.price_id = e.id','left'],
        ];
        $field = "a.id,a.name,a.iphone,a.integral,a.license,e.card_type,c.school_name,b.teacher_name";
        $where['a.status'] = ['=',0];
        $page  = isset($get['page']) ? $get['page'] : 1;
        $list  = $studentModel->page($where,'a.id asc',15,$get,$join,$field,$page); 
         return view('index',['list'=>$list]);
    }
    
    public function edit($id)
    {
        $student = new Base('student');
        $school  = new Base('school');
        $teacher = new Base('teacher');
        $course  = new Base('course');
        $data = input('param.');
        if(request()->isPost()){
            if ($data['actions'] == 'password'){    //修改密码
                if ($data['password'] != $data['repassword']) {
                    return ajaxReturn('两次密码不一致');
                }
                $where['id'] = $data['id'];
                $list = $student->find($where,'password');
                if (md5($data['oldpassword']) != $list['password']) {
                    return ajaxReturn('旧密码输入不正确');
                }
                $datas['id'] = $data['id'];
                $datas['password'] = md5($data['password']);
                $result = $student->updateData($datas);
                if ($result) {
                    return ajaxReturn(lang('action_success'), url('index'));
                }else{
                    return ajaxReturn('编辑失败');
                }
            }elseif ($data['actions'] == 'member'){   //修改个人资料
                $list = $student->find($where);
                unset($data['actions']);
                if (!preg_match('/^([\xe4-\xe9][\x80-\xbf]{2}){2,5}$/', $data['name'])) {
                    return ajaxReturn('请输入2-5位的中文姓名');
                }
                if(!preg_match("/^1[34578]{1}\d{9}$/",$data['iphone'])){
                    return ajaxReturn('请输入正确的手机号码');
                }
                if ($list['school_id'] != $data['school_id'] || $list['teacher_id'] != $data['teacher_id']) {
                    $where = ['school_id' => $data['school_id'],'id' => $data['teacher_id']];
                    $student = $teacher->find($where);
                    if(empty($student)){
                        return ajaxReturn('教练和驾校应是保持对应');
                    }
                }
                $data['edit_time'] = time();
                $result = $student->updateData($data);
                if ($result) {
                    return ajaxReturn(lang('action_success'), url('index'));
                }else{
                    return ajaxReturn('编辑失败');
                }
            }
        }else{
            $get  = input('param.');
            $studentModel = new Base('student');
            $join = [
                ['school c','a.school_id=c.id','left'],
                ['teacher b' ,'a.teacher_id = b.id','left'],
            ];
            $field = "a.id,a.name,a.iphone,a.integral,a.status,a.idnumber,a.price_id,a.license,c.id cid,c.school_name,b.id bid,b.teacher_name";
            $school  = $school->selectData();
            $teacher = $teacher->selectData();
            $course  = $course->selectData();
            $where['a.id'] = $get['id'];
            $list= $student->joinOne($join,$where,$field);
            return view('edit',['list'=>$list,'school'=>$school,'teacher'=>$teacher,'course'=>$course]);
        }
    }

    public function create()
    {
        if (request()->isPost()){
            $data = input('post.');
            $student = new Base('student');
            if (!preg_match('/^([\xe4-\xe9][\x80-\xbf]{2}){2,5}$/', $data['name'])) {
                return ajaxReturn('请输入2-5位的中文姓名');
            }
            if(!preg_match("/^1[34578]{1}\d{9}$/",$data['iphone'])){
                return ajaxReturn('请输入正确的手机号码');
            }
            $where['iphone'] = $data['iphone'];
            $iphone = $student->find($where,'id');
            if ($iphone) {
                return ajaxReturn('该手机号码已注册');
            }
            $data['password'] = md5($data['password']); 
            $data['add_time'] = time();
            $data['edit_time'] = time();
            $data['code'] = rand('100000','999999');
            $where['code'] = $data['code'];
            $code = $student->find($where,'id');
            if ($code) {
               $data['code'] = rand('100000','999999');
            }
            $result = $student->insertData($data);
            cache('DB_TREE_ARETYPE', null);   //删除栏目缓存
            if ($result){
                return ajaxReturn(lang('action_success'), url('index'));
            }else{
                return ajaxReturn('添加失败');
            }
        }else{
            $student = new Base('student');
            $school  = new Base('school');
            $teacher = new Base('teacher');
            $course  = new Base('course');
            $school  = $school->selectData();
            $teacher = $teacher->selectData();
            $course  = $course->selectData();
            return view('create',['school'=>$school,'teacher'=>$teacher,'course'=>$course]);
        }
    }
    
    public function delete()
    {
        if (request()->isPost()){
            $id = input('id');
            if (isset($id) && !empty($id)){
                $where = [ 'id' =>  $id,'status' => 1 ];
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