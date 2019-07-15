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
        $this->setGiving();   ///检测过期时间
    }

    //检测赠送过期时间，每分钟执行一次
    public function setGiving(){
        $Order = M('Order');
        $list = $Order->field('order_id,order_type,overdue_time,gift_uid')->where(['order_type'=>['neq',0],'overdue_time'=>['between',[time()-100,time()]]])->select();
        
        $GiftOrderJoin = M('gift_order_join');
        foreach($list as $v){
            if($v['order_type'] == 1){ //赠送单人
                if(!$v['gift_uid']){  //无人领取
                    $Order->where(['order_id'=>$v['order_id']])->update(['lottery_time'=>0,'giving_time'=>0,'overdue_time'=>0]);
                }else{ 
                    //有人领取时
                    $this->set_gift_time($Order,$GiftOrderJoin,$v,1);
                }
            }else if($v['order_type'] == 2){ //群抢
                $this->set_gift_time($Order,$GiftOrderJoin,$v,2);
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
            $num = $GiftOrderJoin->where(['order_id'=>$v['order_id'],'order_type'=>2,'join_status'=>['neq',4]])->count();
            if($num == 0){  //无人参与
                $Order->where(['order_id'=>$v['order_id']])->update(['lottery_time'=>0,'giving_time'=>0,'overdue_time'=>0]);
            }elseif($num == 1){  //只有一人参与
                $info = $GiftOrderJoin->field('id,user_id')->where(['order_id'=>$v['order_id'],'order_type'=>2,'join_status'=>['neq',4]])->find();
                $this->set_gift_time1($Order,$GiftOrderJoin,$v);
            }elseif($num > 1){  //多人参与
                //查看有无内定
                $info = $GiftOrderJoin->field('id,user_id')->where(['order_id'=>$v['order_id'],'order_type'=>2,'status'=>1,'join_status'=>['neq',4]])->find();
                if(!$info){
                    //随机取一条
                    $n = rand(1,$num);  
                    $info = $GiftOrderJoin->field('id,user_id')->where(['order_id'=>$v['order_id'],'order_type'=>2,'join_status'=>['neq',4]])->limit($n-1,1)->find();
                }
                $this->set_gift_time1($Order,$GiftOrderJoin,$v);
            }
        } 
    }    

    private function set_gift_time($Order,$GiftOrderJoin,$v){
        $info = $GiftOrderJoin->field('id,address_id')->where(['order_id'=>$v['order_id'],'order_type'=>order_type,'status'=>1,'join_status'=>['neq',4]])->find();
        //已过期且没填地址
        if(($v['overdue_time'] < time()) && !$info['address_id']){ 
            // 启动事务
            Db::startTrans();
            try{
                $Order->where(['order_id'=>$v['order_id']])->update(['lottery_time'=>0,'giving_time'=>0,'overdue_time'=>0,'gift_uid'=>0]);
                $GiftOrderJoin->where(['id'=>$info['id']])->update(['join_status'=>4]);
                // 提交事务
                Db::commit(); 
            }catch(Exception $t) {
                // 回滚事务
                Db::rollback();
            }
        }
    }    

    private function set_gift_time1($Order,$GiftOrderJoin,$v){
        // 启动事务
        Db::startTrans();
        try{
            //领取成功则将 赠送时间，赠送/群抢过期时间，群抢开奖时间 设置为空，以转赠
            $Order->where(['order_id'=>$v['order_id']])->update(['lottery_time'=>0,'giving_time'=>0,'overdue_time'=>0,'gift_uid'=>$info['user_id']]);
            $GiftOrderJoin->where(['id'=>$info['id']])->update(['status'=>1]);
            // 提交事务
            Db::commit(); 
        }catch(Exception $t) {
            // 回滚事务
            Db::rollback();
        } 
    }        

}