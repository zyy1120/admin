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
        $get = input('param.');
        $studentModel = new Base('student');
        $join = [
            ['school c','a.school_id=c.id','left'],
            ['teacher b' ,'a.teacher_id = b.id','left'],
            // ['course d' ,'a.course_id = d.id','left'],
        ];
        $field = "a.id,a.name,a.iphone,a.integral,a.license,c.school_name,b.teacher_name";
        $where['a.status'] = ['=',0];
        $page  = isset($get['page']) ? $get['page'] : 1;
        $list  = $studentModel->page('','a.id asc',15,$get,$join,$field,$page); 
         return view('index',['list'=>$list]);
    }
    
    public function create()
    {
        if (request()->isPost()){
            $data = input('post.');
            $result = $this->cModel->validate(CONTROLLER_NAME.'.add')->allowField(true)->save($data);
            cache('DB_TREE_ARETYPE', null);   //删除栏目缓存
            if ($result){
                return ajaxReturn(lang('action_success'), url('index'));
            }else{
                return ajaxReturn($this->cModel->getError());
            }
        }else{
            $arctypeList = $this->cModel->treeList();
            $this->assign('arctypeList', $arctypeList);
            
            $amModel = new ArctypeMod();
            $modList = $amModel->where(['status' => 1])->order('sorts ASC,id ASC')->select();
            $this->assign('modList', $modList);
            return $this->fetch('edit');
        }
    }
    
    public function edit($id)
    {
        $user = new Base('student');
        if(request()->isGet()){
            $post = input('param.');
            try{
                $len = strlen(trim($post['nickname']));
                if($len >24 || $len < 4){
                    throw new \LogicException('昵称长度为4-24个字符',1032);
                }
                // 添加场景验证
                $validate = $this->validate($post,'User.userSave');
                if(true !== $validate){
                    throw new \LogicException($validate,1000);
                }
                $bool = $user->updateData($post);
                if(!$bool){
                    throw new \Exception('操作失败',1010);
                }
            } catch (\Exception $e){
                return self::formatResult($e->getCode(),$e->getMessage());
            }
            return self::formatSuccessResult();
        }
        $get  = input('param.');
        
        return view('userSave',['list'=>$list,'info'=>$info]);
    }
    
    public function delete()
    {
        if (request()->isPost()){
            $id = input('id');
            if (isset($id) && !empty($id)){
                $id_arr = explode(',', $id);
                $where = [ 'id' => ['in', $id_arr] ];
                $result = $this->cModel->where($where)->delete();
                cache('DB_TREE_ARETYPE', null);   //删除栏目缓存
                if ($result){
                    return ajaxReturn(lang('action_success'), url('index'));
                }else{
                    return ajaxReturn($this->cModel->getError());
                }
            }
        }
    }
}