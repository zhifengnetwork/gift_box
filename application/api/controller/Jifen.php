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
        $order_id     = input('order_id',0);
        $user_id      = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $pay_type     = input('pay_type');//支付方式
        $order_info   = Db::name('order')->where(['order_id' => $order_id])->field('order_id,groupon_id,order_sn,order_amount,pay_type,pay_status,user_id,parent_id')->find();
        if($order_info['parent_id']){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'不能支付子订单','data'=>'']);
        }
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
        if(!$card_num){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'请输入vip卡号!','data'=>'']);
        }
        if(!$card_name){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'请输入会员名字!','data'=>'']);
        }
        if(!$card_mobile){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'请输入手机号码!','data'=>'']);
        }
        if(!preg_match("/^1[3456789]{1}\d{9}$/",$card_mobile)){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'手机号码格式错误!','data'=>'']);
        }
        $result = Db::name('order_examine')->where('order_id',$order_id)->count();
        if($result){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'该订单已经提交审核!','data'=>'']);
        }
        $data['card_mobile'] = $card_mobile;
        $data['card_name'] = $card_name;
        $data['card_num'] = $card_num;
        $data['order_id'] = $order_id;
        $data['addtime'] = time();
        $data['status'] = 0;


        //  积分审核,先支付成功
        $result = Db::name('order')->where(['order_id' => $order_id])->update(['pay_status'=>1,'transaction_id'=>'jifen']);
        
        // if($result){
        //     $goods_res = Db::table('order_goods')->field('goods_id,user_id,goods_name,goods_num,spec_key_name,goods_price,sku_id')->where('order_id',$order_id)->select();
        //     foreach($goods_res as $key=>$value){
        //         //付款减库存
        //         Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setDec('inventory',$value['goods_num']);
        //         Db::table('goods')->where('goods_id',$value['goods_id'])->setDec('stock',$value['goods_num']);
        //     }
        // }
        
        $res = Db::name('order_examine')->insert($data);
        if($res){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'提交审核成功!','data'=>'']);
        }else{
            $this->ajaxReturn(['status' => -1 , 'msg'=>'提交审核失败!','data'=>'']);
        }
    }

}