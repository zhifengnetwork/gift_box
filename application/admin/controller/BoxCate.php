<?php
namespace app\admin\controller;

use think\Db;
use think\Request;
use app\common\model\Advertisement as Advertise;

/**
 * 电子礼盒类别
 */
class BoxCate extends Common
{
    /**
     * 礼盒场景列表
     */
    public function index()
    {
        $list = Db::table('box_scene')->where('pid',0)->order('sort asc')->paginate(10)->each(function($v,$k){
            $v['list'] = Db::table('box_scene')->where('pid',$v['id'])->order('sort')->select();
            return $v;
        });;
        $this->assign('list',$list);
        return $this->fetch();
    }

    //添加
    public function add()
    {
        $pid = input('pid',0);
        $cate_list = Db::table('box_scene')->field('id,name')->where('pid',0)->select();
        $this->assign('pid',$pid);
        $this->assign('cate_list',$cate_list);
        return $this->fetch();
    }

    //提交
    public function cate_post()
    {
        //判断
        if(Request::instance()->isPost()){
            $post = input('post.');
            // 图片验证1
            $res = Advertise::pictureUpload('box_scene', 0,'file');
            if ($res[0] == 1) {
                $this->error($res[0]);
            } else {
                $pictureName                             = $res[1];
                !empty($pictureName) && $post['picture'] = '/public'.$pictureName;
            }
            unset($post['file']);
            $id = input('post.id');
            if($id){
                Db::table('box_scene')->where('id',$id)->update($post);
            }else{
                $post['addtime'] = time();
                Db::table('box_scene')->insert($post);
            }
            $this->success('操作成功',url('index'));
        }
    }

    // 删除
    public function del()
    {
        $id = input('id');
        if($id == 28){
            return json(['status'=>0,'msg'=>'花这个不能删除']);
        }
        $count = Db::table('box_scene')->where('pid',$id)->count();
        if($count){
            return json(['status'=>0,'msg'=>'该分类有下级，请先删除其下级']);
        }
        Db::table('box_scene')->where('id',$id)->delete();
        return json(['status'=>1,'msg'=>'删除成功']);
    }

    //修改
    public function edit()
    {
        $id = input('id');
        $info = Db::table('box_scene')->where('id',$id)->find();
        $cate_list = Db::table('box_scene')->field('id,name')->where('pid',0)->select();
        $this->assign('cate_list',$cate_list);
        $this->assign('info',$info);
        return $this->fetch();
    }

    //信封列表
    public function envelope_list()
    {
        $list =  Db::name('box_envelope')->paginate(10);
        $this->assign('list',$list);
        return $this->fetch();
    }

    //添加信封
    public function add_envelope()
    {
        $id = input('id');
        if($id){
            $info = Db::name('box_envelope')->where('id',$id)->find();
        }else{
            $info = getTableField('box_envelope');
        }
        $this->assign('info',$info);
        return $this->fetch();
    }

    //提交
    public function envelope_post()
    {
        //判断
        if(Request::instance()->isPost()){
            $post = input('post.');
            if(!$post['name']){
                $this->error('请填写名称');
            }
            // 图片验证,路径
            $res = Advertise::pictureUpload('box_scene', 0);
            if ($res[0] == 1) {
                $this->error($res[0]);
            } else {
                $pictureName                             = $res[1];
                !empty($pictureName) && $post['picture'] = '/public'.$pictureName;
            }
            unset($post['file']);
            $id = input('post.id');
            if($id){
                Db::table('box_envelope')->where('id',$id)->update($post);
            }else{
                Db::table('box_envelope')->insert($post);
            }
            $this->success('操作成功',url('envelope_list'));
        }
    }

    // 删除
    public function del_envelope()
    {
        $id = input('id');
        Db::table('box_envelope')->where('id',$id)->delete();
        return json(['status'=>1,'msg'=>'删除成功']);
    }

    //类别管理
    public function cate_list()
    {
        $list =  Db::name('box_cate')->paginate(10);
        $this->assign('list',$list);
        return $this->fetch();
    }

    //添加信封
    public function add_cate()
    {
        $id = input('id');
        if($id){
            $info = Db::name('box_cate')->where('id',$id)->find();
        }else{
            $info = getTableField('box_cate');
        }
        $this->assign('info',$info);
        return $this->fetch();
    }

    //提交
    public function cate_post_2()
    {
        //判断
        if(Request::instance()->isPost()){
            $post = input('post.');
            if(!$post['name']){
                $this->error('请填写名称');
            }
            // 图片验证,路径
            $res = Advertise::pictureUpload('box_scene', 0);
            if ($res[0] == 1) {
                $this->error($res[0]);
            } else {
                $pictureName                             = $res[1];
                !empty($pictureName) && $post['picture'] = '/public'.$pictureName;
            }
            unset($post['file']);
            $id = input('post.id');
            if($id){
                Db::table('box_cate')->where('id',$id)->update($post);
            }else{
                Db::table('box_cate')->insert($post);
            }
            $this->success('操作成功',url('cate_list'));
        }
    }

    // 删除
    public function del_cate()
    {
        $id = input('id');
        Db::table('box_cate')->where('id',$id)->delete();
        return json(['status'=>1,'msg'=>'删除成功']);
    }

}
