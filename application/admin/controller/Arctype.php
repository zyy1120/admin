<?php
namespace app\admin\controller;

use think\Controller;
use app\admin\model\Arctype as Arctypes;
use app\admin\model\ArctypeMod;

class Arctype extends Common
{
    private $cModel;   //当前控制器关联模型
    
    public function _initialize()
    {
        parent::_initialize();
        $this->cModel = new Arctypes;   //别名：避免与控制名冲突
    }
    
    public function index()
    {
        $dataList = $this->cModel->treeList();
        foreach ($dataList as $k=>$v){
            if ($v['arctypeMod']['mod'] == 'addonjump' && !empty($v['jumplink'])){
                $dataList[$k]['typelink'] = $v['jumplink'];
            }else{
                $dataList[$k]['typelink'] = url('@category/'.$v['dirs']);
            }
        }
        $this->assign('dataList', $dataList);
        return $this->fetch();
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
        if (request()->isPost()){
            $data = input('post.');
            if (count($data) == 2){
                foreach ($data as $k =>$v){
                    $fv = $k!='id' ? $k : '';
                }
                $result = $this->cModel->validate(CONTROLLER_NAME.'.'.$fv)->allowField(true)->save($data, $data['id']);
            }else{
                $result = $this->cModel->validate(CONTROLLER_NAME.'.edit')->allowField(true)->save($data, $data['id']);
            }
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
            
            $data = $this->cModel->get($id);
            $this->assign('data', $data);
            return $this->fetch();
        }
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