<?php
/**
 * 购物车API
 */
namespace app\api\controller;
use app\common\model\Users;
use app\common\logic\UsersLogic;
use app\common\logic\CartLogic;
use think\Request;
use think\Db;

class Cart extends ApiBase
{
    
    /*
     * 请求获取购物车列表
     */
    public function cartlist()
    {
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $page = input('page',1);
        $num = input('num',10);
        $list = Db::name('cart')->where('user_id',$user_id)->field('id,goods_name,goods_price,goods_num,spec_key_name,sku_id,selected,goods_id')->page($page,$num)->select();
        foreach($list as $key=>$val){
            $val['goods_img'] = Db::name('goods_sku')->where('sku_id',$val['sku_id'])->value('img');
            if(!$val['goods_img']){
                $val['goods_img'] = Db::name('goods_img')->where(['goods_id'=>$val['goods_id'],'main'=>1])->value('picture');
            }
            $val['goods_img'] = $val['goods_img']?SITE_URL.$val['goods_img']:'';
            $list[$key]['goods_img'] = $val['goods_img'];
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //全选
    public function all_select()
    {
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        //如果有未选中的，直接全部选中
        $selected = Db::table('cart')->where('user_id',$user_id)->where('selected',0)->select();
        if($selected){
            $res = Db::name('cart')->where('user_id',$user_id)->where('selected',0)->update(['selected'=>1]);
            $this->ajaxReturn(['status' => 1 , 'msg'=>'全选成功','data'=>'']);
        }else{
            $res = Db::name('cart')->where('user_id',$user_id)->where('selected',1)->update(['selected'=>0]);
            $this->ajaxReturn(['status' => 1 , 'msg'=>'取消全选','data'=>'']);
        }
    }

    /**
     * 购物车总数
     */
    public function cart_sum(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $cart_where['user_id'] = $user_id;
        // $cart_where['groupon_id'] = 0;//团购id
        $num = Db::table('cart')->where($cart_where)->sum('goods_num');

        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$num]);
    }

    /**
     * 加入 | 修改 购物车
     */
    public function addCart()
    {   
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        // input('sku_id/d',0)
        $sku_id       = Request::instance()->param("sku_id", 0, 'intval');
        $cart_number  = Request::instance()->param("cart_number", 1, 'intval');
        // $act = Request::instance()->param('act');

        if( !$sku_id || !$cart_number ){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该商品不存在！','data'=>'']);
        }

        $sku_res = Db::name('goods_sku')->where('sku_id', $sku_id)->field('price,groupon_price,inventory,frozen_stock,goods_id')->find();

        if (empty($sku_res)) {
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该商品不存在！','data'=>'']);
        }

        if ($cart_number > ($sku_res['inventory']-$sku_res['frozen_stock'])) {
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该商品库存不足！','data'=>'']);
        }
       
        $cart_where = array();
        $cart_where['user_id'] = $user_id;
        $cart_where['goods_id'] = $sku_res['goods_id'];
        $cart_where['sku_id'] = $sku_id;
        //判断购物车有没有这件商品
        $cart_res = Db::table('cart')->where($cart_where)->field('id,goods_num')->find();

        if ($cart_res) {
            //购物车内该件商品总数量
            $new_number = $cart_res['goods_num'] + $cart_number;//加或减
            //不懂这什么操作，先注释
            // if($act){
            //     $new_number = $cart_number;
            // }
            //如果是减到这里结束
            if ($new_number <= 0) {
                $result = Db::table('cart')->where('id',$cart_res['id'])->delete();
                $this->ajaxReturn(['status' => 1 , 'msg'=>'该购物车商品已删除！','data'=>'']);
            }
            //如果库存足够，进行下一步操作s
            if ($sku_res['inventory'] >= $new_number) {
                $update_data = array();
                $update_data['id'] = $cart_res['id'];
                $update_data['goods_num'] = $new_number;
                $update_data['subtotal_price'] = $new_number * $sku_res['price'];//小计
                $result = Db::table('cart')->where('id',$update_data['id'])->update($update_data);
                //如果购物车内有该件商品，到这里就完美结束了
                $cart_id = $cart_res['id'];//购物车id
            } else {
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该商品库存不足！','data'=>'']);
            }
        } else {
            $cartData = array();
            $goods_res = Db::name('goods')->where('goods_id',$sku_res['goods_id'])->field('goods_name,price')->find();
            $cartData['goods_id'] = $sku_res['goods_id'];
            $cartData['selected'] = 0;
            $cartData['goods_name'] = $goods_res['goods_name'];
            $cartData['sku_id'] = $sku_id;
            $cartData['user_id'] = $user_id;
            $cartData['goods_price'] = $sku_res['price'];
            $cartData['subtotal_price'] = $cart_number * $sku_res['price'];//小计
            $cartData['goods_num'] = $cart_number;
            $cartData['add_time'] = time();
            $sku_attr = action('Goods/get_sku_str', $sku_id);
            $cartData['spec_key_name'] = $sku_attr;
            $cart_id = Db::table('cart')->insertGetId($cartData);
            $cart_id = intval($cart_id);
        }
        if($cart_id) {
            $this->ajaxReturn(['status' => 1 , 'msg'=>'操作成功！','data'=>$cart_id]);
        } else {
            $this->ajaxReturn(['status' => -1 , 'msg'=>'系统异常！','data'=>'']);
        }
    }

    /**
     * 删除购物车
     */
    public function delCart()
    {   
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $idStr = Request::instance()->param("cart_id", '', 'htmlspecialchars');
        if(!$idStr){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'请选择要删除的购物车','data'=>'']);
        }
        $where['id'] = array('in', $idStr);
        $where['user_id'] = $user_id;
        $cart_res = Db::table('cart')->where($where)->column('id');
        if (empty($cart_res)) {
            $this->ajaxReturn(['status' => -1 , 'msg'=>'购物车不存在！','data'=>'']);
        }
        
        $res = Db::table('cart')->delete($cart_res);
        if ($res) {
            $this->ajaxReturn(['status' => 1 , 'msg'=>'删除成功','data'=>'']);
        } else {
            $this->ajaxReturn(['status' => -1 , 'msg'=>'系统异常！','data'=>'']);
        }
    }

    /**
     * 选中状态
     */
    public function selected(){
        $user_id = $this->get_user_id();
        $cart_id = Request::instance()->param("cart_id", '', 'htmlspecialchars');
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        if(!$cart_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'购物车不存在！','data'=>'']);
        }
        $selected = Db::table('cart')->where('id',$cart_id)->value('selected');
        if(!$selected && $selected != 0){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'购物车不存在！','data'=>'']);
        }

        if($selected){
            $res = Db::table('cart')->where('id',$cart_id)->update(['selected'=>0]);
            $this->ajaxReturn(['status' => 1 , 'msg'=>'取消选中','data'=>'']);
        }else{
            $res = Db::table('cart')->where('id',$cart_id)->update(['selected'=>1]);
            $this->ajaxReturn(['status' => 1 , 'msg'=>'成功选中','data'=>'']);
        }
    }

}
