<?php
namespace app\admin\controller;
use think\Controller;
use think\Request;
use think\Model;
use think\Db;
use think\Lang;
use app\admin\model\AuthRule;
use expand\Auth;
use app\admin\model\Config;
use app\admin\controller\Login;
use app\admin\model\TokenUser;

class Base extends Controller{


    protected $tableName = '';

    public function __construct($tableName=''){
    	$this->restLogin();
        $userId = session('userId');
        define('UID', $userId);   //设置登陆用户ID常量
        
        define('MODULE_NAME', request()->module());
        define('CONTROLLER_NAME', request()->controller());
        define('ACTION_NAME', request()->action());
        
        $auth = new Auth();
        if (!$auth->check(CONTROLLER_NAME.'/'.ACTION_NAME, UID)){
            $this->error(lang('auth_no_exist'), url('Login/index'));
        }
        $this->tableName = $tableName;
    }

        private function restLogin()
    {
        $login = new Login();
        $userId = session('userId');
        if (empty($userId)){   //未登录
            $login->loginOut();
        }
        $config = new Config();
        $login_time = $config->where(['type'=>'system', 'k'=>'login_time'])->value('v');
        $now_token = session('user_token');   //当前token
        $tkModel = new TokenUser();
        $db_token = $tkModel->where(['uid'=>$userId, 'type'=>'1'])->find();   //数据库token
        if ($db_token['token'] != $now_token){   //其他地方登录
            $this->loginBox(lang('login_other'));
        }else{
            if ($db_token['token_time'] < time()){   //登录超时
                $this->loginBox(lang('login_timeout'));
            }else{
                $token_time = time() + $login_time;
                $data = ['token_time' => $token_time];
                $tkModel->where(['uid'=>$userId, 'type'=>'1'])->update($data);
            }
        }
        return;
    }

          /**
     * ajax请求返回成功信息
     * @param  string $data
     * @return array
     */
    public static function formatSuccessResult($data = null){
        return self::formatResult(0, '操作成功', $data);
    }
    /**
     * ajax请求返回错误信息
     * @param int $code
     * @param string $errorMsg
     * @param string $data
     * @return array
     */
    public static function formatResult($code, $errorMsg, $data = null){
        return array('code' => $code,'msg' => $errorMsg,'data'=>$data);
    }

    /**
     * 查询一条数据
     * @param array $where
     * @param string $field
     * @return array|false|mixed|\PDOStatement|string|Model
     */
    public function find($where = [],$field = '*'){
        return Db::name($this->tableName)->field($field)->where($where)->find();
    }

    /**
     * 返回某个字段的值
     * @param string $where
     * @param mixed|null $field
     * @return mixed
     */
    public function value($where,$field){
        return Db::name($this->tableName)->where($where)->value($field);
    }

    /**
     * 更新某个字段的值
     * @param array|string $where
     * @param mixed|string $data
     * @return int
     */
    public function setField($where,$data){
        return Db::name($this->tableName)->where($where)->setField($data);
    }

    /**
     * 连表查询单条信息
     * @param $join
     * @param array $where
     * @param string $field
     * @return array|false|\PDOStatement|string|Model
     */
    public function joinOne($join,$where = [],$field = ''){
        return Db::name($this->tableName)->alias('a')->field($field)->join($join)->where($where)->find();
    }

    /**
     * 统计
     * @param string $where
     * @param $field
     * @return float|int
     */
    public function sum($where,$field){
        return Db::name($this->tableName)->where($where)->sum($field);
    }

    /**
     * 连表查询多条信息
     * @param $join
     * @param array $where
     * @param string $field
     * @param string $order
     * @param string $limit
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function joinAll($join,$where=[],$field='',$order='',$limit=''){
        return Db::name($this->tableName)->alias('a')->field($field)->join($join)->where($where)->order($order)->limit($limit)->select();
    }

    /**
     * @param array $data  插入的数据
     * @return mixed 返回新增数据的自增主键
     * 插入数据的方法
     */
    public function insertData($data = []){
        return Db::name($this->tableName)->insertGetId($data);
    }

    /**
     * @param array $data  更新数据
     * @param array $where  更新条件可为空，如果为空则更新数据需包含主键
     * @return mixed
     * update 方法返回影响数据的条数，没修改任何数据返回 0
     */
    public function updateData($data = [],$where = []){
        if(empty($where)){
            return Db::name($this->tableName)->update($data);
        }
        return Db::name($this->tableName)->where($where)->update($data);
    }

    /**
     * @param array $where  自增条件
     * @param string $field  自增字段名称
     * @param int    $value     自增值
     * @return mixeds 方法返回影响数据的条数
     * 自增一个字段的值
     */
    public function _setInc($where = [],$field = '',$value = 1){
        return Db::name($this->tableName)->where($where)->setInc($field,$value);
    }

    /**
     * @param array $where   自减条件
     * @param string $field   自减字段名称
     * @param int    $value      自减值
     * @return mixed 方法返回影响数据的条数
     * 自减一个字段的值
     */
    public function _setDec($where = [],$field = '',$value = 1){
        return Db::name($this->tableName)->where($where)->setDec($field,$value);
    }

    /**
     * @param  int $id 主键的值
     * @return  mixed 方法返回影响数据的条数，没有删除返回 0
     * 根据主键删除一条数据
     */
    public function deleteDaPtaByPrimaryKey($id = 0){
        return Db::name($this->tableName)->delete($id);
    }

    /**
     * @param array $where  删除条件
     * @return mixed  delete 方法返回影响数据的条数，没有删除返回 0
     * 根据条件删除数据
     */
    public function deleteByWhere($where = []){
        return Db::name($this->tableName)->where($where)->delete();
    }

    /**
     * 分页
     * @param array $where
     * @param string $order
     * @param int $page
     * @param array $query
     * @param array $join
     * @param string $filed
     * @param int $currentPage
     * @param string $group
     * @return \think\Paginator
     */
    public function page($where = [],$order = '',$page = 20,$query = [],$join = [],$filed = '*',$currentPage = 1){
        return Db::name($this->tableName)->alias('a')->field($filed)->join($join)->where($where)->order($order)->paginate($page,false,['query'=>$query,'var_page'=>'page','page'=>$currentPage]);
    }

    public function page1($where = [],$order = '',$page = 20,$query = [],$join = [],$filed = '*',$currentPage = 1,$group = ''){
        return Db::name($this->tableName)->alias('a')->field($filed)->join($join)->where($where)->group($group)->order($order)->paginate($page,false,['query'=>$query,'var_page'=>'page','page'=>$currentPage]);
    }

    /**
     * 记录后台操作日志
     * @param $action
     * @return int|string
     */
    public function addAdminLog($action)
    {
        $id = cookie('login');
        $username = Db::name('admin')->where("id = $id")->value('username');
        // 插入操作日志
        $add['uid'] = $id;
        $add['uname'] = $username;
        $add['uip'] = ip2long($_SERVER["REMOTE_ADDR"]);
        $add['log_action'] = $action;
        $add['log_time'] = time();
        return Db::name('admin_log')->insert($add);
    }
    /**
     * @param array $where  查询条件
     * @param string $field  查询字段
     * @param string $order  排序
     * @param string $limit  条数
     * @return false|\PDOStatement|string|\think\Collection  返回一个数组
     */
    public function selectData($where = [],$field = '*',$order = '',$limit = ''){
        return Db::name($this->tableName)->where($where)->field($field)->order($order)->limit($limit)->select();
    }

    /**
     * @param string $alias 当前表别名
     * @param array $join 默认 INNER JOIN: 举例 [[表名1，条件1，类型1],[表名2，条件2，类型2],]
     * $join = [['auth_group_access c','a.id=c.uid','left'],];
     * @param array $where
     * @param string $field
     * @param string $order
     * @param string $limit
     * @return mixed
     */
    public function joinData($alias='a',$join=[],$where = [],$field = '*',$order = '',$limit = ''){
        return Db::name($this->tableName)->alias($alias)->join($join)->
        where($where)->field($field)->order($order)->limit($limit)->select();
    }
}
