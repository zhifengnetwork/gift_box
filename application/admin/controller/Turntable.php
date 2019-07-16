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
        $list = Db::name('order')->field('box_id,order_id,overdue_time,lottery_time')->where('order_type',2)->where('pay_status',1)->paginate(10)->each(function($v,$k){
            $v['goods_num'] = Db::name('order_goods')->where('order_id',$v['order_id'])->value('goods_num');
            return $v;
        });
        $this->assign('list',$list);
        return $this->fetch();
    }

    //设置中奖名单
    public function edit()
    {   
        $order_id = input('order_id',0);
        if(Request::instance()->isPost()){
            $post = input('post.');
            $ids = $post['ids'];
            $goods_num = Db::name('order_goods')->where('order_id',$order_id)->value('goods_num');
            $end_time = Db::name('order')->where('order_id',$order_id)->value('overdue_time');
            if(time() >= $end_time){
                $this->error('群抢时间已结束，不能设置中奖名单');
            }
            if(!$ids){
                $this->error('请勾选中奖的名单');
            }
            if(count($ids)>$goods_num){
                $this->error('中奖人数不能大于购买数量');
            }
            $res = Db::name('gift_order_join')->where('order_id',$order_id)->where('user_id','in',$ids)->update(['status'=>1]);
            if($res){
                $this->success('设置中奖名单成功',url('index'));
            }else{
                $this->error('设置中奖名单失败');
            }
        }
        $list = Db::name('gift_order_join')->alias('g')->field('m.nickname,m.id,g.status')->join('member m','g.user_id=m.id')->where('g.order_id',$order_id)->where('m.nickname','neq','')->select();
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
