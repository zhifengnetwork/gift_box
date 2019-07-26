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
        //$this->lottery();   //群抢开奖
        $this->setGiving();   ///检测过期时间
        M('A')->add(['msg'=>date('Y-m-d H:i:s',time())]);
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
            }/* else if($v['order_type'] == 2){ //群抢
                $this->set_gift_time($Order,$GiftOrderJoin,$v,2);
            } */
        }
    }

    //群抢开奖，每分钟执行一次
    /* $wx_content = "订单支付成功！\n\n订单：{$order_sn}\n支付时间：{$time}\n商户：凡露希环球直供\n金额：{$order['total_amount']}\n\n【凡露希环球直供】欢迎您的再次购物！";
    $wechat = new \app\common\logic\wechat\WechatUtil();
    $wechat->sendMsg($userinfo['openid'], 'text', $wx_content);   */  
    public function lottery(){
        //获取开奖时间100秒以内，且未设置开奖用户的群抢订单
        $Order = M('Order');
        $list = $Order->field('order_id')->where(['order_type'=>2,'lottery_time'=>['between',[time()-60,time()]],'gift_uid'=>0])->select();  

        $GiftOrderJoin = M('gift_order_join');
        foreach($list as $v){
            $num = $GiftOrderJoin->where(['order_id'=>$v['order_id'],'order_type'=>2,'join_status'=>['neq',4]])->count();
            if($num == 0){  //无人参与
                $Order->where(['order_id'=>$v['order_id']])->update(['lottery_time'=>0,'giving_time'=>0,'overdue_time'=>0]);
            }elseif($num == 1){  //只有一人参与
                $info = $GiftOrderJoin->field('id,user_id')->where(['order_id'=>$v['order_id'],'order_type'=>2,'join_status'=>['neq',4]])->find();
                $this->set_gift_time1($Order,$GiftOrderJoin,$v,$info);
            }elseif($num > 1){  //多人参与
                //查看有无内定
                $info = $GiftOrderJoin->field('id,user_id')->where(['order_id'=>$v['order_id'],'order_type'=>2,'status'=>1,'join_status'=>['neq',4]])->find();
                if(!$info){
                    //随机取一条
                    $n = rand(1,$num);  
                    $info = $GiftOrderJoin->field('id,user_id')->where(['order_id'=>$v['order_id'],'order_type'=>2,'join_status'=>['neq',4]])->limit($n-1,1)->find();
                }
                $this->set_gift_time1($Order,$GiftOrderJoin,$v,$infos);
            }

            //开奖推送
            // $join_list = $GiftOrderJoin->where(['order_id'=>$v['order_id'],'order_type'=>2,'join_status'=>['neq',4]])->column('user_id');
            // if($join_list){
            //     $appid = Db::name('member')->where('id','in',$join_list)->column('id,');
            // }
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

            //中奖推送
        }catch(Exception $t) {
            // 回滚事务
            Db::rollback();
        } 
    }        

    /**
     * 消息推送
     * appid        
     * form_id      member_form的id
     * order_id     订单id
     * overdue_time 活动结束时间
     *  */
    public function news_post($appid,$form_id,$order_id,$overdue_time)
    {
        $access_token = $this->getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token='.$access_token;
        $data['touser'] = $appid;//openid
        $template_id = Db::name('config')->where('name','template_id')->value('value');
        $data['template_id'] = $template_id;//模板id，
        $data['page'] = '/pages/turntable/turntable?order_id='.$order_id;//跳转地址加参数
        $data['form_id'] = $form_id;//form_id
        //定义模板需要带的参数
        $data['data']['keyword1']['value'] = '您所期待的抽奖已经开始了，请尽快参与';
        $data['data']['keyword2']['value'] = '不要错过时间哦';
        $data['data']['keyword3']['value'] = $overdue_time;
        $data = json_encode($data);
        $res = request_curl($url,$data);
        $result = json_decode($res, true);
        if($result['errmsg'] == 'ok'){
            return true;
        }
    }

    //获取AccessToken
    public function getAccessToken(){
        $access_token = Db::name('config')->where('name','access_token')->value('value');
        $expires_in = Db::name('config')->where('name','expires_in')->value('value');
        if(time() > $expires_in){
            $appid = Db::name('config')->where(['name'=>'appid'])->value('value');
            $appsecret = Db::name('config')->where(['name'=>'appsecret'])->value('value');
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); 
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $output = curl_exec($ch);
            curl_close($ch);
            $jsoninfo = json_decode($output, true);
            $access_token = $jsoninfo["access_token"];
            $expires_in = time().$jsoninfo["expires_in"];
            Db::name('config')->where('name','access_token')->update(['value'=>$access_token]);
            Db::name('config')->where('name','expires_in')->update(['value'=>$expires_in]);
            return $access_token;
        }else{
            return $access_token;
        }
    }

}