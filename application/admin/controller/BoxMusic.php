<?php
namespace app\admin\controller;

use think\Db;
use think\Request;

/**
 * 音乐管理控制器
 */
class BoxMusic extends Common
{

    /**
     * 音乐列表
     */
    public function index()
    {
        $list = Db::table('box_music')->order('id desc')->paginate(10);
        $this->assign('list',$list);
        return $this->fetch();
    }

    /**
     * 添加音乐
     */
    public function add()
    {	
    	if(Request::instance()->isPost()){
        	$post = input('post.');
        	if(!$post['name']){
        		$this->error('音乐名称不能为空');
			}
			if(!$post['musician']){
        		$this->error('音乐人不能为空');
        	}
        	if(!$post['music_url']){
        		$this->error('请上传音乐文件');
        	}
        	$data['name'] = $post['name'];
        	$data['music_url'] = $post['music_url'];
        	$data['status'] = $post['status'];
        	$data['musician'] = $post['musician'];
        	$data['addtime'] = time();
        	$res = Db::table('box_music')->insert($data);
        	if($res){
        		$this->success('添加成功',url('index'));
        	}else{
        		$this->error('添加失败');
        	}
        }else{
        	return $this->fetch();
        }
    }

    /*
     * 删除音乐
     */
    public function del()
    {
        $music_id = input('music_id');
        if(!$music_id){
        	$result['status'] = 2;
        	$result['msg'] = '该音乐不存在';
            return json($result);
        }
        $res = Db::table('box_music')->where('id',$music_id)->delete();
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

    /*
     * 修改音乐
     */
    public function edit()
    {
    	$id = input('id');
    	if(!$id){
    		$this->error('该音乐不存在');
    	}
    	$info = Db::table('box_music')->where('id',$id)->find();
    	if(!$info){
    		$this->error('该音乐不存在');
    	}
    	$this->assign('info',$info);
    	return $this->fetch();
    }


    /*
     * 修改提交
     */
    public function editPost()
    {
    	if(Request::instance()->isPost()){
        	$post = input('post.');
        	if(!$post['id']){
        		$this->error('音乐id不能为空');
        	}
        	if(!$post['name']){
        		$this->error('音乐名称不能为空');
			}
			if(!$post['musician']){
        		$this->error('音乐人不能为空');
        	}
        	if(!$post['music_url']){
        		$this->error('请上传音乐文件');
        	}
        	$data['name'] = $post['name'];
        	$data['musician'] = $post['musician'];
        	$data['music_url'] = $post['music_url'];
        	$data['status'] = $post['status'];
        	$res = Db::table('box_music')->where('id',$post['id'])->update($data);
        	if($res){
        		$this->success('修改成功',url('index'));
        	}else{
        		$this->error('修改失败');
        	}
        }else{
        	return $this->fetch();
        }
    }
}
