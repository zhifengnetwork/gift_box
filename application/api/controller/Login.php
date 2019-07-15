<?php
/**
 * Created by PhpStorm.
 * User: MyPC
 * Date: 2019/4/22
 * Time: 17:53
 */

namespace app\api\controller;

use think\Db;
use think\Loader;
use think\Request;
use think\Session;
use think\captcha\Captcha;
use app\common\util\jwt\JWT;

class Login extends \think\Controller
{

    public function ajaxReturn($data)
    {
        header('Access-Control-Allow-Origin:*');
        header('Access-Control-Allow-Headers:*');
        header("Access-Control-Allow-Methods:GET, POST, OPTIONS, DELETE");
        header('Content-Type:application/json; charset=utf-8');
        exit(str_replace("\\/", "/", json_encode($data, JSON_UNESCAPED_UNICODE)));
    }

    /**
     * 获取code 的 url
     */
    public function get_code_url() {
        
        $baseUrl = I('baseUrl');
        if(!$baseUrl){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'当前地址参数baseUrl为空','data'=>'']);
        }

        $appid = M('config')->where(['name'=>'appid'])->value('value');
        $appsecret = M('config')->where(['name'=>'appsecret'])->value('value');
        if(!$appid || !$appsecret){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'后台参数appid或appsecret配置为空','data'=>'']);
        }
    
        $baseUrl = urlencode($baseUrl);

        $url = $this->__CreateOauthUrlForCode($baseUrl,$appid,$appsecret); // 获取 code地址

        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$url]);
    }

     /**
     * 凭 code 登录
     */
    public function login_by_code() {
        
        $code = I('code');
        if(!$code){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'参数code为空','data'=>'']);
        }

        $appid = M('config')->where(['name'=>'appid'])->value('value');
        $appsecret = M('config')->where(['name'=>'appsecret'])->value('value');
        if(!$appid || !$appsecret){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'后台参数appid或appsecret配置为空','data'=>'']);
        }
    
        $data = $this->getOpenidFromMp($code,$appid,$appsecret);//获取网页授权access_token和用户openid
        if(isset($data['errcode'])){
            $this->ajaxReturn(['status' => -1 , 'msg'=>$data['errmsg'],'data'=>'']);
        }

        write_log("openid:".$data['openid']);

        $data2 = $this->GetUserInfo($data['access_token'],$data['openid']);//获取微信用户信息
        $data['nickname'] = empty($data2['nickname']) ? '微信用户' : trim($data2['nickname']);
        $data['sex'] = $data2['sex'];
        $data['head_pic'] = $data2['headimgurl']; 
        $data['oauth_child'] = 'mp';
        $data['oauth'] = 'weixin';
        if(isset($data2['unionid'])){
            $data['unionid'] = $data2['unionid'];
        }
       
        //判断是否注册
      
        $field = 'id,openid,avatar';//avatar头像
        $userinfo = M('member')->where(['openid'=>$data['openid']])->field($field)->find();
        if(!$userinfo){
            $newdata = array(
                'openid' => $data['openid'],
                'nickname' => $data['nickname'],
                'createtime' => time(),
                'avatar' => $data['head_pic']
            );
            M('member')->insert($newdata);

            //再次查找
            $userinfo = M('member')->where(['openid'=>$data['openid']])->field($field)->find();
        }

        //创建token
        if(!$userinfo['id']){
            $this->ajaxReturn(['status' => -1 , 'msg'=> '注册或登录出错' ,'data'=>'']);
        }

        $userinfo['token'] = $this->create_token($userinfo['id']);

        $this->ajaxReturn(['status' => 1 , 'msg'=>'登录成功，你很棒棒','data'=>$userinfo]);
    }

    /**
     *
     * 通过access_token openid 从工作平台获取UserInfo      
     * @return openid
     */
    public function GetUserInfo($access_token,$openid)
    {         
        // 获取用户 信息
        $url = $this->__CreateOauthUrlForUserinfo($access_token,$openid);
        $ch = curl_init();//初始化curl        
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);//设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);         
        $res = curl_exec($ch);//运行curl，结果以jason形式返回            
        $data = json_decode($res,true);            
        curl_close($ch);
      
        return $data;
    }

    /**
     * 微信登录
     */
    public function index () {
        $code = I('code');
        if(!$code){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'code不能为空','data'=>'']);
        }

        $appid = M('config')->where(['name'=>'appid'])->value('value');
        $appsecret = M('config')->where(['name'=>'appsecret'])->value('value');
        
        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$appid.'&secret='.$appsecret.'&js_code='.$code.'&grant_type=authorization_code' ;
        $result = httpRequest($url, 'GET');
        $arr = json_decode($result, true);
        if(!isset($arr['openid'])){
            $this->ajaxReturn(['status' => -1 , 'msg'=>$arr['errmsg'],'data'=>'']);
        }

        $openid = $arr['openid'];

        // 查询数据库，判断是否有此openid
        $data = Db::table('member')->where('openid',$openid)->find(); 
        if(!$data){
            Db::table('member')->insert(['openid'=>$openid]); 
            $data = Db::table('member')->where('openid',$openid)->find(); 

            $data['token'] = $this->create_token($data['id']);

            $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$data]);
        }else{
            
            $data['token'] = $this->create_token($data['id']);
            
            $this->ajaxReturn( ['status'=>1,'msg'=>'获取用户信息成功','data'=>$data]);
            
        }
       
    }

    /**
     * 登录接口
     */
    public function login()
    {

        header('Access-Control-Allow-Origin:*');
        header('Access-Control-Allow-Headers:*');
        header("Access-Control-Allow-Methods:GET, POST, OPTIONS, DELETE");
        header('Content-Type:application/json; charset=utf-8');

        $mobile    = input('mobile');
        $password1 = input('password');
        $password  = md5('TPSHOP'.$password1);

        $data = Db::name("users")->where('mobile',$mobile)
            ->field('password,user_id')
            ->find();

        if(!$data){
            exit(json_encode(['status' => -1 , 'msg'=>'手机不存在或错误','data'=>null]));
        }
        if ($password != $data['password']) {
            exit(json_encode(['status' => -2 , 'msg'=>'登录密码错误','data'=>null]));
        }
        unset($data['password']);
        //重写
        $data['token'] = $this->create_token($data['user_id']);
        
        exit(json_encode(['status' => 0 , 'msg'=>'登录成功','data'=>$data],JSON_UNESCAPED_UNICODE));

    }

    

//    public function login () {
//        if (Request::instance()->isPost()) {
//            $username = input('username');
//            $password = input('password');
//            // 实例化验证器
//            $validate = Loader::validate('Login');
//            // 验证数据
//            $data = ['username' => $username, 'password' => $password];
//            // 验证
//            $code = input('captcha');
//            $str = session('captcha_id');
//            $captcha = new \think\captcha\Captcha();
//            if (!$captcha->check($code,$str)){
//                return json(['code'=>0,'msg'=>'验证码错误']);
//            }
//            if (!$validate->check($data)) {
//                return $this->error($validate->getError());
//            }
//            $where['username'] = $username;
//            $where['status']   = 1;
//            $user_info = Db::table('mg_user')->where($where)->find();
//            if ($user_info && $user_info['password'] === minishop_md5($password, $user_info['salt'])) {
//                $session['uid']     = $user_info['mgid'];
//                $session['user_name'] = $user_info['username'];
//                // 记录用户登录信息
//                Session::set('admin_user_auth', $session);
//                return json(['code'=>1,'msg'=>'登录成功']);
//            }
//            return json(['code'=>0,'msg'=>'密码错误！']);
//        }
//    }



//    /*
//     *  获取验证码
//      */
//    public function loginCaptcha () {
//        $str  = time().uniqid();
//        Session::set('captcha_id', $str);
//        $captcha = new Captcha();
//        return $captcha->entry($str);
//    }
//
//    /*
//     * 退出登录
//     */
//    public function login_out()
//    {
//        session('admin_user_auth', null);
//        session('ALL_MENU_LIST', null);
//        return json(['code'=>1,'msg'=>'请登录','data'=>'']);
//    }
}