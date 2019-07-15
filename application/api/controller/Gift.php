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
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $order_id = input('order_id/d',0);
        $join_type = input('join_type/d',0); //参与类型，1：领取，2：参与群抢
        
        $pwdstr = input('pwdstr/s',''); //加密字符串
        $arr = $this->decode_token($pwdstr);
        if(!$arr || !$arr['exp'] || ($arr['exp'] < time())){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该链接已失效','data'=>'']);
        }else{  //分享回调接口，user_id化用为id-order_id
            $resarr = explode('-',$arr['user_id']);
            $joinid = 0;
            if(count($resarr) == 2){
                $joinid = $resarr[0];
            }
            if((count($resarr) == 1) && ($order_id != $resarr[0]))
                $this->ajaxReturn(['status' => -1 , 'msg'=>'警告，参数错误！','data'=>'']);
            elseif((count($resarr) == 2) && ($order_id != $resarr[1]))
                $this->ajaxReturn(['status' => -1 , 'msg'=>'警告，参数错误！','data'=>'']);
        }

        $order = Db::name('order')->field('order_status,shipping_status,pay_status,order_type,lottery_time,giving_time,overdue_time,gift_uid')->where(['order_id'=>$order_id,'user_id'=>$user_id,'deleted'=>0])->find();
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
            if($arr['exp'] < $order['giving_time']){
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该链接已失效','data'=>'']);
            }elseif(($order['order_type'] == 1) && ($order['overdue_time'] < time()))
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单赠送已过期啦！','data'=>'']);
            elseif(($order['order_type'] == 2) && ($order['lottery_time'] < time()))
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该群抢已经开奖啦！','data'=>'']);
            elseif(($order['order_type'] == 2) && ($order['overdue_time'] < time()))
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单赠送已过期啦！','data'=>'']);
            elseif($order['gift_uid'])
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单已有领取人啦！','data'=>'']);
        }elseif($order['giving_time'] == 0){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单未赠送！','data'=>'']);
        }

        $data = [
            'order_id'      => $order_id,
            'order_type'    => $joinid ? 3 : $order['order_type'],
            'addtime'       => time(),
            'status'        => ($join_type == 1) ? 1 : 0,
            'user_id'       => $user_id,
            'parentid'      => $joinid,
        ];

        // 启动事务
        if($join_type == 1)Db::startTrans();
        $res = Db::name('gift_order_join')->insertGetId($data);
        if($res){
            if($join_type == 1){
                //领取成功则将 赠送时间，赠送/群抢过期时间，群抢开奖时间 设置为空，以转赠
                M('Order')->where(['order_id'=>$order_id])->update(['lottery_time'=>0,'giving_time'=>0,'overdue_time'=>0,'gift_uid'=>$user_id]);
                Db::name('gift_order_join')->where(['id'=>['neq',$res],'order_id'=>$order_id,'order_type'=>1])->update(['join_status'=>4]);
                // 提交事务
                Db::commit(); 
            }
            $this->ajaxReturn(['status' => 1 , 'msg'=>'请求成功！','data'=>$res]); 
        }else{
            // 回滚事务
            if($join_type == 1)Db::rollback();
            $this->ajaxReturn(['status' => 1 , 'msg'=>'请求失败！','data'=>'']); 
        }
    }

    //添加地址
    public function set_address(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $joinid = input('joinid/d',0); //参与ID
        $addressid = input('addressid/d',0); //地址ID
        $info = M('gift_order_join')->field('id')->where(['status'=>1,'user_id'=>$user_id])->find($joinid);
        if(!$info)
            $this->ajaxReturn(['status' => -1 , 'msg'=>'不存在此次参与','data'=>'']);
        if(!M('user_address')->where(['user_id'=>$user_id])->find($addressid))
            $this->ajaxReturn(['status' => -1 , 'msg'=>'不存在此用户地址','data'=>'']);    

        $res = Db::name('gift_order_join')->where(['id'=>$joinid])->update(['join_status'=>1,'addressid'=>$addressid]);
        if(false !== $res){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'请求成功！','data'=>'']); 
        }else{
            $this->ajaxReturn(['status' => 1 , 'msg'=>'请求失败！','data'=>'']); 
        }
    }

    //分享回调
    public function share_callback(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $order_id = input('order_id/d',0);
        $act = input('act/d',0);  //操作，0回调，1：检测是否可分享，2：转赠检测，3：转赠回调
        
        $where = ['order_id'=>$order_id,'deleted'=>0];
        if(!in_array($act,[2,3]))$where['user_id'] = $user_id;

        $order = Db::name('order')->field('order_status,shipping_status,pay_status,order_type,lottery_time,giving_time,overdue_time,gift_uid')->where($where)->find();
        
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
            elseif($order['gift_uid'])
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单已有领取人啦！','data'=>'']);
        }

        if(in_array($act,[2,3])){ //查看是否可以转赠
            $joininfo = M('gift_order_join')->field('id')->where(['order_id'=>$order_id,'status'=>1,'user_id'=>$user_id,'join_status'=>0,'addressid'=>0])->find();
            
            if(!$joininfo)
                $this->ajaxReturn(['status' => -1 , 'msg'=>'您不能转赠该礼物啦！','data'=>'']);
        }

        if(in_array($act,[1,2]))
            $this->ajaxReturn(['status' => 1 , 'msg'=>'可以分享！','data'=>'']);
        
        //盒子发送多久开奖（分钟）
        $start_time = M('Config')->where(['id'=>50])->value('value'); 
        //盒子发送多久结束（分钟）
        $end_time = M('Config')->where(['id'=>51])->value('value'); 

        $data = [
            'giving_time'   => time(),
        ];

        if($act == 3){
            $order_id = $joininfo['id'] . '-' . $order_id;
        }

        if($order['order_type'] == 1){
            $data['overdue_time'] = (time() + $end_time * 60);
            $pwdstr = $this->create_token($order_id,$data['overdue_time']);
        }if($order['order_type'] == 2){
            $data['lottery_time'] = (time() + $start_time * 60);
            $pwdstr = $this->create_token($order_id,$data['overdue_time']);
        }

        if($act == 3){
            // 启动事务
            Db::startTrans();

            $res = M('gift_order_join')->field('id')->where(['id'=>$joininfo['id']])->update(['join_status'=>5]);
            if($res !== false){
                $r = M('Order')->where(['order'=>$order_id])->update($data);
                // 提交事务
                Db::commit(); 
                $this->ajaxReturn(['status' => 1 , 'msg'=>'操作成功','data'=>$pwdstr]);
            }else{
                // 回滚事务
                Db::rollback();
                $this->ajaxReturn(['status' => -1 , 'msg'=>'操作失败','data'=>$pwdstr]);    
            }
        }

        $r = M('Order')->where(['order'=>$order_id])->update($data);
        if(false !== $r){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'操作成功','data'=>$pwdstr]);
        }else{
            $this->ajaxReturn(['status' => -1 , 'msg'=>'操作失败','data'=>$pwdstr]);    
        }
    }

}