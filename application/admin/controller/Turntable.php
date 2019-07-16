<?php
namespace app\admin\controller;

use think\Db;
use think\Request;

/**
 * 电子礼盒
 */
class Turntable extends Common
{
    //
    public function index()
    {
        $list = array();
        $list = Db::name('order')->field('box_id,order_id,overdue_time,lottery_time')->where('order_type',2)->where('pay_status',1)->paginate(10);
        $this->assign('list',$list);
        return $this->fetch();
    }

    //edit
    public function edit()
    {   
        if(Request::instance()->isPost()){
            $ids = input('ids');
        }
        $order_id = input('order_id',0);
        $list = Db::name('gift_order_join')->alias('g')->join('member m','g.user_id=m.id')->where('order_id',$order_id)->select();
        $this->assign('list',$list);
        $this->assign('order_id',$order_id);
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
