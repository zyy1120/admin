<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/3/13 0013
 * Time: 18:12
 */
namespace app\api\validate;
use think\Validate;

class Feedback extends Validate{
    //定义验证规则
    protected $rule = [
        'content' => 'require',
    ];

    //定义错误信息
    protected $message = [
        'content.require' => '请输入内容',
    ];

    //验证场景
    protected $scene = [
        'feedback'  =>  ['content'],
    ];
}
