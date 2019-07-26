<?php
namespace app\card\controller;

use app\common\logic\wechat\WechatUtil;
use think\Controller;
use think\Request;
use think\Db;

class Index extends Controller
{
    public function index()
    {
        $card_id = input('card_id',0);
        $info = Db::name('box')->where('id',$card_id)->find();
        if(!$info){
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

        $url = "{url: '/pages/commodity/detalis/payment/award/award?id=459'}";
        $this->assign('url',$url);

        return $this->fetch();
    }


     //微信Jssdk 操作类 用分享朋友圈 JS
     public function ajaxGetWxConfig()
     {
         $askUrl = input('askUrl');//分享URL
         $askUrl = urldecode($askUrl);
 
         $wechat = new WechatUtil;
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
