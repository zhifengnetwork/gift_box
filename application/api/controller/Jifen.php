<?php
namespace app\api\controller;

use think\Db;
use think\Loader;
use think\Request;
use think\Session;
use think\captcha\Captcha;
use app\common\util\jwt\JWT;
use app\common\model\Member as MemberModel;

class Jifen extends ApiBase
{

    /**
     * 积分支付
     */
    public function order_jifen_pay(){

        $order_id     = input('order_id');
       

        $user_id      = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }


        $pay_type     = input('pay_type');//支付方式


        $order_info   = Db::name('order')->where(['order_id' => $order_id])->field('order_id,groupon_id,order_sn,order_amount,pay_type,pay_status,user_id')->find();
        //订单信息
        if($order_info){
            //从订单列表立即付款进来
            $pay_type     = $order_info['pay_type'];//支付方式
        }
        $member       = MemberModel::get($user_id);
        //验证是否本人的
        if(!$order_info){
            $this->ajaxReturn(['status' => -3 , 'msg'=>'订单不存在','data'=>'']);
        }
        if($order_info['user_id'] != $user_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'非本人订单','data'=>'']);
        }

    	if($order_info['pay_status'] == 1){
			$this->ajaxReturn(['status' => -4 , 'msg'=>'此订单，已完成支付!','data'=>'']);
        }


        //积分支付
        //TODO
        
        $card_mobile = I('card_mobile');
        $card_name = I('card_name');
        $card_num = I('card_num');

        Db::name('order')->where(['order_id' => $order_id])->update(['pay_status'=>1,'transaction_id'=>'jifen']);

        $this->ajaxReturn(['status' => 1 , 'msg'=>'支付成功!','data'=>'']);
    }

}