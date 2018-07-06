<?php
/**
 * Created by PhpStorm.
 * User: zhangbing
 * Date: 2017/3/13 0013
 * Time: 18:12
 */
namespace app\api\validate;
use think\Validate;

class Bank extends Validate{
    //定义验证规则
    protected $rule = [
        'name'         => 'require',
        'mobile'       => 'require|length:11',
        'id_card'      => 'require|length:18',
        'bank_account' => 'require|number',
        'bank_name'    => 'require',
        'bank_address' => 'require',
        'bank_branch'  => 'require',
    ];

    //定义错误信息
    protected $message = [
        'name.require'     => '请输入真实姓名',
        'mobile.require'   => '请输入手机号',
        'mobile.number'    => '手机号必须11数字',
        'id_card.require'  => '请输入身份证',
        'id_card.length'   => '身份证长度为18位',
        'bank_account.require' => '请输入银行卡号',
        'bank_account.number'  => '银行卡号必须位数字',
        'bank_name.require'    => '请输入银行名称',
        'bank_address.require' => '请输入开户行所在地',
        'bank_branch.require'  => '请输入开户行支行',
    ];

    //验证场景
    protected $scene = [
        'bankAdd'  =>  ['name','mobile','id_card','bank_account','bank_name','bank_address','bank_branch',],
    ];
}
