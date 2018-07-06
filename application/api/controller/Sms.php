<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/3/23 0023
 * Time: 15:28
 */
namespace app\api\controller;

class Sms extends Base{
    /**
     * 获取验证码
     */
    public function sendCode(){
        if(request()->isPost()){
            $post = input('param.');
            $sms  = new \app\service\Sms();
            try{
                // 发送验证码
                $sms->sendCode($post);

            } catch (\Exception $e){
                return json(self::formatResult($e->getCode(),$e->getMessage()));
            }
            return json(self::formatSuccessResult());
        }
    }
}