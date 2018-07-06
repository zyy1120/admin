<?php
namespace app\admin\controller;
use app\admin\model\School as Schools;
use think\Controller;
/**
* 积分兑换模块
*
*/
class Exchange extends Base
{
    public function index()
    {   
        $get = input('param.');
        $exchange = new Base('exchange');
        $join = [
            ['student b' ,'a.member_id = b.id','left'],
        ];
        $field = "a.*,b.name";
        $page  = isset($get['page']) ? $get['page'] : 1;
        $list  = $exchange->page($where,'a.id asc',15,$get,$join,$field,$page); 
         return view('index',['list'=>$list]);
    }
    
}