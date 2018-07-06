<?php
namespace app\api\controller;
use app\models\Bases;
use app\service\ClientResponseHandler;
use think\config;
use app\service\Utils;

class Wxnotify extends Base {
    /**
     * 异步通知方法
     */
    public function callback(){
        $resHandler = new ClientResponseHandler();
        $Utils      = new Utils;
        $xml        = file_get_contents('php://input');
        $resHandler->setContent($xml);
        $resHandler->setKey(Config('wx.key'));
        if($resHandler->isTenpaySign()){
            if($resHandler->getParameter('status') == 0 && $resHandler->getParameter('result_code') == 0){   
                $Utils::dataRecodes('接口回调收到通知参数',$resHandler->getAllParameters());
                echo 'success';
                exit();
            }else{
                echo 'failure1';
                exit();
            }
        }else{
            echo 'failure2';
        }
    }
}