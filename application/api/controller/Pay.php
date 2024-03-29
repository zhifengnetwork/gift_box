<?php

/***
 * 支付api
 */
namespace app\api\controller;
use app\api\controller\TestNotify;

use Payment\Common\PayException;
use Payment\Notify\PayNotifyInterface;
use Payment\Notify\AliNotify;
use Payment\Client\Charge;
use Payment\Client\Notify;
use Payment\Config as PayConfig;
use app\common\model\Member as MemberModel;
//use app\common\model\Order;
use app\common\model\VipCard;
use app\common\model\Sysset;
use \think\Model;
use \think\Config;
use \think\Db;
use \think\Env;
use \think\Request;

class Pay extends ApiBase
{
     /**
     * 析构流函数
     */
    public function  __construct() {           
        require_once ROOT_PATH.'vendor/riverslei/payment/autoload.php';
    }    

    /***
     * 支付
     */
    public function payment(){
        $order_id     = input('order_id');
        $pay_type     = input('pay_type');//支付方式
        $user_id      = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $order_info   = Db::name('order')->where(['order_id' => $order_id])->field('order_id,groupon_id,order_sn,order_amount,pay_type,pay_status,user_id')->find();//订单信息
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
        
        //团购
        if($order_info['groupon_id']){
            $groupon = Db::table('goods_groupon')->where('groupon_id',$order_info['groupon_id'])->where('is_show',1)->where('is_delete',0)->where('status',2)->find();
            if(!$groupon){
                Db::table('order')->where('order_id',$order_info['order_id'])->delete();
                Db::table('order_goods')->where('order_id',$order_info['order_id'])->delete();
                $this->ajaxReturn(['status' => -2 , 'msg'=>'该期拼团已结束，请前往最新一期拼团！','data'=>'']);
            }
            if(($groupon['target_number'] - $groupon['sold_number']) <= 0){
                Db::table('order')->where('order_id',$order_info['order_id'])->delete();
                Db::table('order_goods')->where('order_id',$order_info['order_id'])->delete();
                $this->ajaxReturn(['status' => -2 , 'msg'=>'该期拼团已结束，请前往最新一期拼团！','data'=>$groupon['goods_id']]);
            }
        }

        // $sysset       = Db::name('sysset')->find();
        // $config       = unserialize($sysset['sets']);
        $amount       = $order_info['order_amount'];
        $client_ip    = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
        $payData['order_no']        = $order_info['order_sn'];
        $payData['body']            = '';
        $payData['timeout_express'] = time() + 600;
        $payData['amount']          = $amount;
        if($pay_type == 3){
              $payData['subject']      = '支付宝支付';
              $payData['goods_type']   = 1;//虚拟还是实物
              $payData['return_param'] = '';
              $payData['store_id']     = '';
              $payData['quit_url']     = '';
        }elseif($pay_type == 2){
            $payData['subject']      = '微信支付';
            $payData['openid']       = $order_info['openid'];
            $payData['product_id']   = '';
            $payData['sub_appid']    = '';
            $payData['sub_mch_id']   = '';
        }elseif($pay_type == 1){
            $balance_info  = get_balance($user_id,0);
            if($balance_info['balance'] < $order_info['order_amount']){
                $this->ajaxReturn(['status' => 0 , 'msg'=>'余额不足','data'=>'']);
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

            //余额记录
            $balance_log = [
                'user_id'      => $user_id,
                'balance'      => $balance_info['balance'] - $order_info['order_amount'],
                'balance_type' => $balance_info['balance_type'],
                'source_type'  => 0,
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
                'pay_type'     => $pay_type,
                'pay_time'     => time(),
            ];
            $reult = Order::where(['order_id' => $order_id])->update($update);

            $goods_res = Db::table('order_goods')->field('goods_id,goods_name,goods_num,spec_key_name,goods_price,sku_id')->where('order_id',$order_id)->select();
            $jifen = 0;
            foreach($goods_res as $key=>$value){

                $goods = Db::table('goods')->where('goods_id',$value['goods_id'])->field('less_stock_type,gift_points')->find();
                //付款减库存
                if($goods['less_stock_type']==2){
                    Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setDec('inventory',$value['goods_num']);
                    Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setDec('frozen_stock',$value['goods_num']);
                    Db::table('goods')->where('goods_id',$value['goods_id'])->setDec('stock',$value['goods_num']);
                }
                $baifenbi = strpos($goods['gift_points'] ,'%');
                if($baifenbi){
                    $goods['gift_points'] = substr($goods['gift_points'],0,strlen($goods['gift_points'])-1); 
                    $goods['gift_points'] = $goods['gift_points'] / 100;
                    $jg    = sprintf("%.2f",$value['goods_price'] * $value['goods_num']);
                    $jifen = sprintf("%.2f",$jifen + ($jg * $goods['gift_points']));
                }else{
                    $goods['gift_points'] = $goods['gift_points'] ? $goods['gift_points'] : 0;
                    $jifen = sprintf("%.2f",$jifen + ($value['goods_num'] * $goods['gift_points']));
                }
            }
            //团购
            Db::table('goods_groupon')->where('groupon_id',$order_info['groupon_id'])->setInc('sold_number',1);
           
            $res = Db::table('member')->update(['id'=>$user_id,'gouwujifen'=>$jifen]);

            //判断用户是否是puls会员
            $is_puls = model('Member')->is_puls($user_id);
            if (empty($is_puls)){
                //不是puls会员
                $update_ispuls = model('Member')->create_puls($user_id,$order_id,1);
            }else{
                $update_ispuls = 1;
            }

            if($reult && $update_ispuls){
                // 提交事务
                Db::commit();
                $this->ajaxReturn(['status' => 1 , 'msg'=>'余额支付成功!','data'=>['order_id' =>$order_info['order_id'],'order_amount' =>$order_info['order_amount'],'goods_name' => getPayBody($order_info['order_id']),'order_sn' => $order_info['order_sn'] ]]);
            }else{
                 Db::rollback();
                $this->ajaxReturn(['status' => -2 , 'msg'=>'余额支付失败','data'=>'']);
            }
        }
        //支付方式不同
        if($pay_type == 3){//支付宝
            $pay_config = Config::get('pay_config');
            $url        = Charge::run(PayConfig::ALI_CHANNEL_WAP, $pay_config, $payData);
        }elseif($pay_type == 2){//微信

        }
        
        try {
            $this->ajaxReturn(['status' => 1 , 'msg'=>'请求路径','data'=> $url]);
        } catch (PayException $e) {
            $this->ajaxReturn(['status' => 0 , 'msg'=>$e->errorMessage(),'data'=>'']);
            exit;
        }
    }
    /**
     * 打卡微信支付接口===
     */
    public function clock_wx_pay(){

        $order_id     = 13;
        $pay_type     = input('pay_type');//支付方式
        $user_id      = 9;
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $order_info   = Db::name('clock_balance_log')->where(['order_id' => $order_id])->field('order_id,order_sn,title,pay_money,pay_status,uid,punch_time')->find();//订单信息
        $member       = MemberModel::get($user_id);
        //验证是否本人的
        if(empty($order_info)){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'订单不存在','data'=>'']);
        }
        if($order_info['uid'] != $user_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'非本人订单','data'=>'']);
        }

        if($order_info['pay_status'] == 1){
            $this->ajaxReturn(['status' => -4 , 'msg'=>'此订单，已完成支付!','data'=>'']);
        }

        $rechData['order_no']        = $order_info['order_sn'];
        $rechData['body']            = '每日打卡支付';
        $rechData['timeout_express'] = time() + 600;
        $rechData['amount']          = $order_info['pay_money'];
        $rechData['subject']         = '每日打卡';
        $rechData['openid']       = $member['openid'];
        $pay_config = Config::get('pay_config');
        $wxConfig = Config::get('wx_config');
        $url      = Charge::run(Config::WX_CHANNEL_WAP, $wxConfig, $rechData);

    }
    /**
     * 订单微信支付接口====正解
     */
    public function order_wx_pay(){
        $order_id = input('order_id',0);
        $address_id = input('address_id',0);//判断是否有地址
        $user_id      = $this->get_user_id();
        $user_note = input('user_note');//备注
        $order_info   = Db::name('order')->where(['order_id' => $order_id])->field('order_id,groupon_id,order_sn,order_amount,pay_type,pay_status,user_id')->find();//订单信息
        $goods   = Db::name('order_goods')->where(['order_id' => $order_id])->field('goods_name')->find();//商品信息
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $member       = MemberModel::get($user_id);
        //验证是否本人的
        if(empty($order_info)){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'订单不存在','data'=>'']);
        }
        if($order_info['user_id'] != $user_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'非本人订单','data'=>'']);
        }
        //如果传了地址就修改订单地址
        if($address_id){
            $addr_res = Db::name('user_address')->where(['user_id'=>$user_id,'address_id'=>$address_id])->find();
            if(!$addr_res){
                $this->ajaxReturn(['status' => -1 , 'msg'=>'地址不存在','data'=>'']);
            }
            $data['consignee'] = $addr_res['consignee'];       //收货人
            $data['province'] = $addr_res['province'];
            $data['city'] = $addr_res['city'];
            $data['district'] = $addr_res['district'];
            $data['twon'] = $addr_res['twon'];
            $data['address'] = $addr_res['address'];
            $data['mobile'] = $addr_res['mobile'];
            $data['user_note'] = $addr_res['user_note'];//备注
            Db::name('order')->where('order_id',$order_id)->update($data);
        }

        if($order_info['pay_status'] == 1){
            $this->ajaxReturn(['status' => -4 , 'msg'=>'此订单，已完成支付!','data'=>'']);
        }
        $rechData['order_no']        = $order_info['order_sn'];
        $rechData['subject']        = $goods['goods_name'].'等商品';
        $rechData['body']            = '购买商品';
        $rechData['timeout_express'] = time() + 600;
        $rechData['amount']          = $order_info['order_amount'];
        $rechData['product_id']          = '';
        $rechData['return_param']          = '';
        $rechData['client_ip']          = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
        $rechData['openid']       = $member['openid'];
        $wxConfig = Config::get('wx_config'); 
        $config = Db::name('config')->where('module',1)->column('name,value');
        $wxConfig['app_id'] = $config['appid']?$config['appid']:$wxConfig['app_id'];
        $wxConfig['app_secret'] = $config['appsecret']?$config['appsecret']:$wxConfig['app_secret'];
        $wxConfig['mch_id'] = $config['mch_id']?$config['mch_id']:$wxConfig['mch_id'];
        $wxConfig['md5_key'] = $config['md5_key']?$config['md5_key']:$wxConfig['md5_key'];
        $url      = Charge::run(PayConfig::WX_CHANNEL_PUB, $wxConfig, $rechData);
        $url['order_id']=$order_id;
        $this->ajaxReturn(['status' => 1 , 'msg'=>'正确','data'=>$url]);
    }

    /**
     * 购物卡支付
     */
    public function shopping_pay(){
        $user_id      = $this->get_user_id();
        // $user_id      = 91;
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $openid = Db::name('member')->where('id',$user_id)->value('openid');
        $data['money'] = input('money',0);
        if($data['money'] == 0){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'充值金额不能小于0.01','data'=>'']);
        }
        //生成订单
        $data['order_sn'] = "C".date('YmdHis',time()).rand(10000,99999);
        $data['user_id'] = $user_id;
        $data['addtime'] = time();
        $data['shop_name'] = '购物卡充值';
        $data['desc'] = '+'.$data['money'];
        $data['type'] = 0;//0微信支付
        $res = Db::name('member_order')->insert($data);
        // $data['money'] = $data['money']*100;
        if(!$res){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'订单生成失败','data'=>'']);
        }
        $rechData['order_no']        =  $data['order_sn'];
        $rechData['subject']        = '购物卡充值';
        $rechData['body']            = '购物卡充值';
        $rechData['timeout_express'] = time() + 600;
        $rechData['amount']          = $data['money'];
        $rechData['product_id']          = '';
        $rechData['return_param']          = '';
        $rechData['client_ip']          = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
        $rechData['openid']       = $openid;
        $wxConfig = Config::get('wx_config');
        $config = Db::name('config')->where('module',1)->column('name,value');
        $wxConfig['app_id'] = $config['appid']?$config['appid']:$wxConfig['app_id'];
        $wxConfig['app_secret'] = $config['appsecret']?$config['appsecret']:$wxConfig['app_secret'];
        $wxConfig['mch_id'] = $config['mch_id']?$config['mch_id']:$wxConfig['mch_id'];
        $wxConfig['md5_key'] = $config['md5_key']?$config['md5_key']:$wxConfig['md5_key'];
        $url      = Charge::run(PayConfig::WX_CHANNEL_PUB, $wxConfig, $rechData);
        // $url['number']=$number;
        $this->ajaxReturn(['status' => 1 , 'msg'=>'正确','data'=>$url]);
    }

    /**
     * 购物卡微信回调
     */
    public function wx_shopping_notify()
    {
        $receipt = $_REQUEST;
        if($receipt==null){
            $receipt = file_get_contents("php://input");
            if($receipt == null){
                $receipt = $GLOBALS['HTTP_RAW_POST_DATA'];
            }
            $this->shopping_notify($receipt);
        }
        // return $receipt;
    }

    /**
     * 购物卡充值回调
     */
    public function shopping_notify(array $data)
    {
        if($result == null){
            return;
        }
        $result = '';
        if($result['result_code'] =='SUCCESS'){
            // $data['order_no']//订单号
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
        }
        if($result){
            //返回给微信的数据
            $return['return_code'] = 'SUCCESS';
            $return['return_msg'] = 'OK';
            $xml_post = '<xml>
                        <return_code>'.$return['return_code'].'</return_code>
                        <return_msg>'.$return['return_msg'].'</return_msg>
                        </xml>';
            echo $xml_post;exit;
        }
    }

    /***
     * 支付宝回调
     */
    public function alipay_notify(){
        $callback = new TestNotify();
        $config   = Config::get('pay_config');
        $ret      = Notify::run('ali_charge', $config, $callback);
        echo  $ret;
    }

    /***
     * 微信支付回调
     */
    public function weixin_notify(){

        $data = file_get_contents("php://input");
        //   write_log($data);
        $re = $this->xmlToArray($data);

        if($re['result_code'] == 'SUCCESS'){
            //支付成功
            update_pay_status($re['out_trade_no'],$re);

        }
      
        // $callback = new TestNotify();
        // $config   = Config::get('wx_config');
        // $ret      = Notify::run('wx_charge', $config, $callback);
        // echo  $ret;
    }

    
    public function xmlToArray($xml)
    {
        $obj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $json = json_encode($obj);
        $arr = json_decode($json, true);
        return $arr;
    }


    private function MakeSign($arr)
    {
        //签名步骤一：按字典序排序参数
        ksort($arr);
        $string = $this->ToUrlParams($arr);
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=DFHFTGJ54DFHfgjffggh342nerge4334";
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }
    private function ToUrlParams($arr)
    {
        $buff = "";
        foreach ($arr as $k => $v)
        {
            if($k != "sign" && $v != "" && !is_array($v)){
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }


    /**
     * 微信支付完成回调
     */
    public function notify()
    {
        $weixin_pay_arr = Config::get('th_wx_config');
        $app_id         = $weixin_pay_arr['appid'];
        $app_key        = $weixin_pay_arr['appsecret'];
        $mch_id         = $weixin_pay_arr['mch_id'];
        $mch_key        = $weixin_pay_arr['mch_key'];

        $notify = new Notify($app_id, $app_key, $mch_id, $mch_key);

        // 支付校验失败
        $transaction = $notify->verify();
        if (!$transaction) {
            echo $notify->reply('FAIL', 'verify transaction error');
            return;
        }
        $wechat_result = $transaction->toArray();
        $order_no      = $wechat_result['out_trade_no'];
     
        //使用统一的日志函数
        pft_log('wxpay_notify', json_encode([$wechat_result]), 'month');
        // 判断是否已经处理过(微信可能通知多次)并获取agent_id用于通知
        $has_order = Db::table('user_order')->alias('uo')
        ->join('machine m', 'uo.machine_id = m.machine_id', 'LEFT')
        ->join('place p', 'm.place_id = p.place_id', 'LEFT')
        ->field('uo.order_id, uo.amount, uo.service_name,uo.good_id, uo.good_name, uo.good_price, uo.paid, uo.end_price, uo.order_time, uo.order_amount, uo.subject, uo.body, uo.uid, uo.wx_openid, uo.cst_id, m.mac, m.gw_did, m.machine_id, m.rm_id, p.area, p.channel_id, p.agent_id, p.place_id, p.pf_div, p.channel_div, p.agent_div, p.place_div,m.factory_id,p.place_name,m.platform_id,uo.create_time,m.app_protocol_type,m.machine_width,m.machine_type')
        ->where('uo.order_no', $order_no)
        ->find();
        if (!$has_order) {
            echo   $notify->reply('FAIL', 'no this transaction');
            return;
        } else if ($has_order['paid']) {
            echo   $notify->reply();
            return;
        }
      
        $end_price = $has_order['order_amount']*$has_order['place_div'];
        $order = [
            'area_id'        => $has_order['area'], //支付完成时设置更精准
            'channel_id'     => $has_order['channel_id'], //支付完成时设置更精准
            'agent_id'       => $has_order['agent_id'], //支付完成时设置更精准
            'place_id'       => $has_order['place_id'], //支付完成时设置更精准
            'mac'            => $has_order['mac'], //支付完成时设置更精准
            'pf_div'         => $has_order['pf_div'],
            'channel_div'    => $has_order['channel_div'],
            'agent_div'      => $has_order['agent_div'],
            'place_div'      => $has_order['place_div'],
            'paid'           => 1,
            'time_paid'      => time(),
            'transaction_id' => $order_no,
            'end_price'      => $end_price,//主账户最终收益
        ];
        
        $ress  = Db::table('user_order')->where(['order_no' => $order_no])->update($order);
        $censuso = [
            'agent_id'     => $has_order['agent_id'],
            'order_id'     => $has_order['order_id'],
            'place_id'     => $has_order['place_id'],
            'order_amount' => $has_order['order_amount'],
            'amount'       => $end_price,
            'division'     => $has_order['place_div'],
            'create_time'  => $has_order['create_time'],
            'order_type'   => 1,
       ];
       Db::table('agent_census')->insert($censuso);
        //记录收益统计
        $total_order = [
            'agent_id'       => $has_order['agent_id'], //商户ID
            'order_no'       => $order_no, //订单号
            'place_id'       => $has_order['place_id'], 
            'machine_id'     => $has_order['machine_id'], 
            'order_id'       => $has_order['order_id'],
            'place_name'     => $has_order['place_name'],
            'order_time'     => $has_order['order_time'],
            'order_amount'   => $has_order['order_amount'],
            'good_name'      => $has_order['good_name'],
            'good_price'     => $has_order['good_price'],
            'order_type'     => 1,
            'pay_type'       => 0,
            'service_name'   => $has_order['service_name'],
            'end_price'      => $end_price,//主账户最终收益
            'create_time'    => time(),
        ];
        Db::table('total_order')->insert($total_order);
        //商户总收益和余额
        $rema = [
            'order_num'         =>  ['exp', 'order_num+1'],
            'remainder'         =>  ['exp', 'remainder+'.$end_price.''],
            'profit'            =>  ['exp', 'profit+'.$end_price.''],
        ];
         Db::table('agent')->where(['agent_id'=>$has_order['agent_id']])->update($rema);

        //商户今日收益
        
         $todaywhere['agent_id']    =    $has_order['agent_id'];
         $todaywhere['create_time'] =    strtotime(date("Y-m-d"));
         $today_info = Db::table('today_profit')->where($todaywhere)->find();
         if($today_info){
            $todayprofit = [
                'profit'            =>  ['exp', 'profit+'.$end_price.''],
            ];
            // Db::table('agent')->where(['agent_id' => $has_order['agent_id']])->update($todayprofit);
            Db::table('today_profit')->where($todaywhere)->update($todayprofit);
         }else{
            $todaywhere['profit'] = $end_price;
            Db::table('today_profit')->insert($todaywhere);
         }

         //子账号今日收益
         $accountwhere['account_id'] = $has_order['agent_id'];
         $accountlist=Db::table('agent')->field('agent_id,division,profit,remainder')->where($accountwhere)->select();
        if(count($accountlist)>0){
            foreach($accountlist as $v){
                $perplace    = Db::table('user_permission')->where('agent_id',$v['agent_id'])->value('place_id');
                $perplacearr = explode(',',$perplace);
                    if(in_array($has_order['place_id'],$perplacearr)){
                        
                        $profit = $has_order['order_amount']   *    $v['division'];
                        $todayaccount['agent_id']              =    $v['agent_id'];
                        $todayaccount['create_time']           =    strtotime(date("Y-m-d"));
                        $remass = [
                            'order_num'                        =>  ['exp', 'order_num+1'],
                            'remainder'                        =>  ['exp', 'remainder+'.$profit.''],
                            'profit'                           =>  ['exp', 'profit+'.$profit.''],
                        ];
                        Db::table('agent')->where(['agent_id' => $v['agent_id']])->update($remass);
                        $census = [
                            'amount'       => $profit,
                            'agent_id'     => $v['agent_id'],
                            'order_id'     => $has_order['order_id'],
                            'place_id'     => $has_order['place_id'],
                            'order_amount' => $has_order['order_amount'],
                            'division'     => $v['division'],
                            'create_time'  => $has_order['create_time'],
                            'order_type'   => 1,
                       ];
                        Db::table('agent_census')->insert($census);
                        $account_res = Db::table('today_profit')->where($todayaccount)->find();
                        if($account_res){
                            $todayprofitacc = [
                                'profit'          =>  ['exp', 'profit+'.$profit.''],
                            ];
                            Db::table('today_profit')->where($todayaccount)->update($todayprofitacc);
                         }else{
                            $todayaccount['profit'] = $profit;
                            Db::table('today_profit')->insert($todayaccount);
                         }
                        $remainder=[
                            'account_id'  => $v['agent_id'],
                            'agent_id'    => $has_order['agent_id'],
                            'price'       => $profit,
                            'order_id'    => $has_order['order_id'],
                            'platform_id' => $has_order['platform_id'],
                            'create_time' => time()
                        ];
                        Db::table('account_remainder')->insert($remainder);
                    }
             
            }
        }

        Db::table('account_remainder')->insert(['agent_id'=>$has_order['agent_id'],'account_id'=>$has_order['agent_id'],'order_id'=> $has_order['order_id'], 'create_time' => time(),'platform_id' => $has_order['platform_id'],'price'=> $end_price,]);
        //设备启动       
        Start::start_machine($has_order['machine_id'],$has_order['order_time'],0,false);
  
  
        $timeend =$time_min*60+time();
        $machineup = [
            'work_time'         =>  ['exp', 'work_time+'.$has_order['order_time'].''],
            'work_count'        =>  ['exp', 'work_count+1'],
            'profit'            =>  ['exp',  'profit+'.$has_order['order_amount'].''],
            'state'             =>  3,
            'work_endtime'      =>  $timeend,
        ];
        Db::table('machine')->where(['machine_id'=>$has_order['machine_id']])->update($machineup);
      
        $res = true;
        //记录控制的结果
        $controlData = [
            'machine_id'  => $has_order['machine_id'],
            'mac'         => $has_order['mac'],
            'order_id'    => $has_order['order_id'],
            'uid'         => $has_order['uid'],
            'wx_openid'   => $has_order['wx_openid'],
            'is_auto'     => 1,
            'event'       => 1, //控制启动/叠加
            'retcode'     => $res ? 0 : 1101,
            'create_time' => time(),
        ];
        Db::table('machine_control_log')->insert($controlData);
        $orderData = [
            'use_time' => time(),
            'cmd_sent_count' => 1,
            'cmd_sent_time'  => time(),
        ];
        //默认控制成功
        if($has_order['machine_type'] == 2 || $has_order['app_protocol_type'] == 1){

        }else{
            $order['is_used']   = 1;
        }
       
        Db::table('user_order')->where(['order_no' => $order_no])->update($orderData);
    

        //报表统计
        StatisService::addStatisAsynchTask($has_order['order_id']);
        //咪小二分润
        if ($has_order['cst_id']) {
            $couponModel = new CouponModel();
            $couponInfo  = $couponModel->getCouponReceiveInfo($has_order['cst_id'], 'suid');

            if (isset($couponInfo['suid']) && $couponInfo['suid'] > 0 ) {
                $taskExecuteModel = new TaskExecuteModel();
                $params = json_encode([$order_no, $has_order['order_amount'] * 100, $has_order['cst_id']]);
                $taskExecuteModel->addTask('100011', $params);
            }
        }

        // 获取需要通知的人并发送通知
        $openid_list = Db::table('agent_notice_config')
        //->where('agent_id', ['=',0], ['=',$has_order['agent_id']], 'OR')
            ->where('(all_agent=1 OR agent_id=' . $has_order['agent_id'] . ' OR place_id=' . $has_order['place_id'] . ')')
            ->where('event', 1)
            ->column('wx_openid');
        if ($openid_list) {
            $title  = '您有一位客户付款成功！ (' . str_replace(' - ' . $has_order['subject'], '', $has_order['body']) . ')';
            $name   = $has_order['subject'];
            $amount = $has_order['order_amount'];
            $time   = $order['time_paid'];
            $remark = '点击进入平台查看更多信息';
            $i      = 0;
            foreach ($openid_list as $openid) {
                if (++$i > 1) {
                    usleep(50000);
                }

                $this->_notice_agent($openid, $title, $name, $amount, $time, $remark);
            }
        }

        // 应答微信
        echo $notify->reply();
    }



    private function _notice_agent($openid, $title = '您有一位客户付款成功！', $name = '零钱微SPA', $amount, $time, $remark = '')
    {
        // 发送客户付款成功模板消息
        try {
            $appid     = Config::get('biz_wx_config.appid');
                        $appsecret = Config::get('biz_wx_config.appsecret');
            $notice    = new Notice($appid, $appsecret);

            $template = Config::get('biz_wx_tmplmsg.pay_success');
            $url      = url('admin/order/index', '', true, true);
            $color    = '#FF0000';
            $data     = array(
                'first'    => [$title . "\n", '#078610'],
                'keyword1' => [$name, '#014D79'], //名称
                'keyword2' => ['￥' . $amount, '#014D79'], //金额
                'keyword3' => [date('Y-m-d H:i:s', $time), '#014D79'],
                'remark'   => [$remark ? "\n" . $remark : '', '#014D79'],
            );

            $messageId = $notice->to($openid)->template($template)->data($data)->url($url)->color($color)->send();
        } catch (Exception $e) {
        } catch (\Exception $e) {
        }
    }

    //微信退款
    public function wx_refund($order_id='3483')
    {
        $order = Db::name('order')->field('transaction_id,order_sn,order_amount')->where('order_id',$order_id)->find();
        $refund_order = Db::name('refund_apply')->field('price,real_pay_price,status')->where('order_id',$order_id)->find();
        if($order && $refund_order['status'] == 0){
            // $config = config('wx_config');
            $parma = array(
                'appid'=> Db::name('config')->where('name','appid')->value('value'),
                'mch_id'=> Db::name('config')->where('name','mch_id')->value('value'),
                'nonce_str'=> $this->createNoncestr(),
                'out_refund_no'=> $order['order_sn'],
                'transaction_id'=> $order['transaction_id'],//微信订单号
                'total_fee'=> $order['order_amount']*100,//订单金额
                'refund_fee'=> $refund_order['price']*100,//退款金额
            );
            if($parma['transaction_id'] == 'jifen'){
                $result['return_code'] = 'FAIL';
                $result['return_msg'] = '积分支付不能退款';
                return $result;
            }
            $parma['sign'] = $this->getSign($parma);
            $xmldata = $this->arrayToXml($parma);
            $xmlresult = $this->postXmlSSLCurl($xmldata,'https://api.mch.weixin.qq.com/secapi/pay/refund');
            $result = $this->xmlToArray($xmlresult);
            if($result['return_code'] == 'SUCCESS'){
                $data['order_id'] = $order_id;
                $data['addtime'] = time();
                $data['price'] = $refund_order['price'];
                Db::name('refund_log')->insert($data);
            }
            return $result;
        }
        return false;
    }

    /*
    * 对要发送到微信统一下单接口的数据进行签名
    */
    protected function getSign($Obj){
        foreach ($Obj as $k => $v){
            $Parameters[$k] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap($Parameters, false);
        //签名步骤二：在string后加入KEY
        $key = Db::name('config')->where('name','md5_key')->value('value');
        $String = $String."&key=".$key;
        //签名步骤三：MD5加密
        $String = md5($String);
        //签名步骤四：所有字符转为大写
        $result_ = strtoupper($String);
        return $result_;
    }

    /*
    *排序并格式化参数方法，签名时需要使用
    */
    protected function formatBizQueryParaMap($paraMap, $urlencode)
    {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v)
        {
            if($urlencode)
            {
                $v = urlencode($v);
            }
            //$buff .= strtolower($k) . "=" . $v . "&";
            $buff .= $k . "=" . $v . "&";
        }
        $reqPar = "";
        if (strlen($buff) > 0)
        {
            $reqPar = substr($buff, 0, strlen($buff)-1);
        }
        return $reqPar;
    }

    /*
    * 生成随机字符串方法
    */
    protected function createNoncestr($length = 32 ){
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str ="";
        for ( $i = 0; $i < $length; $i++ ) {
            $str.= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }
        return $str;
    }
    //数组转字符串方法
    protected function arrayToXml($arr){
        $xml = "<xml>";
        foreach ($arr as $key=>$val)
        {
            if (is_numeric($val)){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        return $xml;
    }

    protected static function xmlToArray2($xml){
        $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $array_data;
    }

    //需要使用证书的请求
    private function postXmlSSLCurl($xml,$url,$second=30)
    {
        $config = config('pay_weixin');
        $ch = curl_init();
        //超时时间
        curl_setopt($ch,CURLOPT_TIMEOUT,$second);
        //这里设置代理，如果有的话
        //curl_setopt($ch,CURLOPT_PROXY, '8.8.8.8');
        //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
        //设置header
        curl_setopt($ch,CURLOPT_HEADER,FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
        //设置证书
        //使用证书：cert 与 key 分别属于两个.pem文件
        //默认格式为PEM，可以注释
        curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLCERT, $config['app_cert_pem']);
        //默认格式为PEM，可以注释
        curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLKEY,$config['app_key_pem']);
        //post提交方式
        curl_setopt($ch,CURLOPT_POST, true);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$xml);
        $data = curl_exec($ch);
        //返回结果
        if($data){
            curl_close($ch);
            return $data;
        }
        else {
            $error = curl_errno($ch);
            echo "curl出错，错误码:$error"."<br>";
            curl_close($ch);
            return false;
        }
    }

    //微信退款回调--不用异步了
    public function weixin_refund()
    {
        $data = file_get_contents("php://input");
        //   write_log($data);
        // $data = $this->xmlToArray($data);
        // if($data['return_code'] == 'SUCCESS'){
        //     $order_sn = $data['return_code'];
        // }
    }

}
