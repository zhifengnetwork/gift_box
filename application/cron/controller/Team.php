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

class Team extends ApiBase{
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
            }/* else if($v['order_type'] == 2){ //群抢
                $this->set_gift_time($Order,$GiftOrderJoin,$v,2);
            } */
        }
    }

    //群抢开奖，每分钟执行一次
    public function lottery(){
        //获取开奖时间100秒以内，且未设置开奖用户的群抢订单
        $Order = M('Order');
        $list = $Order->field('order_id,overdue_time,lottery_time')->where(['order_type'=>2,'lottery_time'=>['between',[time(),time()+120]],'gift_uid'=>0])->select();  
        $GiftOrderJoin = M('gift_order_join');
        foreach($list as $v){
            //开奖推送
            $join_list = $GiftOrderJoin->where(['order_id'=>$v['order_id'],'order_type'=>2,'join_status'=>['neq',4],'push_status'=>0])->column('user_id');
            if($join_list){
                $openid_arr = Db::name('member')->where('id','in',$join_list)->column('id,openid');
                $formid_arr = Db::name('member_formid')->where('user_id','in',$join_list)->where('status',0)->column('user_id,formid');
                foreach($join_list as $key=>$val){
                    $form_id = $formid_arr[$val];
                    $openid = $openid_arr[$val];
                    $order_id = $v['order_id'];
                    $res = $this->news_post($openid,$form_id,$order_id,$v['overdue_time'],$v['lottery_time']);
                    if($res == 'ok'){
                        Db::name('gift_order_join')->where(['order_id'=>$v['order_id'],'user_id'=>$val])->update(['push_status'=>1]);
                        Db::name('member_formid')->where('formid',$form_id)->update(['status'=>1]);
                    }
                }
            }
        } 
    }    

    private function set_gift_time($Order,$GiftOrderJoin,$v){
        $info = $GiftOrderJoin->field('id,address_id')->where(['order_id'=>$v['order_id'],'order_type'=>$v['order_type'],'status'=>1,'join_status'=>['neq',4]])->find();
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
     * form_id      member_form的id
     * order_id     订单id
     * overdue_time 活动结束时间
     *  */
    public function news_post($openid,$form_id,$order_id,$overdue_time,$lottery_time)
    {
        $goods_name = Db::name('order_goods')->where('order_id',$order_id)->value('goods_name');
        $access_token = $this->getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token='.$access_token;
        $data['touser'] = $openid;//openid
        $template_id = Db::name('config')->where('name','template_id')->value('value');
        $data['template_id'] = $template_id;//模板id，
        $overdue_time = M('Order')->where(['order_id'=>$order_id])->value('overdue_time');
        $pwdstr = $this->create_token($order_id,$overdue_time);
        $data['page'] = '/pages/turntable/turntable?order_id='.$order_id.'&pwdstr='.$pwdstr;//跳转地址加参数
        $data['form_id'] = $form_id;//form_id
        //定义模板需要带的参数
        $data['data']['keyword1']['value'] = $goods_name;//商品名称
        $data['data']['keyword2']['value'] = date('Y-m-d H:i:s',$lottery_time);//开奖时间
        $data['data']['keyword3']['value'] = '结束时间'.date('Y-m-d H:i:s',$overdue_time).'，请尽快参与';//注意事项
        $data = json_encode($data);
        $res = request_curl($url,$data);
        $result = json_decode($res, true);
        if($result['errmsg'] == 'ok'){
            return 'ok';
        }
        return 'no';
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
            $expires_in = time()+$jsoninfo["expires_in"];
            Db::name('config')->where('name','access_token')->update(['value'=>$access_token]);
            Db::name('config')->where('name','expires_in')->update(['value'=>$expires_in]);
            return $access_token;
        }else{
            return $access_token;
        }
    }

}