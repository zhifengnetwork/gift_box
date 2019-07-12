<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/13 0013
 * Time: 10:20
 */

namespace app\cron\controller;

use think\Controller;
use think\Db;
use app\common\util\Exception;

class Team extends Controller{
    /**
     * 执行方法
     */
    public function run()
    {
        $this->lottery();   //群抢开奖
    }

    //检测赠送过期时间，每分钟执行一次
    public function setGiving(){
        $Order = M('Order');
        $list = $Order->field('order_id,order_type,gift_uid')->where(['order_type'=>['neq',0],'overdue_time'=>['between',[time()-100,time()]]])->select();
        
        foreach($list as $v){
            if($v['order_type'] == 1){ //赠送单人
                if(!$v['gift_uid']){  //无人领取
                    $Order->where(['order_id'=>$v['order_id']])->update(['lottery_time'=>0,'giving_time'=>0,'overdue_time'=>0]);
                }
            }
        }
    }

    //群抢开奖，每分钟执行一次
    public function lottery(){
        //获取开奖时间100秒以内，且未设置开奖用户的群抢订单
        $Order = M('Order');
        $list = $Order->field('order_id')->where(['order_type'=>2,'lottery_time'=>['between',[time()-100,time()]],'gift_uid'=>0])->select();  

        $GiftOrderJoin = M('gift_order_join');
        foreach($list as $v){
            $num = $GiftOrderJoin->where(['order_id'=>$v['order_id'],'order_type'=>2])->count();
            if($num == 0){  //无人参与
                $Order->where(['order_id'=>$v['order_id']])->update(['lottery_time'=>0,'giving_time'=>0,'overdue_time'=>0]);
            }elseif($num == 1){  //只有一人参与
                $info = $GiftOrderJoin->field('id,user_id')->where(['order_id'=>$v['order_id'],'order_type'=>2])->find();
                $Order->where(['order_id'=>$v['order_id']])->update(['gift_uid'=>$info['user_id']]);
                $GiftOrderJoin->where(['id'=>$info['id']])->update(['status'=>1]);
            }elseif($num > 1){  //多人参与
                //查看有无内定
                $info = $GiftOrderJoin->field('id,user_id')->where(['order_id'=>$v['order_id'],'order_type'=>2,'status'=>1])->find();
                if(!$info){
                    //随机取一条
                    $n = rand(1,$num);  
                    $info = $GiftOrderJoin->field('id,user_id')->where(['order_id'=>$v['order_id'],'order_type'=>2])->limit($n-1,1)->find();
                }
                $Order->where(['order_id'=>$v['order_id']])->update(['gift_uid'=>$info['user_id']]);
                $GiftOrderJoin->where(['id'=>$info['id']])->update(['status'=>1]);
                
            }
        } 
    }

}