<?php
namespace app\admin\controller;

use think\Db;
use think\Request;

/**
 * 电子礼盒
 */
class Box extends Common
{
    /**
     * 礼盒列表列表
     */
    public function index()
    {
        $list = Db::table('box')->alias('b')
                ->join('user u','u.uid=b.user_id','LEFT')
                ->join('user us','us.uid=b.sender_id','LEFT')
                ->join('box_cate c','c.id=b.cate_id','LEFT')
                ->field('b.id,b.cate_id,b.user_id,b.sender_id,b.music_id,b.addtime,u.wx_nickname as u_nickname,us.wx_nickname as sender_nickname,c.name')
                ->order('b.id desc')
                ->paginate(10);
        $this->assign('list',$list);
        return $this->fetch();
    }

    /**
     * 礼盒列表添加
     */
    public function add()
    {
        $music = Db::table('box_music')->where('status',1)->order('id desc')->select();
        $cate = Db::table('box_cate')->order('id desc')->select();
        $this->assign('music',$music);
        $this->assign('cate',$cate);
        return $this->fetch();
    }

    /**
     * 礼盒列表添加
     */
    public function edit()
    {
        $music = Db::table('box_music')->where('status',1)->order('id desc')->select();
        $cate = Db::table('box_cate')->order('id desc')->select();
        $this->assign('music',$music);
        $id = input('id');
        if($id){
            $info = Db::table('box')->where('id',$id)->find();
            $this->assign('info',$info);
        }
        $this->assign('cate',$cate);
        return $this->fetch();
    }

    /**
     * 语音文件上传
     */
    public function upload_music()
    {
    	// 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('voice_file');
	    // 移动到框架应用根目录/public/uploads/ 目录下
	    if($file){
	        $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . 'box' . DS);
	        if($info){
	            // 成功上传后 获取上传信息
	            $result['url'] = '/public/uploads/box/'.$info->getSaveName();
	            $result['status'] = 1;
	            $result['msg'] = '上传成功';
	            return json($result);
	        }else{
	            // 上传失败获取错误信息
	            $result['msg'] = $file->getError();
	            $result['status'] = 2;
	            return json($result);
	        }
	    }
    }

    /**
     * 照片文件上传
     */
    public function upload_photo()
    {
    	// 获取表单上传文件 例如上传了001.jpg
	    $file = request()->file('photo_file');
	    // 移动到框架应用根目录/public/uploads/ 目录下
	    if($file){
	        $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . 'box' . DS);
	        if($info){
	            // 成功上传后 获取上传信息
	            $result['url'] = '/public/uploads/box/'.$info->getSaveName();
	            $result['status'] = 1;
	            return json($result);
	        }else{
	            // 上传失败获取错误信息
	            $result['msg'] = $file->getError();
	            $result['status'] = 2;
	            return json($result);
	        }
	    }
    }

    /**
     * 提交
     */
    public function box_post()
    {
        //判断
        if(Request::instance()->isPost()){
            $id = input('post.id');
            $post = input('post.');
            $post['addtime'] = time();
            if(!$post['music_id']){
                $this->error('请选择音乐');
            }
            if($id){
                Db::name('box')->where('id',$id)->update($post);
            }else{
                Db::name('box')->insert($post);
            }
            $this->success('操作成功',url('index'));
        }
    }

    /**
     * 删除
     */
    public function del()
    {
        $id = input('id');
        if(!$id){
        	$result['status'] = 2;
        	$result['msg'] = '该盒子不存在';
            return json($result);
        }
        $res = Db::table('box')->where('id',$id)->delete();
        if($res){
        	$result['msg'] = '删除成功';
        	$result['status'] = 1;
        	return json($result);
        }else{
        	$result['msg'] = '该文件已删除';
        	$result['status'] = 2;
        	return json($result);
        }
    }

    

}
