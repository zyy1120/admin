<?php
namespace app\api\controller;
use app\service\ClientResponseHandler;
use app\service\Utils;
use think\config;
use app\models\Bases;
// use app\service\Redis;
// use app\service\Rsa;

class Weifunotify extends Base {
    /**
     * 提供给威富通的回调方法
     */
    public function callback(){
        $xml = file_get_contents('php://input');
        $resHandler    = new  ClientResponseHandler();
        $Utils         = new  Utils();
        $config        = new  config();
        $key           = Config('scancode.key');
        $resHandler->setContent($xml);
        $resHandler->setKey($key);
        if($resHandler->isTenpaySign()){
            if($resHandler->getParameter('status') == 0 && $resHandler->getParameter('result_code') == 0){
        //echo $resHandler->getParameter('status');
        // 11;
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