<?php
/**
 * 购物车API
 */
namespace app\api\controller;
use think\Request;
use think\Db;

class Box extends ApiBase
{
    
    /*
     * 获取应用场景
     */
    public function box_scene_list()
    {
        //获取应用场景
        $list = Db::name('box_scene')->field('id,name')->where(['pid'=>0,'status'=>1])->order('sort')->select();
        foreach($list as $key=>$val){
            $list[$key]['list'] = Db::table('box_scene')->field('id,name,picture')->where('pid',$val['id'])->select();
            foreach($list[$key]['list'] as $k=>$v){
                $list[$key]['list'][$k]['picture'] = $v['picture']?SITE_URL.$v['picture']:'';
            }
        }
        $data['status'] = 1;
        $data['data'] = $list;
        $data['msg'] = '获取数据成功';
        $this->ajaxReturn($data);
    }

    /*
     * 获取分类列表
     */
    public function box_cate_list()
    {
        //获取分类列表
        // $list = Db::name('box_cate')->field('id,name,picture')->order('id desc')->limit(3)->select();
        $list = Db::name('box_scene')->field('id,name,picture')->where(['status'=>1,'pid'=>array('neq',0)])->order('id desc')->limit(3)->select();
        foreach($list as $key=>$val){
            $list[$key]['picture'] = $val['picture']?SITE_URL.$val['picture']:'';
        }
        $data['status'] = 1;
        $data['data'] = $list;
        $data['msg'] = '获取数据成功';
        $this->ajaxReturn($data);
    }

    /*
     * 获取视频列表
     */
    public function box_video_list()
    {
        $list = Db::name('box_video')->field('id,name,video_url')->where('status',1)->order('addtime desc')->select();
        foreach($list as $key=>$val){
            $list[$key]['video_url'] = $val['video_url']?SITE_URL.$val['video_url']:'';
        }
        $data['status'] = 1;
        $data['data'] = $list;
        $data['msg'] = '获取数据成功';
        $this->ajaxReturn($data);
    }

    /*
     * 获取应用场景的数据
     * 
     */
    public function get_scene(){
        $id = input('post.id',0);
        $list = Db::table('box_scene')->field('id,name,picture')->where('pid',$id)->order('sort')->select();
        if(!$list){
            $data['data'] = array();
            $data['status'] = 1;
            $data['msg'] = '获取数据成功';
            $this->ajaxReturn($data);
        }
        if($id == 0){
            $list[0]['list'] = Db::table('box_scene')->field('id,name,picture')->where('pid',$list[0]['id'])->order('sort')->select();
            foreach($list[0]['list'] as $key=>$val){
                $list[0]['list'][$key]['picture'] = $val['picture']?SITE_URL.$val['picture']:'';
            }
        }
        foreach($list as $k=>$v){
            $list[$k]['picture'] = $v['picture']?SITE_URL.$v['picture']:'';
        }
        $data['data'] = $list;
        $data['status'] = 1;
        $data['msg'] = '获取数据成功';
        $this->ajaxReturn($data);
    }
    

    /**
     * 文件上传
     */
    public function upload_file()
    {
    	// 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('file');
	    // 移动到框架应用根目录/public/uploads/ 目录下
	    if($file){
	        $info = $file->validate(['size'=>1024*1024*10])->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . 'box' . DS);
	        if($info){
	            // 成功上传后 获取上传信息
	            $result['data'] = SITE_URL.'/public/uploads/box/'.$info->getSaveName();
	            $result['status'] = 1;
	            $result['msg'] = '上传成功';
	            $this->ajaxReturn($result);
	        }else{
	            // 上传失败获取错误信息
	            $result['msg'] = $file->getError();
	            $result['status'] = -1;
	            $result['data'] = '';
	            $this->ajaxReturn($result);
	        }
        }
        // 上传失败获取错误信息
        $result['msg'] = '上传文件不存在';
        $result['status'] = -1;
        $result['data'] = '';
        $this->ajaxReturn($result);
    }

    //选择音乐页面
    public function get_music_list()
    {
        $list = Db::table('box_music')->field('id,name,music_url,musician')->where('status',1)->select();
        foreach($list as $key=>$val){
            $list[$key]['music_url'] = $val['music_url']?SITE_URL.$val['music_url']:'';
        }
        $result['status'] = 1;
        $result['data'] = $list;
        $result['msg'] = '获取数据成功';
        $this->ajaxReturn($result);
    }

    //制作电子礼盒的界面
    public function get_box()
    {
        $id = input('id',0);
        $cate_id = input('cate_id',0);
        $advice = input('advice',0);
        $data['user_id'] = $this->get_user_id();
        $data['advice'] = $advice;//赠言
        $user_id = $this->get_user_id();
        if(!$id && !$cate_id){
            $result['status'] = -1;
            $result['msg'] = '创建盒子需要传类别id';
            $this->ajaxReturn($result);
        }
        if(!$id){
            $last_box_id = Db::name('box')->where(['user_id'=>$user_id])->order('addtime desc')->limit(1)->value('id');
            if($last_box_id){
                $last_box_id = Db::name('box')->where(['user_id'=>$user_id])->order('addtime desc')->limit(1)->value('id');
                $count = Db::name('order')->where(['box_id'=>$last_box_id,'user_id'=>$user_id])->count();
                if(!$count){
                    $id = $last_box_id;
                }
            }
        }
        if($id){
            $box_info = Db::table('box')->field('id,music_id,photo_url,voice_url,content')->where('id',$id)->find();
            $box_info['music_name'] = Db::name('box_music')->where('id',$box_info['music_id'])->value('name');
        }else{
            $data['addtime'] = time();
            $data['cate_id'] = $cate_id;
            $data['photo_url'] = '/image/default.png';
            $data['music_id'] = Db::table('box_music')->where('status',1)->value('id');
            $id = Db::table('box')->insertGetId($data);
            $result['data']['id'] = $id;
            $result['data']['music_id'] = $data['music_id'];
            $result['data']['photo_url'] =  SITE_URL.$data['photo_url'];
            $result['data']['voice_url'] = '';
            $result['data']['content'] = '';
            $result['status'] = 1;
            $result['msg'] = '获取数据成功';
            $this->ajaxReturn($result);
        }
        $info['data'] = $box_info;
        $info['data']['photo_url'] = $box_info['photo_url']?SITE_URL.$box_info['photo_url']:'';
        $info['data']['voice_url'] = $box_info['voice_url']?SITE_URL.$box_info['voice_url']:'';
        $info['data']['content'] = $box_info['content']?SITE_URL.$box_info['content']:'';
        $info['data']['id'] = $id;
        $info['status'] = 1;
        $info['msg'] = '获取数据成功';
        $this->ajaxReturn($info);
    }

    //修改盒子某一个字段
    public function set_box()
    {
        $id = input('post.id',0);
        $music_id = input('post.music_id',0);
        $photo_url = input('post.photo_url','');
        $voice_url = input('post.voice_url','');
        $content = input('post.content','');
        $scene_id = input('post.scene_id','');//场景
        $user_id = $this->get_user_id();
        if(!$id){
            $result['status'] = -1;
            $result['msg'] = '请提供礼盒ID';
            $this->ajaxReturn($result);
        }
        if($music_id){
            $data['music_id'] = $music_id;
        }
        if($scene_id){
            $data['scene_id'] = $scene_id;
        }
        if($photo_url){
            $data['photo_url'] = str_replace(SITE_URL,'',$photo_url);
        }
        if($voice_url){
            $data['voice_url'] = str_replace(SITE_URL,'',$voice_url);
        }
        if($content){
            $data['content'] = $content;
        }
        $count = Db::table('box')->where('id',$id)->where('user_id',$user_id)->count();
        if(!$count){
            $result['status'] = -1;
            $result['msg'] = '该盒子不存在';
            $this->ajaxReturn($result);
        }
        
        //如果留言为空
        if(!isset($data)){
            $result['status'] = 1;
            $result['msg'] = '数据未改变';
            $this->ajaxReturn($result);
        }

        $res = Db::table('box')->where('id',$id)->update($data);
        if($res){
            $result['status'] = 1;
            $result['msg'] = '修改成功';
            $this->ajaxReturn($result);
        }else{
            $result['status'] = 1;
            $result['msg'] = '数据未改变';
            $this->ajaxReturn($result);
        }
    }


}
