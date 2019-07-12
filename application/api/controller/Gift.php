<?php
/**
 * 订单API
 */
namespace app\api\controller;
use app\common\logic\PointLogic;
use think\Db;
use app\api\controller\Goods;
use think\Request;

class Gift extends ApiBase
{
    //领取/参与
    public function receive_join(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在','data'=>'']);
        }

        $order_id = input('order_id/d',0);
        $join_type = input('join_type/d',0); //参与类型，1：领取，2：参与群抢

        $order = Db::name('order')->field('order_status,shipping_status,pay_status,order_type,lottery_time,giving_time,overdue_time')->where(['order_id'=>$order_id,'user_id'=>$user_id,'deleted'=>0])->find();
        if(!$order){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'订单不存在','data'=>'']);
        }elseif($order['pay_status'] != 1){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'订单还未支付','data'=>'']);
        }elseif(!in_array($order['order_status'],[0,1])){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单状态已不允许执行此操作','data'=>'']);
        }elseif($order['order_type'] == 0){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单不是赠送订单','data'=>'']);
        }elseif(($order['order_type'] == 1) && ($join_type != 1)){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'参与类型不符合','data'=>'']);
        }elseif(($order['order_type'] == 2) && ($join_type != 2)){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'参与类型不符合','data'=>'']);
        }elseif($order['giving_time'] > 0){
            if(($order['order_type'] == 1) && ($order['overdue_time'] > time()))
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单赠送已过期啦！','data'=>'']);
            elseif(($order['order_type'] == 2) && ($order['lottery_time'] > time()))
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该群抢已经开奖啦！','data'=>'']);
            elseif(($order['order_type'] == 2) && ($order['overdue_time'] > time()))
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单赠送已过期啦！','data'=>'']);
        }elseif($order['giving_time'] == 0){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单未赠送！','data'=>'']);
        }

        $data = [
            'order_id'      => $order_id,
            'order_type'    => $order['order_type'],
            'addtime'       => time(),
            'status'        => ($join_type == 1) ? 1 : 0,
            'user_id'       => $user_id
        ];

        $res = Db::name('gift_order_join')->insertGetId($data);
        if($res){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'请求成功！','data'=>'']); 
        }else{
            $this->ajaxReturn(['status' => 1 , 'msg'=>'请求失败！','data'=>'']); 
        }
    }

    //分享回调
    public function share_callback(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在','data'=>'']);
        }

        $order_id = input('order_id/d',0);
        $order = Db::name('order')->field('order_status,shipping_status,pay_status,order_type,lottery_time,giving_time,overdue_time')->where(['order_id'=>$order_id,'user_id'=>$user_id,'deleted'=>0])->find();
        
        if(!$order){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'订单不存在','data'=>'']);
        }elseif($order['pay_status'] != 1){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'订单还未支付','data'=>'']);
        }elseif(!in_array($order['order_status'],[0,1])){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单状态已不允许执行此操作','data'=>'']);
        }elseif($order['order_type'] == 0){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单不是赠送订单','data'=>'']);
        }elseif($order['giving_time'] > 0){
            if(($order['order_type'] == 1) && ($order['overdue_time'] < time()))
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单已赠送过啦！','data'=>'']);
            elseif(($order['order_type'] == 2) && ($order['lottery_time'] < time()))
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单已赠送过啦！','data'=>'']);
            elseif(($order['order_type'] == 2) && ($order['overdue_time'] < time()))
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单已赠送过啦！','data'=>'']);
        }
        
        //盒子发送多久开奖（分钟）
        $start_time = M('Config')->where(['id'=>50])->value('value'); 
        //盒子发送多久结束（分钟）
        $end_time = M('Config')->where(['id'=>51])->value('value'); 

        $data = [
            'giving_time'   => time(),
        ];
        if($order['order_type'] == 1) 
            $data['overdue'] = (time() + $end_time * 60);
        if($order['order_type'] == 2) 
            $data['lottery_time'] = (time() + $start_time * 60);

        $res = M('Order')->where(['order'=>$order_id])->update($data);
        if(false !== $res){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'操作成功','data'=>'']);
        }else{
            $this->ajaxReturn(['status' => -1 , 'msg'=>'操作失败','data'=>'']);    
        }
    }

}