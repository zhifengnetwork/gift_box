<?php
namespace app\card\controller;

use app\common\logic\wechat\WechatUtil;
use think\Controller;
use think\Request;
use think\Db;

class Index extends Controller
{
    /**
     * card_id=' + id + '&type=' + type + '&order_id=' + order_id + '&pwdstr=' + pwdstr
     */
    public function index()
    {
        $card_id = input('card_id');
        $type = input('type');
        $order_id = input('order_id');
        $pwdstr = input('pwdstr');

        write_log(SITE_URL.'/card?card_id='.$card_id.'&type='.$type.'&order_id='.$order_id.'&pwdstr='.$pwdstr);

        if(!$card_id){
            echo "<h1>card_id不存在</h1>";
            $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://": "http://";
            $nowurl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            echo $nowurl;//输出完整的url
            exit;
        }
        $info = Db::name('box')->where('id',$card_id)->find();
        if(!$info){
            echo "<h1>礼盒不存在</h1>";
            exit;
            $info['cate_url'] = '';
            $info['music_url'] = '';
            $info['video_url'] = '';
            $info['scene_url'] = '';
            $info['photo_url'] = '';
            $info['content'] = '';
            $info['voice_url'] = '';
            $this->assign('info',$info);
            return $this->fetch();
        }
        $info['cate_url'] = '';
        $info['music_url'] = '';
        $info['video_url'] = '';
        $info['scene_url'] = '';
        //类别
        if($info['cate_id']){
            $info['cate_url'] = Db::name('box_cate')->where('id',$info['cate_id'])->value('picture');
        }
        //音乐
        if($info['music_id']){
            $info['music_url'] = Db::name('box_music')->where('id',$info['music_id'])->value('music_url');
        }
        //相框
        if($info['video_id']){
            $info['video_url'] = Db::name('box_video')->where('id',$info['video_id'])->value('video_url');
        }
        //场景
        if($info['scene_id']){
            $info['scene_url'] = Db::name('box_scene')->where('id',$info['scene_id'])->value('scene_url');
        }
        $info['cate_url'] = $info['cate_url']?SITE_URL.$info['cate_url']:'';//类别
        $info['music_url'] = $info['music_url']?SITE_URL.$info['music_url']:'';//音乐
        $info['video_url'] = $info['video_url']?SITE_URL.$info['video_url']:'';//相框
        $info['scene_url'] = $info['scene_url']?SITE_URL.$info['scene_url']:'';//场景
        $info['photo_url'] = $info['photo_url']?SITE_URL.$info['photo_url']:'';//照片
        $info['voice_url'] = $info['voice_url']?SITE_URL.$info['voice_url']:'';//录音
        $this->assign('info',$info);

        // $url = "{url: '/pages/commodity/detalis/payment/award/award?id=459'}";
        // $this->assign('url',$url);

        $this->assign('card_id', $card_id);
        $this->assign('type', $type);
        $this->assign('order_id', $order_id);
        $this->assign('pwdstr', $pwdstr);

        $lottery_time = '';
        if($type == '2' && $order_id){
            $lottery_time = M('order')->where(['order_id'=>$order_id])->value('lottery_time');
            $lottery_time = date('Y-m-d H:i:s',$lottery_time);
        }
        $this->assign('lottery_time', $lottery_time);


        return $this->fetch();
    }


     //微信Jssdk 操作类 用分享朋友圈 JS
     public function ajaxGetWxConfig()
     {
         $askUrl = input('askUrl');//分享URL
         $askUrl = urldecode($askUrl);
 
         $config['appid'] = M('config')->where(['name'=>'appid'])->value('value');
         $config['appsecret'] = M('config')->where(['name'=>'appsecret'])->value('value');
            $config['web_expires'] =  M('config')->where(['name'=>'web_expires'])->value('value');
            $config['web_access_token'] = M('config')->where(['name'=>'web_access_token'])->value('value');


         $wechat = new WechatUtil($config);

         $signPackage = $wechat->getSignPackage($askUrl);
 
         $this->ajaxReturn($signPackage);
     }


     public function ajaxReturn($data = [],$type = 'json'){
        header('Content-Type:application/json; charset=utf-8');
        /*$data   = !empty($data) ? $data : ['status' => 1, 'msg' => '操作成功'];
        exit(json_encode($data,JSON_UNESCAPED_UNICODE));*/
        exit(json_encode($data));
    }
}
