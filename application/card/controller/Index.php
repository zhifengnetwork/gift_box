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
        $preview =  input('preview',0);
        if(!$card_id){
            if($order_id){
                $card_id = Db::name('order')->where('order_id',$order_id)->value('box_id');
            }
        }
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
        
        // box_scene
        $box_scene = Db::name('box_scene')->where('id',$info['cate_id'])->find();

        //类别
        if($info['cate_id']){
            $info['cate_url'] = $box_scene['gif'];
            $info['tail_img'] = $box_scene['tail_img'];
            $info['duration'] = $box_scene['duration'];
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
            $info['scene_url'] = $box_scene['scene_url'];
        }
        $info['cate_url'] = $info['cate_url']?SITE_URL.$info['cate_url']:'';//类别
        $info['tail_img'] = $info['tail_img']?SITE_URL.$info['tail_img']:'';//静图
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
        $this->assign('preview', $preview);
        if($preview == 1){
            $text = '返回';
        }else{
            $text = '直接跳過';
        }
        $this->assign('text', $text);

        $lottery_time = '';
        if($type == '2' && $order_id){
            $lottery_time = M('order')->where(['order_id'=>$order_id])->value('lottery_time');
            $lottery_time = date('Y-m-d H:i:s',$lottery_time);
        }
       
        $this->assign('lottery_time', $lottery_time);


        //判断是不是  花
        // 不是花的话，就加载  gif.html

        if($info['cate_id'] == 28){
            $template = 'index';
        }else{
            $template = 'gif';
            $gif_url = $info['cate_url'];
            $this->assign('gif_url', $gif_url);
           
            //时长
            $duration = $info['duration'];
            $this->assign('duration',$duration);

            $tail_img = $info['tail_img'];
            $this->assign('tail_img',$tail_img);
        }
        
        //祝福语处理
        $font_size =  $box_scene['font_size'] ? $box_scene['font_size'] : '16';//祝福语颜色
        //祝福语颜色
        $color =  $box_scene['color'] ? $box_scene['color'] : '#000000';//祝福语颜色
        //祝福语文字
        $is_strong = $box_scene['is_strong'] == 1 ? 'font-weight:bold;' : '';//是否加粗

        //头像的圆形方形 圆形是0，方形是1
        $user_img_type = $box_scene['user_img_type'] == 1 ? '' : 'border-radius: 50%;';

        $this->assign('style',"font-size:$font_size"."px; color: $color;$is_strong");
        $this->assign('touxiang',$user_img_type);
        $this->assign('box_scene',$box_scene);

        return $this->fetch($template);
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
