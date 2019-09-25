<?php
namespace app\admin\controller;

use app\common\model\Config;
use think\Db;
use think\Loader;
use think\Request;
use think\Session;

/*
 * 后台管理控制器
 */
class Login extends \think\Controller
{
    /**
     * 登录
     */
    public function index()
    {
        //避免没有权限无限循环跳跳跳
        Session::set('admin_user_auth', '');
        if (Request::instance()->isPost()) {
            $username = input('post.username');
            $password = input('post.password');
            $code = input('post.code');
            if(!$code){
                $this->error('验证码不能为空');
            }
            // 实例化验证器
            $validate = Loader::validate('Login');
            // 验证数据
            $data = ['username' => $username, 'password' => $password];
            // 验证码
            $Verify = new \think\Verify();
            $yzm = $Verify->check($code);
            if($yzm == false){
                $this->error('验证码错误');
            }

            $where['username'] = $username;
            $where['status']   = 1;

            $user_info = Db::table('mg_user')->where($where)->find();
            if ($user_info && $user_info['password'] === minishop_md5($password, $user_info['salt'])) {
                $session['mgid']     = $user_info['mgid'];
                $session['username'] = $user_info['username'];
                // 记录用户登录信息
                Session::set('admin_user_auth', $session);
                define('UID', $user_info['mgid']);
                $this->success('登陆成功！', url('index/index'));
            }
            $this->error('密码错误！');

        } else {
            // 登入标题
            config(Config::where('name', 'login_title')->column('value', 'name'));
            return $this->fetch();
        }
    }

    /*
     * 退出登录
     */
    public function login_out()
    {
        session('admin_user_auth', null);
        session('ALL_MENU_LIST', null);
        $this->redirect('login/index');
    }

    //验证码TP3的
    public function verify() {
        $config = array(
            'codeSet'   =>  '123456789',   // 验证码字符集合
            'useImgBg' => false,           // 使用背景图片
            'fontSize' => 14,              // 验证码字体大小(px)
            'useCurve' => false,          // 是否画混淆曲线
            'useNoise' => false,          // 是否添加杂点
            'length' => 4,                 // 验证码位数
            'bg' => array(255, 255, 255),  // 背景颜色
        );
        $Verify = new \think\Verify($config);
        $Verify->entry();
    }

}
