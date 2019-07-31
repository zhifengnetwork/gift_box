<?php
/**
 * 订单API
 */
namespace app\api\controller;
use app\common\logic\PointLogic;
use think\Db;
use app\api\controller\Goods;
use think\Request;

class Order extends ApiBase
{


    /**
     * 购物车提交订单
     */
    public function temporary()
    {
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        //购物车商品
        $address_id = input('address_id/d',0);
       
		//$cart_where['id'] = array('in',$idStr);
        $cart_where['selected']=1;
        $cart_where['user_id'] = $user_id;
        $cartM = model('Cart');
        $cart_res = $cartM->cartList_no($cart_where);
        if(!$cart_res){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'购物车商品不存在！','data'=>'']);
        }

        // 查询地址
        $addr_data['ua.user_id'] = $user_id;
        if($address_id){
            $addr_data['ua.address_id'] = $address_id;
        }
        $addressM = Model('UserAddr');
        $addr_res = $addressM->getAddressList($addr_data);
        $addr_list=[];
        if($addr_res){
            foreach($addr_res as $key=>$value){
                $addr = $value['p_cn'] . $value['c_cn'] . $value['d_cn'] . $value['s_cn'];
                $addr_res[$key]['address'] = $addr . $addr_res[$key]['address'];
                unset($addr_res[$key]['p_cn'],$addr_res[$key]['c_cn'],$addr_res[$key]['d_cn'],$addr_res[$key]['s_cn']);
                if(!$addr_list||$value['is_default']){
                    $addr_list=$value;
                }
            }
        }
        $data['goods'] = $cart_res;
        $data['addr_res'] = $addr_list;

        $pay = Db::table('sysset')->value('sets');
        $pay = unserialize($pay)['pay'];

        $pay_type = config('PAY_TYPE');
        $arr = [];
        $i = 0;
        foreach($pay as $key=>$value){
            if($value){
                $arr[$i]['pay_type'] = $pay_type[$key]['pay_type'];
                $arr[$i]['pay_name'] = $pay_type[$key]['pay_name'];
                $i++;
            }
        }

        $data['pay_type'] = $arr;

        $order_amount = '0'; //订单价格
        $taxes = 0;     //优惠额
        $discount = 0;  //税费
        $shipping_price = 0;
        $cart_goods_arr = [];
        $all_num=0;
        foreach($data['goods'] as $key=>$value){

			//$data['goods'][$key]['img']=SITE_URL.Config('c_pub.img').$data['goods'][$key]['img'];
            if( !in_array($value['goods_id'],$cart_goods_arr) ){
                $cart_goods_arr[] = $value['goods_id'];

                //处理运费
                $goods_res = Db::table('goods')->field('shipping_setting,shipping_price,delivery_id,less_stock_type')->where('goods_id',$value['goods_id'])->find();
                if($goods_res['shipping_setting'] == 1){
                    $shipping_price = sprintf("%.2f",$shipping_price + $goods_res['shipping_price']);   //计算该订单的物流费用
                }else if($goods_res['shipping_setting'] == 2){ 
                    if( !$goods_res['delivery_id'] ){
                        $deliveryWhere['is_default'] = 1;
                    }else{
                        $deliveryWhere['delivery_id'] = $goods_res['delivery_id'];
                    }
                    $delivery = Db::table('goods_delivery')->where($deliveryWhere)->find();
                    if( $delivery ){
                        if($delivery['type'] == 2){
                            $shipping_price = sprintf("%.2f",$shipping_price + $delivery['firstprice']);   //计算该订单的物流费用
                            $number = $value['goods_num'] - $delivery['firstweight'];
                            if($number > 0){
                                $number = ceil( $number / $delivery['secondweight'] );  //向上取整
                                $xu = sprintf("%.2f",$delivery['secondprice'] * $number );   //续价
                                $shipping_price = sprintf("%.2f",$shipping_price + $xu);   //计算该订单的物流费用
                            }
                        }
                    }
                }
                $discount += ($value['discount'] * $value['goods_num']);
                $taxes += floor(($value['goods_price'] - $value['discount']) * $value['taxes'] * $value['goods_num'])/100;
                $order_amount = sprintf("%.2f",$order_amount + $value['subtotal_price']);   //计算该订单的总价

            }
            $all_num=$all_num+$value['goods_num'];
        }


        $balance = Db::name('member')->where(['id' => $user_id])->value('balance');
        $data['balance']=$balance;
        $data['order_amount']=$order_amount;
        $data['discount_amount'] = $discount;
        $data['taxes_amount'] = $taxes;
        $data['order_num']=$all_num;
        $data['goods'] = array_values($data['goods']);
        $data['shipping_price'] = $shipping_price;  //该订单的物流费用
       

        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$data]);
    }

    /**
     * 立即购买
     */
    public function immediatelyOrder()
    {
        $user_id = $this->get_user_id();
        // $user_id = 88;
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $sku_id       = input('sku_id/d', 0);
        $cart_number  = input('cart_number/d', 0);
        $act = input('act', '');

        if( !$sku_id || !$cart_number ){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该商品不存在！','data'=>'']);
        }

        $sku_res = Db::name('goods_sku')->where('sku_id', $sku_id)->field('price,groupon_price,inventory,frozen_stock,goods_id')->find();

        if (empty($sku_res)) {
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该商品不存在！','data'=>'']);
        }
        if ($cart_number > $sku_res['inventory']) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'该商品库存不足！','data'=>'']);
        }
        $goods = Db::table('goods')->where('goods_id',$sku_res['goods_id'])->field('single_number,most_buy_number,stock')->find();
        if($cart_number>$goods['stock']){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'该商品库存不足！','data'=>'']);
        }
        $goods = Db::table('goods')->where('goods_id',$sku_res['goods_id'])->field('single_number,most_buy_number,taxes,discount')->find();


        $cart_where = array();
        $cart_where['user_id'] = $user_id;
        $cart_where['goods_id'] = $sku_res['goods_id'];


        $cart_where['sku_id'] = $sku_id;
        $cart_res = Db::table('cart')->where($cart_where)->field('id,goods_num,subtotal_price')->find();
        Db::table('cart')->where('user_id',$user_id)->update(['selected'=>0]);
        if ($cart_res) {
            $cart_data['selected']=1;
            $cart_data['goods_num']=$cart_number;
            $cart_data['taxes'] = $goods['taxes'];
            $cart_data['discount'] = $goods['discount'];
            $cart_data['subtotal_price'] = ($cart_number*$sku_res['price']) - $cart_number * $goods['discount'];
            $cart_data['subtotal_price'] += (floor(($sku_res['price']-$goods['discount']) * $goods['taxes'] * $cart_number)/100);
            Db::table('cart')->where($cart_where)->update($cart_data);
            $cart_id=$cart_res['id'];
        } else {
            $cartData = array();
            $goods_res = Db::name('goods')->where('goods_id',$sku_res['goods_id'])->field('goods_name,price,original_price')->find();
            $cartData['goods_id'] = $sku_res['goods_id'];
            $cartData['selected'] = 1;
            $cartData['goods_name'] = $goods_res['goods_name'];
            $cartData['sku_id'] = $sku_id;
            $cartData['user_id'] = $user_id;
            $cartData['market_price'] = $goods_res['original_price'];
            $cartData['goods_price'] = $sku_res['price'];
            $cartData['member_goods_price'] = $sku_res['price'];
            $cartData['subtotal_price'] = $cart_number * $sku_res['price'] - $cart_number * $goods['discount'];
            $cartData['subtotal_price'] += (floor(($sku_res['price']-$goods['discount']) * $goods['taxes'] * $cart_number)/100);
            $cartData['goods_num'] = $cart_number;
            $cartData['add_time'] = time();
            $cartData['taxes'] = $goods['taxes'];
            $cartData['discount'] = $goods['discount'];
            $sku_attr = action('Goods/get_sku_str', $sku_id);
            $cartData['spec_key_name'] = $sku_attr;
            $cart_id = Db::table('cart')->insertGetId($cartData);
            $cart_id = intval($cart_id);
        }
        if($cart_id) {
            $this->ajaxReturn(['status' => 1 , 'msg'=>'成功！','data'=>$cart_id]);
        } else {
            $this->ajaxReturn(['status' => -1 , 'msg'=>'系统异常！','data'=>'']);
        }
    }    

    /**
     * 提交订单
     */
    public function submitOrder()
    {
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $addr_id = input("address_id/d",0);
        $pay_type = input("pay_type/d",0);
        $order_type = input("order_type/d",0); //订单类型，0犒劳自己，1：赠送单人，2：群抢
        $user_note = input("user_note", '', '');
        // 查询地址是否存在
        $AddressM = model('UserAddr');

        $invoice_title = I('post.invoice_title/s',''); //发票抬头
        $taxpayer = I('post.taxpayer/s',''); //纳税人识别号
        $invoice_desc = I('post.invoice_desc/s',''); //发票内容
        $invoice_mobile = I('post.invoice_mobile/s',''); //收票人手机
        $invoice_email = I('post.invoice_email/s','');  //收票人邮箱

        //有可能传invoice_id
        $invoice_id = I('invoice_id');
        if($invoice_id){
            $invoice_array = M('invoice')->where(['user_id'=>$user_id])->find();

            $invoice_title = $invoice_array['invoice_title']; //发票抬头
            $taxpayer = $invoice_array['taxpayer']; //纳税人识别号
            $invoice_desc = $invoice_array['invoice_desc']; //发票内容
            $invoice_mobile = $invoice_array['invoice_mobile']; //收票人手机
            $invoice_email = $invoice_array['invoice_email'];  //收票人邮箱
        }

        $box_id = I('post.box_id',0);  //礼盒id
        if($invoice_desc && !$invoice_mobile)
            $this->ajaxReturn(['status' => -1 , 'msg'=>'请填写收票人手机！','data'=>'']);
        if(!$invoice_desc && $invoice_mobile)
            $this->ajaxReturn(['status' => -1 , 'msg'=>'请填写发票内容！','data'=>'']);
        //7月22修改
        if(!$order_type && !$addr_id){
            $addr_id = Db::name('user_address')->where('user_id',$user_id)->where('is_default',1)->value('address_id');
            // $this->ajaxReturn(['status' => -1 , 'msg'=>'','data'=>'犒劳自己需要填写地址']);
        }
        $addrWhere = array();
        $addr_res = array();
        $addr_res['consignee'] = '';
        $addr_res['province'] = '';
        $addr_res['city'] = '';
        $addr_res['district'] = '';
        $addr_res['twon'] = '';
        $addr_res['address'] = '';
        $addr_res['mobile'] = '';
        if($addr_id){
            $addrWhere['address_id'] = $addr_id;
            $addrWhere['user_id'] = $user_id;
            $addr_res = $AddressM->getAddressFind($addrWhere);

            if (empty($addr_res)) {
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该地址不存在！','data'=>'']);
            }
        }
        //购物车商品
        $cart_where['selected']=1;
        $cart_where['user_id'] = $user_id;
        $cartM = model('Cart');
        $cart_res = $cartM->cartList2($cart_where);
        if(!$cart_res){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'购物车商品不存在！','data'=>'']);
        }
        if((count($cart_res) > 1) && ($order_type == 2))
            $this->ajaxReturn(['status' => -1 , 'msg'=>'群抢类型不支持多种商品！','data'=>'']);

        $order_amount = '0'; //订单价格
        $order_goods = [];  //订单商品
        $sku_goods = [];  //去库存
        $shipping_price = '0'; //订单运费
        $i = 0;
        $cart_ids = ''; //提交成功后删掉购物车
        //$goods_ids = '';//商品IDS
        $goods_coupon = [];
        foreach($cart_res as $key=>$value){
            $goods_coupon[$value['goods_id']]['subtotal_price'] =  $value['subtotal_price'];

            //处理运费
            $goods_res = Db::table('goods')->field('shipping_setting,shipping_price,delivery_id,less_stock_type,goods_attr,goods_name,taxes,discount')->where('goods_id',$value['goods_id'])->where('is_show',1)->find();
            if(!$goods_res){
                $this->ajaxReturn(['status' => -1 , 'msg'=>"商品：{$goods_res['goods_name']}已下架，请重新选择",'data'=>'']);
                continue;
            }
            if($goods_res['goods_attr']){
                $goods_attr = explode(',',$goods_res['goods_attr']);
                if( in_array(6,$goods_attr) ){
                    $is_limited = 1;
                }else{
                    $is_limited = 0;
                }
            }

            if($goods_res['shipping_setting'] == 1){
                $shipping_price = sprintf("%.2f",$shipping_price + $goods_res['shipping_price']);   //计算该订单的物流费用
            }else if($goods_res['shipping_setting'] == 2){
                if( !$goods_res['delivery_id'] ){
                    $deliveryWhere['is_default'] = 1;
                }else{
                    $deliveryWhere['delivery_id'] = $goods_res['delivery_id'];
                }
                $delivery = Db::table('goods_delivery')->where($deliveryWhere)->find();
                if( $delivery ){
                    if($delivery['type'] == 2){
                        //件数
                        $shipping_price = sprintf("%.2f",$shipping_price + $delivery['firstprice']);   //计算该订单的物流费用
                        $number = $value['goods_num'] - $delivery['firstweight'];
                        if($number > 0){
                            $number = ceil( $number / $delivery['secondweight'] );  //向上取整
                            $xu = sprintf("%.2f",$delivery['secondprice'] * $number );   //续价
                            $shipping_price = sprintf("%.2f",$shipping_price + $xu);   //计算该订单的物流费用
                        }
                    }else{
                        //重量的待处理
                    }
                }

            }

            //$cart_ids .= ',' . $value['cart_id'];
            $order_amount = sprintf("%.2f",$order_amount + $value['subtotal_price']);   //计算该订单的总价
            $cat_id = Db::table('goods')->where('goods_id',$value['goods_id'])->value('cat_id1');
            foreach($value['spec'] as $k=>$v){

                if($is_limited){
                    //限时购redis
                    $redis = $this->getRedis();
                    for($i=0;$i<$v['goods_num'];$i++){
                        if( !$redis->lpop("GOODS_LIMITED_{$v['sku_id']}") ){
                            for($j=1;$j<=$i;$j++){
                                $redis->rpush("GOODS_LIMITED_{$v['sku_id']}",1);
                                continue;
                            }
                            $this->ajaxReturn(['status' => -1 , 'msg'=>"商品：{$v['goods_name']}，规格：{$v['spec_key_name']}，数量：剩余{$i}件可购买！",'data'=>'']);
                            continue;
                        }
                    }
                }else{
                    $sku = Db::table('goods_sku')->where('sku_id',$v['sku_id'])->field('inventory,frozen_stock')->find();
                    $sku_num = $sku['inventory'] - $sku['frozen_stock'];
                    if( $v['goods_num'] > $sku_num ){
                        $this->ajaxReturn(['status' => -1 , 'msg'=>"商品：{$v['goods_name']}，规格：{$v['spec_key_name']}，数量：剩余{$sku_num}件可购买！",'data'=>'']);
                    }
                }

                $order_goods[$i]['goods_id'] = $v['goods_id'];
                $order_goods[$i]['user_id'] = $v['user_id'];
                $order_goods[$i]['less_stock_type'] = $goods_res['less_stock_type'];
                $order_goods[$i]['cat_id'] = $cat_id;
                $order_goods[$i]['goods_name'] = $v['goods_name'];
                $order_goods[$i]['goods_sn'] = $v['goods_sn'];
                $order_goods[$i]['goods_num'] = $v['goods_num'];
                $order_goods[$i]['final_price'] = $v['goods_price'];
                $order_goods[$i]['goods_price'] = $v['goods_price'];
                $order_goods[$i]['member_goods_price'] = $v['member_goods_price'];
                $order_goods[$i]['sku_id'] = $v['sku_id'];
                $order_goods[$i]['spec_key_name'] = $v['spec_key_name'];
                $order_goods[$i]['delivery_id'] = $goods_res['delivery_id'];
                $order_goods[$i]['taxes'] = $goods_res['taxes'];
                $order_goods[$i]['discount'] = $goods_res['discount'];
                $i++;
            }
        }
        $coupon_price = 0;
        //$cart_ids = ltrim($cart_ids,',');
        
        Db::startTrans();
        $goods_price = $order_amount;
        $order_amount = sprintf("%.2f",$order_amount + $shipping_price);    //商品价格+物流价格=订单金额

        $orderInfoData['order_sn'] = date('YmdHis',time()) . mt_rand(100000,999999);
        $orderInfoData['user_id'] = $user_id;
        //$orderInfoData['groupon_id'] = $groupon_id;
        $orderInfoData['order_status'] = 1;         //订单状态 0:待确认,1:已确认,2:已收货,3:已取消,4:已完成,5:已作废,6:申请退款,7:已退款,8:拒绝退款
        $orderInfoData['pay_status'] = 0;       //支付状态 0:未支付,1:已支付,2:部分支付
        $orderInfoData['shipping_status'] = 0;       //商品配送情况;0:未发货,1:已发货,2:部分发货,3:已收货
        $orderInfoData['pay_type'] = $pay_type;    //支付方式 1:余额支付,2:微信支付,3:支付宝支付,4:货到付款
        $orderInfoData['consignee'] = $addr_res['consignee'];       //收货人
        $orderInfoData['province'] = $addr_res['province'];
        $orderInfoData['city'] = $addr_res['city'];
        $orderInfoData['district'] = $addr_res['district'];
        $orderInfoData['twon'] = $addr_res['twon'];
        $orderInfoData['address'] = $addr_res['address'];
        $orderInfoData['mobile'] = $addr_res['mobile'];
        $orderInfoData['user_note'] = $user_note;       //备注
        $orderInfoData['add_time'] = time();
        $orderInfoData['coupon_price'] = $coupon_price;     //优惠金额
        $orderInfoData['shipping_price'] = $shipping_price;     //物流费(待完善)
        $orderInfoData['goods_price'] = $goods_price;     //商品价格
        $orderInfoData['total_amount'] = $order_amount;     //订单金额
        if($coupon_price){
            $orderInfoData['coupon_id'] = $coupon_id;
            $orderInfoData['order_amount'] = sprintf("%.2f",$order_amount - $coupon_price);       //总金额(实付金额)
        }else{
            $orderInfoData['order_amount'] = $order_amount;       //总金额(实付金额)
        }
        $orderInfoData['invoice_title'] = $invoice_title;
        $orderInfoData['taxpayer'] = $taxpayer;
        $orderInfoData['invoice_desc'] = $invoice_desc;
        $orderInfoData['invoice_mobile'] = $invoice_mobile;
        $orderInfoData['invoice_email'] = $invoice_email;
        $orderInfoData['order_type'] = $order_type;
        $orderInfoData['box_id'] = $box_id;
        $order_id = Db::table('order')->insertGetId($orderInfoData);

        // 添加订单商品
        foreach($order_goods as $key=>$value){
            $order_goods[$key]['order_id'] = $order_id;
            //拍下减库存
            Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setDec('inventory',$value['goods_num']);
            Db::table('goods')->where('goods_id',$value['goods_id'])->setDec('stock',$value['goods_num']);
            unset($order_goods[$key]['less_stock_type']);
        }

        //添加使用优惠券记录
        if($coupon_price){
            Db::table('coupon_get')->where('user_id',$user_id)->where('coupon_id',$coupon_id)->update(['is_use'=>1,'use_time'=>time()]);
        }

        $res = Db::table('order_goods')->insertAll($order_goods);
        
        if($order_type == 2){ //群抢订单拆分成多个子订单
            for($i=0; $i<$order_goods[0]['goods_num']; $i++){
                $orderInfoData1 = $orderInfoData;
                $orderInfoData1['parent_id'] = $order_id;
                $order_sn_child = $orderInfoData['order_sn'];
                $order_sn_child .= '-'.$i;
                $orderInfoData1['order_sn'] = $order_sn_child;
                $order_id_child = Db::table('order')->insertGetId($orderInfoData1);  
                $childordergoods = $order_goods[0];
                $childordergoods['order_id'] = $order_id_child;
                $childordergoods['goods_num'] = 1;
                Db::table('order_goods')->insert($childordergoods);
            }
        }

        if (!empty($res)) {
            //将商品从购物车删除
            Db::table('cart')->where($cart_where)->delete();

            Db::commit();
            if($pay_type==1){//余额支付
                $this->yue_order($order_id);
            }elseif($pay_type==2){ //微信支付
                $pay=new Pay();
                $pay->order_wx_pay($order_id);
            }elseif($pay_type==4){//积分支付
                $this->jifen_order($order_id);
            }
            $this->ajaxReturn(['status' => 1 ,'msg'=>'提交成功！','data'=>$order_id]);
        } else {
            Db::rollback();
            $this->ajaxReturn(['status' => -1 , 'msg'=>'提交订单失败！','data'=>'']);
        }
    }

    //获取用户最近的发票信息
    public function getUserInvoice(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        
        $info = M('Order')->field('invoice_title,taxpayer,invoice_desc,invoice_mobile,invoice_email')->where(['user_id'=>$user_id,'invoice_mobile'=>['neq',''],'invoice_desc'=>['neq','']])->order('add_time desc')->limit(1)->find();
        $this->ajaxReturn(['status' => 1 ,'msg'=>'请求成功！','data'=>$info ? $info : '']);
    }

   /**
    * 订单列表
    */
    public function order_list()
    {
        $user_id = $this->get_user_id();
        // $user_id = 90;
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $pay_status = input('pay_status/d',0);//0全部1未付款2已付款
        $order_type = input('order_type/s','0'); //订单类型，0犒劳自己，1：赠送单人，2：群抢
        $type = input('type/d',0);
        $gift_type = input('gift_type/d',0); //0全部，1已送礼物-已领，2已送礼物-未领，3已收礼物
        $page = input('page/d',1);
        $num = input('num/d',6);
        $parent_id = input('parent_id/d',0); //父单单号
        $where = [];
        $pageParam = ['query' => []];
        $pageParam['page']=$page;
        
        if($parent_id && ($order_type != 2))
            $this->ajaxReturn(['status' => -1 , 'msg'=>'父单单号与订单类型冲突','data'=>'']);

        if ($type == 7){
            $where = array('o.order_status' => 1 ,'o.pay_status'=>0 ,'o.shipping_status' =>0); //待付款
            $pageParam['query']['order_status'] = 1;
            $pageParam['query']['pay_status'] = 0;
            $pageParam['query']['shipping_status'] = 0;
        }
        if ($type == 1){
            $where = array('o.order_status' => 1 ,'o.pay_status'=>1 ,'o.shipping_status' =>0); //待发货
            $pageParam['query']['order_status'] = 1;
            $pageParam['query']['pay_status'] = 1;
            $pageParam['query']['shipping_status'] = 0;
        }
        if ($type == 2){
            $where = array('o.order_status' => ['in',[0,1]] ,'o.pay_status'=>0); //待支付
            $pageParam['query']['order_status'] = ['in',[0,1]];
            $pageParam['query']['pay_status'] = 0;
        }
        if ($type == 3){
            $where = array('o.order_status' => 1 ,'o.pay_status'=>1 ,'o.shipping_status' =>1); //待收货
            $pageParam['query']['order_status'] = 1;
            $pageParam['query']['pay_status'] = 1;
            $pageParam['query']['shipping_status'] = 1;
        }
        if ($type == 4){
            $where = array('o.order_status' => 2 ,'o.pay_status'=>1 ,'o.shipping_status' =>3); //待评价
            $pageParam['query']['order_status'] = 2;
            $pageParam['query']['pay_status'] = 1;
            $pageParam['query']['shipping_status'] = 3;
        }
        if ($type == 5){
            $where = array('o.order_status' => [['=',6],['=',7],['=',8],'or'] ,'o.pay_status'=>1); //退款/售后
            $pageParam['query']['order_status'] = [['=',6],['=',7],['=',8],'or'];
            $pageParam['query']['pay_status'] = 1;
        }
        if ($type == 6){
            $where = array('o.order_status' => 3); //已取消
            $pageParam['query']['order_status'] = 3;
        }
        //支付状态
        if($pay_status == 1){
            $where['o.pay_status'] = 0;
        }else if($pay_status == 2){
            $where['o.pay_status'] = 1;
            $where['o.giving_time'] = 0;
        }
        //不取已取消的订单
        !$type && ($where['o.order_status'] = ['neq',3]); 
        $parent_id && ($where['goj.parent_id'] = $parent_id);
        $where['o.user_id'] = $user_id;
        $where['o.order_type'] = ['in',$order_type];
        //$where['gi.main'] = 1;
        $where['o.deleted'] = 0;
        if($gift_type == 1){ //已送礼物-已领
            $where['goj.status'] = 1;   
            $where['goj.join_status'] = ['notin',[4,5]];   
            $where['goj.addressid'] = ['gt',0];   
        }elseif($gift_type == 2){  //已送礼物-未领
            $where['goj.status'] = 1;   
            $where['goj.join_status'] = ['notin',[4]];   
            $where['goj.addressid'] = 0;   
        }elseif($gift_type == 3){   //已收礼物
            unset($where['o.user_id']);

            $where['goj.status'] = 1;   
            $where['goj.user_id'] = $user_id; 
            $where['goj.join_status'] = ['notin',[4]];   
            $where['goj.addressid'] = ['gt',0];   

            $order_list = Db::table('gift_order_join')->alias('goj')
            ->join('order o','goj.order_id=o.order_id','LEFT')
            ->join('order_goods og','og.order_id=o.order_id','LEFT')
            ->join('goods_img gi','gi.goods_id=og.goods_id and gi.main=1','LEFT')
            ->join('goods g','g.goods_id=og.goods_id','LEFT')
            ->join('refund_apply r','o.order_id=r.order_id','LEFT')
            ->join('member m','o.user_id=m.id','LEFT')
            ->where($where)
            ->group('og.order_id')
            ->order('o.order_id DESC')
            ->field('o.order_id,o.add_time,o.order_sn,og.goods_name,gi.picture img,og.spec_key_name,og.goods_price,g.original_price,og.goods_num,o.order_status,o.pay_status,o.shipping_status,pay_type,o.parent_id,o.total_amount,o.shipping_price,o.order_type,o.lottery_time,o.giving_time,o.overdue_time,o.gift_uid,r.id as refund_id,m.nickname')
            ->page($page,$num)
            ->select();
        }
        if($gift_type != 3){ 
            $whereor = 'o1.order_type=2 and o1.user_id = '.$user_id;
            if($pay_status == 1){
                $whereor = $whereor.' and o1.pay_status=0 ';
            }else if($pay_status == 2){
                $whereor = $whereor.' and o1.pay_status=1 ';
            }
            if($order_type != 0){    //非群抢，非犒劳自己时，不取母ID为0
                $whereor .= ' and o1.parent_id<>0';
            }else{
                //犒劳自己
                $whereor .= ' and o1.parent_id=0';
                if($pay_status == 0){
                    $where['o.pay_status'] = 1;
                }
            }
             $order_list = Db::table('order')->alias('o')
                        ->join('order o1','o.order_id=o1.order_id','LEFT')
                        ->join('order_goods og','og.order_id=o.order_id','LEFT')
                        ->join('goods_img gi','gi.goods_id=og.goods_id and gi.main=1','LEFT')
                        ->join('goods g','g.goods_id=og.goods_id','LEFT')
                        ->join('gift_order_join goj','o.order_id=goj.order_id','LEFT')
                        ->join('refund_apply r','o.order_id=r.order_id','LEFT')
                        ->where($where)
                        // ->whereor($whereor)
                        ->group('og.order_id')
                        ->order('o.order_id DESC')
                        ->field('o.order_id,o.add_time,o.order_sn,og.goods_name,gi.picture img,og.spec_key_name,og.goods_price,g.original_price,og.goods_num,o.order_status,o.pay_status,o.shipping_status,o.pay_type,o.parent_id,o.total_amount,o.shipping_price,o.order_type,o.lottery_time,o.giving_time,o.overdue_time,o.gift_uid,r.id as refund_id')
                        ->page($page,$num)
                        ->select(); 
        }
        $order_list = $this->getOrderList($order_list);
        // dump(Db::table('gift_order_join')->_sql());exit;
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$order_list]);
    }

    //礼品库-待付款和已付款
    public function gift_list(){
        $page = input('page',1);
        $num = input('num',100);
        $user_id = $this->get_user_id();
        // $user_id = 91;
        $pay_status = input('pay_status',2);//0全部1待付款2已付款
        $order_type = input('order_type/s','1,2'); //订单类型，0犒劳自己，1：赠送单人，2：群抢
        //支付状态
        if($pay_status == 1){
            $where['o.pay_status'] = 0;
        }else if($pay_status == 2){
            $where['o.pay_status'] = 1;
            // $where['o.giving_time'] = 0;
        }
        $where['o.user_id'] = $user_id;
        $where['o.parent_id'] = 0;//不取子订单
        $where['o.user_id'] = $user_id;
        //不取已取消的订单
        $where['o.order_status'] = ['neq',3];
        $where['o.order_type'] = ['in',$order_type];//订单类型
        $where['o.deleted'] = 0;
        $order_list = Db::table('order')->alias('o')
            ->join('order o1','o.order_id=o1.order_id','LEFT')
            ->join('order_goods og','og.order_id=o.order_id','LEFT')
            ->join('goods_img gi','gi.goods_id=og.goods_id and gi.main=1','LEFT')
            ->join('goods g','g.goods_id=og.goods_id','LEFT')
            ->join('gift_order_join goj','o.order_id=goj.order_id','LEFT')
            ->join('refund_apply r','o.order_id=r.order_id','LEFT')
            ->where($where)
            ->group('og.order_id')
            ->order('o.order_id DESC')
            ->field('o.order_id,o.add_time,o.order_sn,og.goods_name,gi.picture img,og.spec_key_name,og.goods_price,g.original_price,og.goods_num,o.order_status,o.pay_status,o.shipping_status,o.pay_type,o.parent_id,o.total_amount,o.shipping_price,o.order_type,o.lottery_time,o.giving_time,o.overdue_time,o.gift_uid,r.id as refund_id')
            ->page($page,$num)
            ->select();
        $order_list = $this->getOrderList($order_list);
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$order_list]);
    }

    //修饰订单
    public function getOrderList($order_list = array())
    {
        if(!$order_list) {
            return array();
        }   
        $OrderGoods = M('Order_goods');
        foreach($order_list as $key=>&$value){
            $value['add_time']=date('Y-m-d H:i:s',$value['add_time']);
            $value['img']= $value['img'] ? (SITE_URL.$value['img']) : '';
            $value['comment'] = 0;
            if( $value['order_status'] == 1 && $value['pay_status'] == 0 && $value['shipping_status'] == 0 ){
                $value['status'] = 1;   //待付款
            }else if( $value['order_status'] == 1 && $value['pay_status'] == 1 && $value['shipping_status'] == 0 ){
                $value['status'] = 2;   //待发货
            }else if( $value['order_status'] == 1 && $value['pay_status'] == 1 && $value['shipping_status'] == 1 ){
                $value['status'] = 3;   //待收货
            }else if( $value['order_status'] == 2 && $value['pay_status'] == 1 && $value['shipping_status'] == 3 ){
                $value['status'] = 4;   //待评价
                //是否评价
                $comment = Db::table('goods_comment')->where('order_id',$value['order_id'])->find();
                if($comment){
                    $value['comment'] = 1;
                }else{
                    $value['comment'] = 0;
                }
            }else if( $value['order_status'] == 3 && $value['pay_status'] == 0 && $value['shipping_status'] == 0 ){
                $value['status'] = 5;   //已取消
            }else if( $value['order_status'] == 6 ){
                $value['status'] = 6;   //待退款
            }else if( $value['order_status'] == 7 ){
                $value['status'] = 7;   //已退款
            }else if( $value['order_status'] == 8 ){
                $value['status'] = 8;   //拒绝退款
            }

            $value['goods_list'] = $OrderGoods->alias('og')->join('goods g','og.goods_id=g.goods_id','left')->join('goods_sku gs','og.sku_id=gs.sku_id','left')->join('refund_apply ra','og.rec_id=ra.rec_id','left')->field('og.rec_id,og.goods_id,og.goods_name,og.goods_num,og.goods_price,og.sku_id,og.spec_key_name,og.taxes,og.discount,gs.price,gs.img,g.picture,ra.status,ra.type')->where(['og.order_id'=>$value['order_id']])->select();
            foreach($value['goods_list'] as $key=>$val){
                $value['goods_list'][$key]['img'] = $val['img']?SITE_URL.$val['img']:'';
            }
        }
        return $order_list;
    }

    /**
     * 不用token
     * 只是查订单信息
     */
	public function get_order_info(){
      
        $order_id = input('order_id');
        if(!$order_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'order_id不存在','data'=>'']);
        }

		$info = M('Order_goods')->field('goods_id,goods_name,sku_id,user_id')->where(['order_id'=>$order_id])->find();
		if(!$info){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'订单不存在','data'=>'']);
        }
		
		if($info['sku_id']){
			$info['img'] = M('goods_sku')->where(['sku_id'=>$info['sku_id']])->value('img');
		}

		if(!isset($info['img'])){
			$info['img'] = M('goods')->where(['goods_id'=>$info['goods_id']])->value('picture');
		}

		$info['img'] = $info['img'] ? SITE_URL.$info['img'] : '';

        $address_id = input('address_id');
        if($address_id){
            //有地址
            $address = M('user_address')->where(['address_id'=>$address_id])->find();
        }else{
            //$address = M('user_address')->where(['user_id'=>$info['user_id']])->order('is_default desc')->limit('0,1')->find();
            $address = [];
        }

		if($address){
			$address['province_name'] = $address['province'] ? M('region')->where(['area_id'=>$address['province']])->value('area_name') : '';
			$address['city_name'] = $address['city'] ? M('region')->where(['area_id'=>$address['city']])->value('area_name') : '';
			$address['district_name'] = $address['district'] ? M('region')->where(['area_id'=>$address['district']])->value('area_name') : '';
			$address['twon_name'] = $address['twon'] ? M('region')->where(['area_id'=>$address['twon']])->value('area_name') : '';
		}

		$this->ajaxReturn(['status' => 1 , 'msg'=>'请求成功','data'=>['order'=>$info,'address'=>$address] ]);
		
	}

    /**
    * 订单详情
    */
    public function order_detail()
    {
        $user_id = $this->get_user_id();
        // $user_id = 88;
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $order_id = input('order_id/d',0);
        $where['o.user_id'] = $user_id;
        $where['o.order_id'] = $order_id;

        $order = Db::name('order')->alias('o')->where($where)->where('deleted',0)->find();
        if(!$order){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'订单不存在','data'=>'']);
        }

        $act = input('act/d',0);

        $field = array(
            'o.order_id',//订单ID
            'o.order_sn',//订单编号
            'o.order_status',//订单状态
            'o.pay_status',//支付状态
            'o.shipping_status',//商品配送情况
            'o.pay_type',//支付类型
            'o.consignee',//收货人
            'o.mobile',//收货人手机号
            'o.province',//省
            'o.city',//市
            'o.district',//区
            'o.twon',//街道
            'o.address',//地址
            'o.coupon_price',//优惠券抵扣
            'o.order_amount',//订单总价
            'o.total_amount',//应付款金额
            'o.add_time',//下单时间
            'o.shipping_name',//物流名称
            'o.shipping_price',//物流费用
            'o.user_note',//订单备注
            'o.pay_time',//支付时间
            'o.user_money',//使用余额
            'o.integral',//使用积分
            'o.order_type',//订单类型，0犒劳自己，1：赠送单人，2：群抢
            'o.invoice_title',//发票抬头
            'o.taxpayer',//纳税人识别号
            'o.invoice_desc',//发票内容，购物卡或者礼品卡
            'o.invoice_mobile',//收票人手机
            'o.invoice_email',//收票人邮箱
            
        );
        if($act == 1)$field = ['o.order_status,o.pay_status,o.shipping_status,o.pay_type'];

        $order = Db::table('order')->alias('o')->where($where)->field($field)->find();

        $pay_type = config('PAY_TYPE');
        foreach($pay_type as $key=>$value){
            if($value['pay_type'] == $order['pay_type']){
                $order['pay_type'] = $value['pay_name'];
            }
        }

        $data['order_refund'] = [];
        if( $order['order_status'] == 1 && $order['pay_status'] == 0 && $order['shipping_status'] == 0 ){
            $order['status'] = 1;   //待付款
        }else if( $order['order_status'] == 1 && $order['pay_status'] == 1 && $order['shipping_status'] == 0 ){
            $order['status'] = 2;   //待发货
        }else if( $order['order_status'] == 1 && $order['pay_status'] == 1 && $order['shipping_status'] == 1 ){
            $order['status'] = 3;   //待收货
        }else if( $order['order_status'] == 2 && $order['pay_status'] == 1 && $order['shipping_status'] == 3 ){
            $order['status'] = 4;   //待评价
        }else if( $order['order_status'] == 3 && $order['pay_status'] == 0 && $order['shipping_status'] == 0 ){
            $order['status'] = 5;   //已取消
        }else if( $order['order_status'] == 6 ){
            $order['status'] = 6;   //待退款
        }else if( $order['order_status'] == 7 ){
            $order['status'] = 7;   //已退款
        }else if( $order['order_status'] == 8 ){
            $order['status'] = 8;   //拒绝退款
        }
        if($act == 1)$this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$order]);

        $order['goods_total_amount']=0;
        $order['goods_res'] = Db::table('order_goods')->field('rec_id,goods_id,goods_name,goods_num,spec_key_name,goods_price,taxes,discount')->where('order_id',$order['order_id'])->select();
        $RefundApply = M('Refund_apply');
        $tmp_num = 0;//商品总数量
        foreach($order['goods_res'] as $key=>$value){
            $order['goods_total_amount']=$order['goods_total_amount']+($value['goods_num']*$value['goods_price']);
            $order['goods_res'][$key]['original_price'] = Db::table('goods')->where('goods_id',$value['goods_id'])->value('original_price');
            $order['goods_res'][$key]['img'] = Db::table('goods_img')->where('goods_id',$value['goods_id'])->where('main',1)->value('picture');
            $order['goods_res'][$key]['img']=$order['goods_res'][$key]['img']?SITE_URL.$order['goods_res'][$key]['img']:'';
            $order['goods_res'][$key]['refund_apply_info'] = $RefundApply->where(['order_id'=>$order_id,'rec_id'=>$value['rec_id']])->find();
            $tmp_num += $value['goods_num'];

        }
        $order['total_num'] = $tmp_num;
        $order['add_time']=date('Y-m-d H:i:s', $order['add_time']);
        $order['pay_time']=date('Y-m-d H:i:s', $order['pay_time']);
        $order['province'] = Db::table('region')->where('area_id',$order['province'])->value('area_name');
        $order['city'] = Db::table('region')->where('area_id',$order['city'])->value('area_name');
        $order['district'] = Db::table('region')->where('area_id',$order['district'])->value('area_name');
        $order['twon'] = Db::table('region')->where('area_id',$order['twon'])->value('area_name');

        $order['address'] = $order['province'].$order['city'].$order['district'];
        // $order['address'] = $order['province'].$order['city'].$order['district'].$order['twon'].$order['address'];
        unset($order['province'],$order['city'],$order['district'],$order['twon']);
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$order]);
    }

    /**
    * 修改状态
    */
    public function edit_status(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $order_id = input('order_id');
        $status = input('status');

        if($status != 1 && $status != 3 && $status != 4 && $status != 5){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'参数错误！','data'=>'']);
        }

        $order = Db::table('order')->where('order_id',$order_id)->where('user_id',$user_id)->whereor('parent_id',$user_id)->field('order_id,order_sn,order_status,groupon_id,pay_status,shipping_status,order_amount')->find();
        if(!$order) $this->ajaxReturn(['status' => -1 , 'msg'=>'订单不存在！','data'=>'']);
        if($order['shipping_status'] != 1 && $status == 3){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'订单未发货，不能确认收货','data'=>'']);
        }
        if( $order['order_status'] == 1 && $order['pay_status'] == 0 && $order['shipping_status'] == 0 ){
            //取消订单
            if($status != 1) $this->ajaxReturn(['status' => -1 , 'msg'=>'参数错误！','data'=>'']);
            Db::startTrans();
            //取消主订单把副订单也取消了
            $res = Db::table('order')->update(['order_id'=>$order_id,'order_status'=>3]);
            if($res){
                Db::table('order')->where('parent_id',$order_id)->update(['order_status'=>3]);
            }
            $order_goods = Db::table('order_goods')->where('order_id',$order_id)->field('goods_id,sku_id,goods_num')->select();
            // foreach($order_goods as $key=>$value){
            //     $goods = Db::table('goods')->where('goods_id',$value['goods_id'])->field('goods_attr,less_stock_type')->find();
            //     if($goods['less_stock_type'] == 1){
            //         Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setInc('inventory',$value['goods_num']);
            //         Db::table('goods')->where('goods_id',$value['goods_id'])->setInc('stock',$value['goods_num']);
            //     }else if($goods['less_stock_type'] == 2){
            //         Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setDec('frozen_stock',$value['goods_num']);
            //     }
            // }

            if($res){
                Db::commit();
            }else{
                Db::rollback();
            }
        }else if( $order['order_status'] == 1 && $order['pay_status'] == 1 && $order['shipping_status'] == 1 ){
            //确认收货
            Db::startTrans();

            if($status != 3) $this->ajaxReturn(['status' => -1 , 'msg'=>'参数错误！','data'=>'']);
            $res = Db::table('order')->update(['order_id'=>$order_id,'order_status'=>2,'shipping_status'=>3]);
            if (!$res) {
                Db::rollback();
                $this->ajaxReturn(['status' => -1, 'msg' => '失败！']);
            }
            if (!$res) Db::rollback();
            Db::commit();

        }else if( ($order['order_status'] == 4 && $order['pay_status'] == 1 && $order['shipping_status'] == 3) || $order['order_status'] == 3 ){
            //删除订单
            if($status != 4 && $status != 5) $this->ajaxReturn(['status' => -1 , 'msg'=>'参数错误！','data'=>'']);
            $res = Db::table('order')->update(['order_id'=>$order_id,'deleted'=>1]);
        }

        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功！','data'=>'']);
    }

    /**
    * 订单商品评论
    */
    public function order_comment(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $comments = input('comments');
        $comments = json_decode($comments ,true);

        $order_id = $comments[0]['order_id'];

        $res = Db::table('goods_comment')->where('order_id',$order_id)->find();
        if($res) $this->ajaxReturn(['status' => -1 , 'msg'=>'此订单您已评论过！','data'=>'']);

        $order = Db::table('order')->where('order_id',$order_id)->where('user_id',$user_id)->field('order_status,pay_status,shipping_status')->find();
        if(!$order) $this->ajaxReturn(['status' => -1 , 'msg'=>'订单不存在！','data'=>'']);
        
        if( $order['order_status'] != 4 && $order['pay_status'] != 1 && $order['shipping_status'] != 3 ){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'参数错误！','data'=>'']);
        }

        $order_goods = Db::table('order_goods')
                            ->where('order_id',$order_id)
                            ->field('goods_id,sku_id')
                            ->select();
        $time = time();        
        foreach($order_goods as $key=>$value){

            if($order_goods[$key]['goods_id'] == $comments[$key]['goods_id'] && $order_goods[$key]['sku_id'] == $comments[$key]['sku_id']){
                if(!empty($comments[$key]['img'])){
                    foreach ($comments[$key]['img'] as $k => $val) {
                        $val = explode(',',$val)[1];
                        $saveName = request()->time().rand(0,99999) . '.png';

                        $img=base64_decode($val);
                        //生成文件夹
                        $names = "comment" ;
                        $name = "comment/" .date('Ymd',time()) ;
                        if (!file_exists(ROOT_PATH .Config('c_pub.img').$names)){ 
                            mkdir(ROOT_PATH .Config('c_pub.img').$names,0777,true);
                        }
                        //保存图片到本地
                        file_put_contents(ROOT_PATH .Config('c_pub.img').$name.$saveName,$img);

                        // unset($comments[$key]['img'][$k]);
                        $comments[$key]['img'][$k] = $name.$saveName;
                    }
                    $comments[$key]['img'] = implode(',',$comments[$key]['img']);
                }
            }else{
                $this->ajaxReturn(['status' => -1 , 'msg'=>'参数错误！','data'=>'']);
            }
            $comments[$key]['add_time'] = $time;
        }

        $res = Db::table('goods_comment')->insertAll($comments);

        if($res){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'成功！','data'=>'']);
        }

        $this->ajaxReturn(['status' => -1 , 'msg'=>'提交失败！','data'=>'']);
    }

    /**
    * 获取订单商品评论列表
    */
    public function order_comment_list(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $order_id = input('order_id');

        $order = Db::table('order')->where('order_id',$order_id)->where('user_id',$user_id)->field('order_status,pay_status,shipping_status')->find();
        if(!$order) $this->ajaxReturn(['status' => -1 , 'msg'=>'订单不存在！','data'=>'']);

        if( $order['order_status'] == 4 && $order['pay_status'] == 1 && $order['shipping_status'] == 3 ){
            $order_goods = Db::table('order_goods')->alias('og')
                            ->join('goods_img gi','gi.goods_id=og.goods_id')
                            ->where('gi.main',1)
                            ->where('og.order_id',$order_id)
                            ->field('og.goods_id,og.sku_id,og.goods_name,og.goods_num,og.spec_key_name,gi.picture img')
                            ->select();
            $this->ajaxReturn(['status' => 1 , 'msg'=>'成功！','data'=>$order_goods]);
        }else{
            $this->ajaxReturn(['status' => -1 , 'msg'=>'参数错误！','data'=>'']);
        }
    }

    /**
    * 获取退款信息
    */
    public function get_refund(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $order_id = input('order_id');
        $order = Db::table('order')->where('order_id',$order_id)->where('user_id',$user_id)->field('order_id,order_status,pay_status,shipping_status,consignee,mobile')->find();
        if(!$order) $this->ajaxReturn(['status' => -1 , 'msg'=>'订单不存在！','data'=>'']);
        if($order['pay_status'] == 0){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'参数错误！','data'=>'']);
            // $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单还未付款！','data'=>'']);
        }
        if( $order['order_status'] > 3 && $order['shipping_status'] > 4 ){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'参数错误！','data'=>'']);
        }

        $data['consignee'] = $order['consignee'];
        $data['mobile'] = $order['mobile'];
        $data['refund_reason'] = config('REFUND_REASON');
        $data['refund_type'] = config('REFUND_TYPE');

        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功！','data'=>$data]);
    }

    /**
    * 申请退款
    */
    public function apply_refund(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $order_id = input('order_id');
        $refund_type = input('refund_type');
        $refund_reason = input('refund_reason');
        $cancel_remark = input('cancel_remark');
        $create_time = time();
        $img = input('img');

        $order = Db::table('order')->where('order_id',$order_id)->where('user_id',$user_id)->field('order_id,order_status,pay_status,shipping_status')->find();
        if(!$order) $this->ajaxReturn(['status' => -1 , 'msg'=>'订单不存在！','data'=>'']);

        if($order['pay_status'] == 0){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'参数错误！','data'=>'']);
            // $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单还未付款！','data'=>'']);
        }

        if( $order['order_status'] > 3 ){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'参数错误！','data'=>'']);
        }

        $refund = Db::table('order_refund')->where('order_id',$order['order_id'])->find();
        if($refund){
            if($refund['refund_status'] == 0){
                $this->ajaxReturn(['status' => -1 , 'msg'=>'此订单已被拒绝退款！','data'=>'']);
            }else if($refund['refund_status'] == 1){
                $this->ajaxReturn(['status' => -1 , 'msg'=>'您已申请退款，待审核中！','data'=>'']);
            }else if($refund['refund_status'] == 2){
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单已退款！','data'=>'']);
            }
        }

        if(!empty($img)){
            $img = json_decode($img,true);
            foreach ($img as $k => $val) {
                $val = explode(',',$val)[1];
                $saveName = request()->time().rand(0,99999) . '.png';

                $imga=base64_decode($val);
                //生成文件夹
                $names = "refund" ;
                $name = "refund/" .date('Ymd',time()) ;
                if (!file_exists(ROOT_PATH .Config('c_pub.img').$names)){ 
                    mkdir(ROOT_PATH .Config('c_pub.img').$names,0777,true);
                }
                //保存图片到本地
                file_put_contents(ROOT_PATH .Config('c_pub.img').$name.$saveName,$imga);

                // unset($img[$k]);
                $img[$k] = $name.$saveName;
            }
            $img = implode(',',$img);
        }

        $data['order_id']  = $order_id;
        $data['refund_sn'] = 'ZF' . date('YmdHis',time()) . mt_rand(100000,999999);
        $data['refund_type']   = $refund_type;
        $data['refund_reason'] = $refund_reason;
        $data['cancel_remark'] = $cancel_remark;
        $data['create_time']   = $create_time;
        $data['img']   = $img;
        $data['refund_status'] = 1;
        Db::startTrans();
        $res = Db::table('order_refund')->insert($data);

        Db::table('order')->update(['order_id'=>$order_id,'order_status'=>6]);

        if($res){
            Db::commit();
            $this->ajaxReturn(['status' => 1 , 'msg'=>'成功！','data'=>'']);
        }else{
            Db::rollback();
            $this->ajaxReturn(['status' => -1 , 'msg'=>'申请退款失败！','data'=>'']);
        }
    }

    /**
    * 取消申请退款
    */
    public function cancel_refund(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $order_id = input('order_id');

        $order = Db::table('order')->where('order_id',$order_id)->where('user_id',$user_id)->field('order_id,order_status,pay_status,shipping_status')->find();
        if(!$order) $this->ajaxReturn(['status' => -1 , 'msg'=>'订单不存在！','data'=>'']);

        if($order['order_status'] != 6){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'参数错误！','data'=>'']);
        }
        
        if($order['shipping_status'] == 0 || $order['shipping_status'] == 1){
            $res = Db::table('order')->update(['order_id'=>$order_id,'order_status'=>1]);
        }else if($order['shipping_status'] == 3){
            $res = Db::table('order')->update(['order_id'=>$order_id,'order_status'=>4]);
        }

        if($res){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'取消申请退款成功！','data'=>'']);
        }else{
            $this->ajaxReturn(['status' => -1 , 'msg'=>'取消申请退款失败！','data'=>'']);
        }
    }

    /**
     * 积分支付
     *
     */
    public function jifen_order($order_id){
        $order_info   = Db::name('order')->where(['order_id' => $order_id])->field('order_id,groupon_id,order_sn,order_amount,pay_type,pay_status,user_id')->find();//订单信息
        $user_id=$order_info['user_id'];
        $amount=$order_info['order_amount'];
        $member = Db::table('member')->field('ky_point,dsh_point')->where(['id' => $user_id])->find();
        $ky_point = bcsub($member['ky_point'], $amount, 2);
        $dsh_point = bcadd($amount, $member['dsh_point'], 2);
        if($ky_point<0){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'积分不足','data'=>'']);
        }

        Db::startTrans();

        // 扣除用户积分
        $result = Db::table('member')->update(['id' => $user_id]);
        $result && $result = Db::name('point_log')->insert([
            'type' => 11,
            'user_id' => $user_id,
            'point' => $amount,
            'operate_id' => $order_info['order_sn'],
            'calculate' => 1,
            'before' => $member['dsh_point'],
            'after' => $dsh_point,
            'create_time' => time()
        ]);

        $res = Db::table('member')->update(['id' => $user_id, 'ky_point' => $ky_point, 'dsh_point' => $dsh_point]);
        if (!$res) {
            Db::rollback();
            $this->ajaxReturn(['status' => -1, 'msg' => '支付失败', 'data' => '']);
        }

        // 积分记录
        $res = Db::name('point_log')->insert([
            'type' => 2,
            'user_id' => $user_id,
            'point' => $amount,
            'operate_id' => $order_info['order_sn'],
            'calculate' => 0,
            'before' => $member['ky_point'],
            'after' => $ky_point,
            'create_time' => time()
        ]);
        $res && $res = Db::name('point_log')->insert([
            'type' => 11,
            'user_id' => $user_id,
            'point' => $amount,
            'operate_id' => $order_info['order_sn'],
            'calculate' => 1,
            'before' => $member['dsh_point'],
            'after' => $dsh_point,
            'create_time' => time()
        ]);
        if (!$res) {
            Db::rollback();
            $this->ajaxReturn(['status' => -1, 'msg' => '支付失败', 'data' => '']);
        }

        // 修改订单状态
        $update = [
            'order_status' => 1,
            'pay_status'   => 1,
            'pay_type'     => 4,
            'integral'     => $amount,
            'pay_time'     => time(),
        ];
        $reult = Db::table('order')->where(['order_id' => $order_id])->update($update);

        $goods_res = Db::table('order_goods')->field('goods_id,goods_name,goods_num,spec_key_name,goods_price,sku_id')->where('order_id',$order_id)->select();
        foreach($goods_res as $key=>$value){

            $goods = Db::table('goods')->where('goods_id',$value['goods_id'])->field('less_stock_type,gift_points')->find();
            //付款减库存
            if($goods['less_stock_type']==2){
                Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setDec('inventory',$value['goods_num']);
                Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setDec('frozen_stock',$value['goods_num']);
                Db::table('goods')->where('goods_id',$value['goods_id'])->setDec('stock',$value['goods_num']);
            }
        }

        if($reult){
            // 提交事务
            Db::commit();
            $this->ajaxReturn(['status' => 1 , 'msg'=>'支付成功!','data'=>['order_id' =>$order_info['order_id'],'order_amount' =>$order_info['order_amount'],'goods_name' => getPayBody($order_info['order_id']),'order_sn' => $order_info['order_sn'] ]]);
        }else{
            Db::rollback();
            $this->ajaxReturn(['status' => -1 , 'msg'=>'支付失败','data'=>'']);
        }
    }    

    /**
     * 余额
     *
     */
    public function yue_order($order_id){
        $order_info   = Db::name('order')->where(['order_id' => $order_id])->field('order_id,groupon_id,order_sn,order_amount,pay_type,pay_status,user_id')->find();//订单信息
        $user_id=$order_info['user_id'];
        $amount=$order_info['order_amount'];
        $member     = Db::name('member')->where(["id" => $user_id])->find();
        $balance_info  = get_balance($user_id,0);
        if($balance_info['balance'] < $order_info['order_amount']){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'余额不足','data'=>'']);
        }
        // 启动事务
        Db::startTrans();

        //扣除用户余额
        $balance = [
            'balance'            =>  Db::raw('balance-'.$amount.''),
        ];
        $res =  Db::table('member')->where(['id' => $user_id])->update($balance);
        if(!$res){
            Db::rollback();
        }
        //
        //余额记录
        $balance_log = [
            'user_id'      => $user_id,
            'money'        => $order_info['order_amount'],
            'balance'      => bcsub($balance_info['balance'], $order_info['order_amount'], 2),
            'balance_type' => 0,
            'source_type'  => 1,
            'log_type'     => 0,
            'source_id'    => $order_info['order_sn'],
            'note'         => '商品订单消费',
            'create_time'  => time(),
            'old_balance'  => $balance_info['balance']
        ];
        $res2 = Db::table('menber_balance_log')->insert($balance_log);
        if(!$res2){
            Db::rollback();
        }
        //修改订单状态
        $update = [
            'order_status' => 1,
            'pay_status'   => 1,
            'pay_type'     => 1,
            'user_money'      =>$amount,
            'pay_time'     => time(),
        ];
        $reult = Db::table('order')->where(['order_id' => $order_id])->update($update);

        $goods_res = Db::table('order_goods')->field('goods_id,goods_name,goods_num,spec_key_name,goods_price,sku_id')->where('order_id',$order_id)->select();
        foreach($goods_res as $key=>$value){

            $goods = Db::table('goods')->where('goods_id',$value['goods_id'])->field('less_stock_type,gift_points')->find();
            //付款减库存
            if($goods['less_stock_type']==2){
                Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setDec('inventory',$value['goods_num']);
                Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setDec('frozen_stock',$value['goods_num']);
                Db::table('goods')->where('goods_id',$value['goods_id'])->setDec('stock',$value['goods_num']);
            }

        }


        /* $dsh_point = bcadd($amount, $member['dsh_point'], 2);
        $result = Db::table('member')->update(['id' => $user_id, 'dsh_point' => $dsh_point]);
        $result && $result = Db::name('point_log')->insert([
            'type' => 11,
            'user_id' => $user_id,
            'point' => $amount,
            'operate_id' => $order_info['order_sn'],
            'calculate' => 1,
            'before' => $member['dsh_point'],
            'after' => $dsh_point,
            'create_time' => time()
        ]); */



        if($reult){
            // 提交事务
            Db::commit();
            $this->ajaxReturn(['status' => 1 , 'msg'=>'余额支付成功!','data'=>['order_id' =>$order_info['order_id'],'order_amount' =>$order_info['order_amount'],'goods_name' => getPayBody($order_info['order_id']),'order_sn' => $order_info['order_sn'] ]]);
        }else{
            Db::rollback();
            $this->ajaxReturn(['status' => -1 , 'msg'=>'余额支付失败','data'=>'']);
        }
    }    

    /*
     * 余额支付
     */
    public function order_pay(){
        $user_id    = $this->get_user_id();
        $pwd        = input('pwd/d');
        $member     = Db::name('member')->where(["id" => $user_id])->find();
        if(!$member){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在！','data'=>'']);
        }
        $pay_type=input('pay_type');
        $order_id=input('order_id');
        $order_info   = Db::name('order')->where(['order_id' => $order_id])->field('order_id,groupon_id,order_sn,order_amount,pay_type,pay_status,user_id')->find();//订单信息
        if(!$order_info){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'订单不存在！','data'=>'']);
        }
        if($pay_type==1||$pay_type==4){
            $pwd        = input('pwd/d');
            $member     = Db::name('member')->where(["id" => $user_id])->find();
            if(!$member){
                $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在！','data'=>'']);
            }
            $password = md5($member['salt'] . $pwd);
            if($member['pwd'] !== $password){
                $this->ajaxReturn(['status' => -1 , 'msg'=>'支付密码错误！','data'=>'']);
            }
        }
        if($pay_type==1){//余额支付
            $this->yue_order($order_id);
        }elseif($pay_type==2){//微信支付
                $pay=new Pay();
                $pay->order_wx_pay($order_id);
        }elseif($pay_type==4){//积分支付
            $this->jifen_order($order_id);
        }
        //$user_id=$order_info['user_id'];
        $this->ajaxReturn(['status' => -1 , 'msg'=>'未知错误，请联系管理员！','data'=>'']);
    }    

    //申请退款
    public function refund_apply(){
        $user_id    = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在！','data'=>'']);
        }      
        
        $order_id = I('post.order_id/d',0);
        $type = I('post.type/d',0); //类型，1：仅退款，2：退款退货，3：换货
        $reason = I('post.reason/d',0); //退款理由，1.7天无理由，2.退运费，3.少件/漏发，4.质量问题，5.商品信息描述不符，6.包装/商品破损/污渍
        $rec_id = I('post.rec_id/d',0);  //order_goods表ID
        $msg = I('post.msg/s','');  //退款说明
        $goods_num = I('post.goods_num/d',0);  //退款数量
        $prine_way = I('post.prine_way/d',0); //退回途径，微信
        $refund_apply_id = I('post.refund_apply_id/d',0); //退款ID

        if(!$order_id || !$rec_id || !$goods_num || !in_array($type,[1,2,3]) || !in_array($reason,[1,2,3,4,5,6]))
            $this->ajaxReturn(['status' => -1 , 'msg'=>'参数错误！','data'=>'']);
        if(($type != 3) && !$prine_way)
            $this->ajaxReturn(['status' => -1 , 'msg'=>'请选择退款退回途径！','data'=>'']);
        
        $pics = '';
        if(isset($_FILES['pic'])){
            $pics = $this->UploadFile('pic','refund'); 
            if($pics['status'] == -1)
                $this->ajaxReturn($pics);
            else
                $pics = implode(',',$pics['data']);
        }

        $orderinfo = M('Order')->field('order_status,shipping_status,pay_status,parent_id,gift_uid')->where(['user_id'=>$user_id])->find($order_id);
        if(!$orderinfo){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'订单不存在！','data'=>'']);
        }elseif($orderinfo['parent_id']){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'子订单不能进行此操作！','data'=>'']);
        }elseif($orderinfo['gift_uid']){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单已被人领取，不能执行退款操作！','data'=>'']);
        }elseif($type == 1){ //仅退款
            if(!in_array($orderinfo['order_status'],[0,1]))
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单状态不能执行退款操作！','data'=>'']);
            elseif($orderinfo['shipping_status'] != 0)
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单不是未发货状态！','data'=>'']);
            elseif($orderinfo['pay_status'] != 1)
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单未付款！','data'=>'']);
        }elseif($type == 2){ //退款退货
            if(!in_array($orderinfo['order_status'],[1]))
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单状态不能执行退款退货操作！','data'=>'']);
            elseif(!in_array($orderinfo['shipping_status'],[1,2]))
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单不是已发货状态！','data'=>'']);
            elseif($orderinfo['pay_status'] != 1)
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单未付款！','data'=>'']);
        }elseif($type == 2){ //换货
            if(!in_array($orderinfo['order_status'],[1]))
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单状态不能执行换货操作！','data'=>'']);
            elseif(!in_array($orderinfo['shipping_status'],[1,2]))
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单不是已发货状态！','data'=>'']);
            elseif($orderinfo['pay_status'] != 1)
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单未付款！','data'=>'']);
        }

        $RefundApply = M('refund_apply');
        $ranum = $RefundApply->where(['order_id'=>$order_id,'rec_id'=>$rec_id])->count();
        if($ranum)$this->ajaxReturn(['status' => -1 , 'msg'=>'该订单不能再进行此操作了！','data'=>'']);

        $data = [];
        $OrderGoods = M('Order_goods');
        $recinfo = $OrderGoods->field('goods_price,goods_num,taxes,discount')->find($rec_id);
        if($recinfo['goods_num'] < $goods_num){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'退款数量超出订单数量！','data'=>'']);
        }

        //税费
        //$taxes = floor(($recinfo['goods_price'] - $recinfo['discount']) * $recinfo['taxes'] * $goods_num)/100;
        $taxes = 0; //2019-07-16 20:00修改为退款不退税
        //商品总额
        $price = ($recinfo['goods_price'] - $recinfo['discount']) * $goods_num;
        $data['order_id']          = $order_id;
        $data['type']              = $type;
        $data['addtime']           = time();
        $data['reason']            = $reason;
        $data['rec_id']            = $rec_id;
        $data['msg']               = $msg;
        $data['goods_num']         = $goods_num;
        $data['price']             = $price + $taxes;
        $data['real_pay_price']    = $price + $taxes;
        $data['prine_way']         = $prine_way;
        $data['user_id']           = $user_id;

        $pics && $data['pic'] = $pics;

        Db::startTrans();
        $res = $RefundApply->insertGetId($data);

        if($res){
            //查看当前订单商品是否已全部退货
            $rec_ids1 = $OrderGoods->where(['order_id'=>$order_id])->column('rec_id');
            $rec_ids2 = $RefundApply->where(['order_id'=>$order_id,'rec_id'=>['in',$rec_ids1],'status'=>['notin',[1,4]]])->column('rec_id');
            $arr = array_diff($rec_ids1,$rec_ids2);

            if(empty($arr))M('Order')->where(['order_id'=>$order_id])->update(['order_status'=>6]);

            Db::commit(); 
            $this->ajaxReturn(['status' => 1 , 'msg'=>'请求成功！','data'=>$res]);
        }else{
            Db::rollback();
            $this->ajaxReturn(['status' => -1 , 'msg'=>'请求失败！','data'=>'']);
        }
    }

    //查看退款详细信息
    public function get_refund_info(){
        $user_id    = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在！','data'=>'']);
        }      
        
        $refund_apply_id = I('post.refund_apply_id/d',0); //退款ID
        $info = M('refund_apply')->field('id,order_id,rec_id,status,type,addtime,kuaidi_number,reason,msg,pic,kuaidi_com,tel,kuaidi_msg,kuaidi_pic,goods_num,price,integral,real_pay_price,prine_way,on_time')->where(['user_id'=>$user_id])->find($refund_apply_id);
        
        if(!$info)
            $this->ajaxReturn(['status' => -1 , 'msg'=>'未查询到此次退款信息！','data'=>'']);
        if($info['pic']){
            $info['pic'] = explode(',',$info['pic']);
            foreach($info['pic'] as $k=>$v){
                $info['pic'][$k] = SITE_URL . $v;
            }
        }
        if($info['kuaidi_pic']){
            $info['kuaidi_pic'] = explode(',',$info['pic']);
            foreach($info['kuaidi_pic'] as $k=>$v){
                $info['kuaidi_pic'][$k] = SITE_URL . $v;
            }
        }
        //商品
        if($info['rec_id']){
            $goods = Db::name('order_goods')->field('sku_id,goods_id,goods_name,goods_num,goods_price,spec_key_name')->where('rec_id',$info['rec_id'])->find();
            $goods['img'] = Db::name('goods_sku')->where('sku_id',$goods['sku_id'])->value('img');
            $goods['img'] = $goods['img']?SITE_URL.$goods['img']:'';
            $info['goods_name'] = $goods['goods_name'];
            $info['goods_num'] = $goods['goods_num'];
            $info['goods_price'] = $goods['goods_price'];
            $info['spec_key_name'] = $goods['spec_key_name'];
            $info['img'] = $goods['img'];
        }
        // 退货设置
        $return  = Db::name('config')->where('status',1)->where('module',2)->column('name,value');
        $info['return_name'] = $return['return_name'];
        $info['return_phone'] = $return['return_phone'];
        $info['return_address'] = $return['return_address'];
        $info['return_desc'] = $return['return_desc'];
        $info['return_remarks'] = $return['return_remarks'];
        $info['return_remarks2'] = $return['return_remarks2'];
        $info['on_time'] = $this->secToTime(time()-$info['on_time']);
        $this->ajaxReturn(['status' => 1 , 'msg'=>'请求成功！','data'=>$info]);
    }

    /** 
     *      把秒数转换为时分秒的格式 
     *      @param Int $times 时间，单位 秒 
     *      @return String 
     */  
    public function secToTime($times){  
        $result = '0';  
        if ($times>0) {  
                $day = floor($times/86400);
                $hour = floor($times/3600);  
                $minute = floor(($times-3600 * $hour)/60);  
                $second = floor((($times-3600 * $hour) - 60 * $minute) % 60);  
                $result = $day.'天'.$hour.'时'.$minute.'分'.$second.'秒';  
        }  
        return $result;  
    }  


    //设置退款快递 
    public function set_refund_kuaidi(){
        $user_id    = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在！','data'=>'']);
        }      
        
        $refund_apply_id = I('post.refund_apply_id/d',0); //退款ID
        $info = M('refund_apply')->field('id,order_id,rec_id,status,type,kuaidi_number')->where(['user_id'=>$user_id])->find($refund_apply_id);

        if(!$info)
            $this->ajaxReturn(['status' => -1 , 'msg'=>'未查询到此次退款信息！','data'=>'']);       
        elseif($info['kuaidi_number'])
            $this->ajaxReturn(['status' => -1 , 'msg'=>'已填写退款物流信息啦！','data'=>'']);   
            
        $kuaidi_com = I('post.kuaidi_com/s','');    
        $kuaidi_number = I('post.kuaidi_number/s',''); 
        $tel = I('post.tel/s',''); 
        $kuaidi_msg = I('post.kuaidi_msg/s','');   

        if(!$kuaidi_com || !$kuaidi_number || !$tel){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'参数错误！','data'=>'']);       
        }
        
        $pics = '';
        if(isset($_FILES['kuaidi_pic'])){
            $pics = $this->UploadFile('kuaidi_pic','refund'); 
            if($pics['status'] == -1)
                $this->ajaxReturn($pics);
            else
                $pics = implode(',',$pics['data']);
        }

        $data = [
            'kuaidi_com'    => $kuaidi_com,
            'kuaidi_number' => $kuaidi_number,
            'tel'           => $tel,
            'kuaidi_msg'    => $kuaidi_msg
        ];
        if($pics)$data['kuaidi_pic'] = $pics;

        $res = M('refund_apply')->where(['id'=>$refund_apply_id])->update($data);
        if($res !== false){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'设置成功！','data'=>'']);      
        }else
        $this->ajaxReturn(['status' => -1 , 'msg'=>'设置失败！','data'=>'']);  
    }

    public function express_detail()
    {
        $user_id    = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在！','data'=>'']);
        }   

        $order_id = I('id/d',0);
        if(!$order_id)$this->ajaxReturn(['status' => -1 , 'msg'=>'参数错误！','data'=>'']);
        $orderinfo = M('Order')->where(['order_id'=>$order_id,'user_id'=>$user_id])->count();
        if(!$orderinfo)$this->ajaxReturn(['status' => -1 , 'msg'=>'订单不存在！','data'=>'']);

        $Delivery = new  \app\common\logic\DeliveryLogic;
        $data = M('delivery_doc')->where('order_id', $order_id)->find();
        $shipping_code = $data['shipping_code'];
        $invoice_no = $data['invoice_no'];
        $result = $Delivery->queryExpress($shipping_code, $invoice_no);
        if ($result['status'] == 0) {
            $result['result'] = $result['result']['list'];
        }
        $this->assign('invoice_no', $invoice_no);
        $this->assign('result', $result);
        $this->ajaxReturn(['status' => 1 , 'msg'=>'请求成功！','data'=>$result]);
    }    

    //撤销退款申请
    public function undo_refund(){
        $user_id    = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在！','data'=>'']);
        }    
        $raid = I('post.raid/d',0);
        if(!$user_id)$this->ajaxReturn(['status' => -1 , 'msg'=>'参数错误！','data'=>'']);
        //状态，0:申请中，1:未同意，2:已同意，3:已完成，4:已撤销
        //类型，1：仅退款，2：退款退货，3：换货
        $info = M('refund_apply')->field('order_id,status,type')->where(['user_id'=>$user_id])->find($raid);
        if($info['status'] != 0)
            $this->ajaxReturn(['status' => -1 , 'msg'=>'此次申请已不可撤销！','data'=>'']);

        Db::startTrans();    
        $res1 = M('refund_apply')->where(['id'=>$raid])->update(['status' => 4]);     
        
        if(false !== $res1){
            //查看当前订单商品是否已全部退货
            $rec_ids1 = $OrderGoods->where(['order_id'=>$raid['order_id']])->column('rec_id');
            $rec_ids2 = $RefundApply->where(['order_id'=>$raid['order_id'],'rec_id'=>['in',$rec_ids],'status'=>['notin',[1,4]]])->column('rec_id');
            $arr = array_diff($rec_ids1,$rec_ids2);
            $arr1 = array_diff($rec_ids1,$arr);

            if(empty($arr1))M('Order')->where(['order_id'=>$raid['order_id']])->update(['order_status'=>1]);
            Db::commit(); 
            $this->ajaxReturn(['status' => 1 , 'msg'=>'操作成功！','data'=>'']);
        }else{
            Db::rollback();
            $this->ajaxReturn(['status' => -1 , 'msg'=>'操作失败！','data'=>'']);
        }
        
    }

    //修改发票信息
    /**
     * 这里改成
     * 每个人都有一个发票表
     */
    public function edit_invoice()
    {
        $user_id = $this->get_user_id();
        // $count = Db::name('order')->where('order_id',$order_id)->where('user_id',$user_id)->count();
        // if(!$count){
        //     $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单不是你的订单','data'=>'']);
        // }
        $data['invoice_title'] = I('post.invoice_title/s',''); //发票抬头
        $data['taxpayer'] = I('post.taxpayer/s',''); //纳税人识别号
        $data['invoice_desc'] = I('post.invoice_desc/s',''); //发票内容
        $data['invoice_mobile'] = I('post.invoice_mobile/s',''); //收票人手机
        $data['invoice_email'] = I('post.invoice_email/s','');  //收票人邮箱


        $res = Db::name('invoice')->where('user_id',$user_id)->find();
        
        if($res){
            Db::name('invoice')->where('user_id',$user_id)->update($data);
        }else{
            $data['user_id'] = $user_id;
            Db::name('invoice')->insert($data);
        }

        $this->ajaxReturn(['status' => 1 , 'msg'=>'操作成功！','data'=>$res['id']]);
    }

     //获取发票信息
     public function get_invoice()
     {
        $user_id = $this->get_user_id();
        
        $res = Db::name('invoice')->where('user_id',$user_id)->find();
        if(!$res){

            $data['invoice_title'] = I('post.invoice_title/s',''); //发票抬头
            $data['taxpayer'] = I('post.taxpayer/s',''); //纳税人识别号
            $data['invoice_desc'] = I('post.invoice_desc/s',''); //发票内容
            $data['invoice_mobile'] = I('post.invoice_mobile/s',''); //收票人手机
            $data['invoice_email'] = I('post.invoice_email/s','');  //收票人邮箱
            $data['user_id'] = $user_id;

            Db::name('invoice')->insert($data);

            $res = Db::name('invoice')->where('user_id',$user_id)->find();
        } 

        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=> $res]);
         
     }

    //礼品库犒劳自己操作
    public function edit_order_type()
    {
        $user_id = $this->get_user_id();
        // $user_id = 90;
        $order_id = input('order_id',0);
        $info = Db::name('order')->field('order_id,pay_status,order_type,gift_uid,order_status,overdue_time')->where('order_id',$order_id)->where('user_id',$user_id)->find();
        if(!$info){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'订单不存在','data'=>'']);
        }
        if($info['pay_status'] != 1){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单未支付','data'=>'']);
        }
        if($info['order_status'] != 0 && $info['order_status'] != 1){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单已经有人领取','data'=>'']);
        }
        if($info['gift_uid']){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单已经有人领取','data'=>'']);
        }
        if($info['order_type'] == 0){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'不是赠送订单不能犒劳自己','data'=>'']);
        }
        //判断有没有人参与
        $gift_order_join = Db::name('gift_order_join')->where('order_id',$order_id)->find();
        //判断有没有下级
        if($gift_order_join){
            $zi_gift = Db::name('gift_order_join')->where('parentid',$gift_order_join['id'])->find();
            if($gift_order_join['addressid'] || $zi_gift['addressid']){
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该礼品已经有人领取','data'=>'']);
            }
            if(time() < $info['overdue_time']){
                $this->ajaxReturn(['status' => -1 , 'msg'=>'活动未结束不能犒劳自己','data'=>'']);
            }
        }
        $res = Db::name('order')->where('order_id',$order_id)->update(['order_type'=>0]);
        if($res){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'操作成功','data'=>'']);
        }else{
            $this->ajaxReturn(['status' => -1 , 'msg'=>'操作失败','data'=>'']);
        }
    }

    //退换/售后
    public function after_sale()
    {
        $user_id = $this->get_user_id();
        $array = ['申请中','未同意','已同意','已完成','已撤销'];
        $list = Db::name('refund_apply')
            ->alias('ra')
            ->join('order_goods og','ra.rec_id=og.rec_id','LEFT')
            ->join('order o','ra.rec_id=og.rec_id','LEFT')
            ->where('ra.user_id',$user_id)
            ->field('ra.id,o.order_sn,og.goods_name,og.spec_key_name,og.goods_id,og.goods_price,og.goods_num,ra.status')
            ->order('ra.addtime desc')
            ->select();
        foreach($list as $key=>$val){
            $list[$key]['msg'] = $array[$val['status']];
            $val['img'] = Db::name('goods_sku')->where('goods_id',$val['goods_id'])->value('img');
            $list[$key]['img'] =  $val['img']?SITE_URL.$val['img']:'';
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取数据成功','data'=>$list]);
    }

    //获取退货物流信息
    public function refund_logistics()
    {
        $list =  [
            ['name'=>'顺丰快递','value'=>'顺丰快递'],
            ['name'=>'中国邮政','value'=>'中国邮政'],
            ['name'=>'圆通快递','value'=>'圆通快递'],
            ['name'=>'韵达快递','value'=>'韵达快递'],
            ['name'=>'中通快递','value'=>'中通快递']
        ];
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取数据成功','data'=>$list]);
    }

}
