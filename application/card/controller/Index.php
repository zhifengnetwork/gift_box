<?php
namespace app\card\controller;

use think\Controller;
use think\Request;
use think\Db;

class Index extends Controller
{
    public function index()
    {
        $card_id = input('card_id',0);
        $info = Db::name('box')->where('id',$card_id)->find();
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
        $info['cate_url'] = $info['cate_url']?SITE_URL.$info['cate_url']:'';
        $info['music_url'] = $info['music_url']?SITE_URL.$info['music_url']:'';
        $info['video_url'] = $info['video_url']?SITE_URL.$info['video_url']:'';
        $info['scene_url'] = $info['scene_url']?SITE_URL.$info['scene_url']:'';
        $info['photo_url'] = $info['photo_url']?SITE_URL.$info['photo_url']:'';
        $info['voice_url'] = $info['voice_url']?SITE_URL.$info['voice_url']:'';
        $this->assign('info',$info);
        return $this->fetch();
    }
}
