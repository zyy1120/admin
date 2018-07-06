<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/3/23 0023
 * Time: 11:11
 */
namespace app\api\controller;
use app\models\Bases;

class Test{
    public function test(){
        $user = new Bases('user');
        $account = new Bases('account');
        $dynamic = new Bases('dynamic');
        $image = new Bases('dynamicImage');
        $invite = new Bases('invite');
        $follow = new Bases('follow');
        $str = '15255591951,15255591952,15255591953,15255591954,15255591955,15255591956,15255591957,15255591958,15255591959,15255591960,15255591961,15255591962,15255591963,15255591964,15255591965,15255591966,15255591967,15255591968,15255591969,15255591970,15255591971,15255591972,15255591973,15255591974,15255591975,15255591976,15255591977,15021713000,15021713001,15021713002,15021713003,15021713004,15021713005,15021713006,15021713007,15021713008,15021713009,15021713010,15021713011,15021713012,15021713013,15021713014,15021713015,15021713016,15021713017,15021713018,15021713019,13807210000,13807210001,13807210002,13807210003,13807210004,13807210005,13807210006,13807210007,13807210008,13807210009,13807210010,13807210011,13807210012,13807210013,13807210014,13807210015,13807210016,13807210017,13807210018,13807210019,13807210020,13807210021,13807210022,13807210023,13807210024,13807210025,18090530001,18090530002,18090530003,18090530004,18090530005,18090530006,18090530007,18090530008,18090530009,18090530010,18090530011,18090530012,18090530013,18090530014,18090530015,18090530016,18090530017,18090530018,18090530019,18090530020,13816518600';;
        $ulist = $user->selectData(['username'=>['in',$str]],'id');
        if(!$ulist){
            echo '用户不存在';exit;
        }
        foreach($ulist as $v){
            $ids[] = $v['id'];
        }
        $ids = implode(',',$ids);
        $dlist = $dynamic->selectData(['uid'=>['in',$ids]]);
        if(!$dlist){
            echo '动态不存在';exit;
        }
        foreach ($dlist as $v){
            $did[] = $v['id'];
            $image_id[] = $v['image_id'];
        }
        $did = implode(',',$did);
        $image_id = implode(',',$image_id);
        $user->startTrans();
        $bool = $account->deleteByWhere(['uid'=>['in',$ids]]);
        $bool1 = $image->deleteByWhere(['id'=>['in',$image_id]]);
        $bool2 = $dynamic->deleteByWhere(['id'=>['in',$did]]);
        $bool3 = $invite->deleteByWhere(['invitee_id'=>['in',$ids]]);
        $bool4 = $user->deleteByWhere(['id'=>['in',$ids]]);


        if(!$bool || !$bool1 || !$bool2 || !$bool3 || !$bool4){
            $user->rollback();
            echo '操作失败';exit;
        }
        $user->commit();
        echo '操作成功';
    }
}