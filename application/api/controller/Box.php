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
    public function box_cate_list()
    {
        //获取应用场景
        $list = Db::name('box_cate')->field('id,name')->where(['pid'=>0,'status'=>1])->order('sort')->select();
        foreach($list as $key=>$val){
            $list[$key]['list'] = Db::table('box_cate')->field('id,name,picture')->where('pid',$val['id'])->select();
            foreach($list[$key]['list'] as $k=>$v){
                $list[$key]['list'][$k]['picture'] = $v['picture']?$this->http_host.$v['picture']:'';
            }
        }
        $data['status'] = 1;
        $data['data'] = $list;
        $data['msg'] = '获取数据成功';
        return json($data);
    }

    /*
     * 获取应用场景的数据
     * 
     */
    public function get_cate(){
        $id = input('post.id',0);
        $list = Db::table('box_cate')->field('id,name,picture')->where('pid',$id)->order('sort')->select();
        if(!$list){
            $data['data'] = array();
            $data['status'] = 1;
            $data['msg'] = '获取数据成功';
            return json($data);
        }
        if($id == 0){
            $list[0]['list'] = Db::table('box_cate')->field('id,name,picture')->where('pid',$list[0]['id'])->order('sort')->select();
            foreach($list[0]['list'] as $key=>$val){
                $list[0]['list'][$key]['picture'] = $val['picture']?$this->http_host.$val['picture']:'';
            }
        }
        foreach($list as $k=>$v){
            $list[$key]['picture'] = $v['picture']?$this->http_host.$v['picture']:'';
        }
        $data['data'] = $list;
        $data['status'] = 1;
        $data['msg'] = '获取数据成功';
        return json($data);
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
	            $result['url'] = $this->http_host.'/public/uploads/box/'.$info->getSaveName();
	            $result['status'] = 1;
	            return json($result);
	        }else{
	            // 上传失败获取错误信息
	            $result['msg'] = $file->getError();
	            $result['status'] = 2;
	            return json($result);
	        }
        }
        // 上传失败获取错误信息
        $result['msg'] = '上传文件不存在';
        $result['status'] = 2;
        return json($result);
    }

    //选择音乐页面
    public function get_music_list()
    {
        $list = Db::table('box_music')->field('id,name,music_url,musician')->where('status',1)->select();
        foreach($list as $key=>$val){
            $list[$key]['music_url'] = $val['music_url']?$this->http_host.$val['music_url']:'';
        }
        $result['status'] = 1;
        $result['data'] = $list;
        $result['msg'] = '获取数据成功';
        return json($result);
    }

    //制作电子礼盒2的界面
    public function get_box()
    {
        $id = input('id');
        $cate_id = input('cate_id');
        $data['user_id'] = $this->get_user_id();
        if(!$id && !$cate_id){
            $result['status'] = 2;
            $result['msg'] = '创建盒子需要传类别id';
            return json($result);
        }
        if($id){
            $info = Db::table('box')->field('id,music_id,photo_url,voice_url,content')->where('id',$id)->find();
        }else{
            $data['addtime'] = time();
            $data['cate_id'] = $cate_id;
            $id = Db::table('box')->insertGetId($data);
            $result['id'] = $id;
            $result['music_id'] = 0;
            $result['photo_url'] = '';
            $result['voice_url'] = '';
            $result['content'] = '';
            $result['status'] = 1;
            $result['msg'] = '获取数据成功';
            return json($result);
        }
        $info['photo_url'] = $info['photo_url']?$this->http_host.$info['photo_url']:'';
        $info['voice_url'] = $info['voice_url']?$this->http_host.$info['voice_url']:'';
        $info['content'] = $info['content']?$this->http_host.$info['content']:'';
        $info['status'] = 1;
        $info['msg'] = '获取数据成功';
        return json($info);
    }

    //修改盒子某一个字段
    public function set_box()
    {
        $id = input('post.id',0);
        $music_id = input('post.music_id',0);
        $photo_url = input('post.photo_url','');
        $voice_url = input('post.voice_url','');
        $content = input('post.content','');
        $user_id = $this->get_user_id();
        if(!$id){
            $result['status'] = 2;
            $result['msg'] = '请提供礼盒ID';
            return json($result);
        }
        if($music_id){
            $data['music_id'] = $music_id;
        }
        if($photo_url){
            $data['photo_url'] = str_replace($this->http_host,'',$photo_url);
        }
        if($voice_url){
            $data['voice_url'] = str_replace($this->http_host,'',$voice_url);
        }
        if($content){
            $data['content'] = $content;
        }
        $count = Db::table('box')->where('id',$id)->where('user_id',$user_id)->count();
        if(!$count){
            $result['status'] = 2;
            $result['msg'] = '该盒子不存在';
            return json($result);
        }
        $res = Db::table('box')->where('id',$id)->save($data);
        if($res){
            $result['status'] = 1;
            $result['msg'] = '修改成功';
            return json($result);
        }else{
            $result['status'] = 1;
            $result['msg'] = '数据未改变';
            return json($result);
        }
    }
}
