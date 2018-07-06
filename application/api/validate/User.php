<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/3/13 0013
 * Time: 18:12
 */
namespace app\api\validate;
use think\Validate;

class User extends Validate{
    //定义验证规则
    protected $rule = [
        'username' => 'require|number|length:11',
        'password' => 'require|length:6,18',
        'nickname' => 'require|max:24|unique:user',
        'sex'      => 'require',
    ];

    //定义错误信息
    protected $message = [
        'username.require' => '请输入手机号',
        'username.number'  => '手机号必须为数字',
        'username.length'  => '手机号必须11数字',
        'password.require' => '密码不能为空',
        'password.length'  => '密码长度为6-18位',
        'nickname.require' => '昵称不能为空',
        'nickname.max'     => '昵称不能超过24个字符',
        'nickname.unique'  => '该昵称已存在',
        'sex.require'      => '性别不能为空',
    ];

    //验证场景
    protected $scene = [
        'login'  =>  ['username','password'],
        'forgetPassword'  =>  ['username','password'],
        'registerStepOne'  =>  ['username','password'],
        'registerStepTwo'  =>  ['nickname','sex'],
        'updateNickname'  =>  ['nickname'],
    ];
}
