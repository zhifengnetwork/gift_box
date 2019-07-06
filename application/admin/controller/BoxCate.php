<?php
namespace app\admin\controller;

use think\Db;
use think\Request;

/**
 * 电子礼盒
 */
class BoxCate extends Common
{
    /**
     * 礼盒列表列表
     */
    public function index()
    {
        $list = Db::table('box_cate')->order('id desc')->paginate(10);
        $this->assign('list',$list);
        return $this->fetch();
    }

    //添加
    public function add()
    {
        $id = input('id');
        if($id){
            $info = Db::table('box_cate')->where('id',$id)->find();
            $this->assign('info',$info);
        }
        return $this->fetch();
    }

    //提交
    public function cate_post()
    {
        //判断
        if(Request::instance()->isPost()){
            $id = input('post.id');
            $name = input('post.name');
            if($id){
                Db::table('box_cate')->where('id',$id)->update(['name'=>$name]);
            }else{
                Db::table('box_cate')->insert(['name'=>$name]);
            }
            $this->success('操作成功',url('index'));
        }
    }

    // 删除
    public function del()
    {
        $id = input('id');
        Db::name('box_cate')->where('id',$id)->delete();
        return json(['status'=>1,'msg'=>'删除成功']);
    }

    //修改
    public function edit()
    {
        $id = input('id');
        $info = Db::table('box_cate')->where('id',$id)->find();
        $this->assign('info',$info);
        return $this->fetch();
    }

}
