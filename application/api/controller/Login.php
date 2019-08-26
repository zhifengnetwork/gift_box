<?php
namespace app\api\controller;

use think\Db;
use think\Loader;
use think\Request;
use think\Session;
use think\captcha\Captcha;
use app\common\util\jwt\JWT;

class Login extends ApiBase
{

    /**
     * 小程序的
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

            $newdata = array(
                'openid' => $openid,
                'createtime' => time(),
                'nickname' => $data['nickname'],
                'avatar' => $data['head_pic']
            );

            Db::table('member')->insert($newdata); 
            $data = Db::table('member')->where('openid',$openid)->find(); 

            $data['token'] = $this->create_token($data['id']);

            $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$data]);
        }else{
            
            $data['token'] = $this->create_token($data['id']);
            $data['avatar'] = substr($data['avatar'],0,1) !='h'?SITE_URL.$data['avatar']:$data['avatar'];
            $this->ajaxReturn( ['status'=>1,'msg'=>'获取用户信息成功','data'=>$data]);
            
        }
       
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

}