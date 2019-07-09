<?php
namespace app\admin\controller;

use think\Db;
use think\Request;

/**
 * 电子礼盒
 */
class Turntable extends Common
{
    //主页
    public function index()
    {
        $list = array();
        $this->assign('list',$list);
        return $this->fetch();
    }
    
    // 俏皮话列表
    public function joke_list()
    {
        $list = Db::table('turntable_joke')->paginate(10);
        $this->assign('list',$list);
        return $this->fetch();
    }

    // 添加俏皮话
    public function add_joke()
    {
        if(Request::instance()->isPost()){
            $id = input('post.id');
            $post = input('post.');
            if(!$post['content']){
                $this->error('请输入内容');
            }
            if($id){
                Db::name('turntable_joke')->where('id',$id)->update($post);
            }else{
                $post['addtime'] = time();
                Db::name('turntable_joke')->insert($post);
            }
            $this->success('操作成功',url('joke_list'));
        }
        $id = input('id');
        if($id){
            $info = Db::name('turntable_joke')->where('id',$id)->find();
        }else{
            $info = getTableField('turntable_joke');
        }
        $this->assign('info',$info);
        return $this->fetch();
    }
}
