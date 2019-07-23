<?php
namespace app\api\controller;
use Payment\Notify\PayNotifyInterface;
use Payment\Config;
use think\Loader;
use think\Db;

/**
 * @author: helei
 * @createTime: 2016-07-20 18:31
 * @description:
 */

/**
 * 客户端需要继承该接口，并实现这个方法，在其中实现对应的业务逻辑
 * Class TestNotify
 * anthor helei
 */
class TestNotify implements PayNotifyInterface
{
    public function notifyProcess(array $data)
    {
        // $channel = $data['channel'];
        if(substr($data['order_no'],0,1) != 'C'){
            //修改订单状态
            $update = [
                // 'seller_id'      => $data['seller_id'],
                'transaction_id' => $data['transaction_id'],
                'order_status'   => 1,
                'pay_status'     => 1,
                'pay_time'       => strtotime($data['pay_time']),
            ];
            Db::startTrans();
            Db::name('order')->where(['order_sn' => $data['order_no']])->update($update);
            //修改子订单
            $order_id = Db::name('order')->where(['order_sn' => $data['order_no']])->value('order_id');
            Db::name('order')->where(['parent_id' => $order_id])->update($update);

            $order = Db::table('order')->where(['order_sn' => $data['order_no']])->field('order_id,user_id')->find();
            $goods_res = Db::table('order_goods')->field('goods_id,goods_name,goods_num,spec_key_name,goods_price,sku_id')->where('order_id',$order['order_id'])->select();
            foreach($goods_res as $key=>$value){
                $goods = Db::table('goods')->where('goods_id',$value['goods_id'])->field('less_stock_type,gift_points')->find();
                //付款减库存
                // if($goods['less_stock_type']==2){
                    Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setDec('inventory',$value['goods_num']);
                    Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setDec('frozen_stock',$value['goods_num']);
                    Db::table('goods')->where('goods_id',$value['goods_id'])->setDec('stock',$value['goods_num']);
                // }
            }
            // 执行业务逻辑，成功后返回true
            return true;
        }else{
            //修改订单状态
            $update = [
                // 'seller_id'      => $data['seller_id'],
                'transaction_id' => $data['transaction_id'],
                'status'   => 1,
                // 'pay_status'     => 1,
                'pay_time'       => strtotime($data['pay_time']),
            ];
            //订单详情
            $order = Db::name('member_order')->field('id,status,order_sn,user_id,money')->where('order_sn',$data['order_no'])->find();
            if($order['status'] == 0){
                $res = Db::name('member_order')->where('id',$order['id'])->update($update);
                if($res){
                    $result = Db::name('member')->where('id',$order['user_id'])->setInc('shop_card_balance',$order['money']);
                }
            }
            if($res){
                return true;
            }
        }
    }
}