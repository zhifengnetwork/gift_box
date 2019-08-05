<?php
namespace app\admin\controller;

use app\common\model\Order as OrderModel;
use app\common\model\OrderGoods as OrdeGoodsModel;
use app\common\model\OrderRefund;
use Overtrue\Wechat\Payment\Business;
use Overtrue\Wechat\Payment\QueryRefund;
use Overtrue\Wechat\Payment\Refund;
use think\Request;
use \think\Db;
use think\Exception;
use think\Session;

//物流api
use app\home\controller\Api;

class Order extends Common
{
   /**
     * 订单列表
     */
    public function index()
    {
        $begin_time        = input('begin_time', '');
        $end_time          = input('end_time', '');
        $order_id          = input('order_id', '');
        $invoice_no        = input('invoice_no', '');
        $orderstatus       = input('orderstatus',-1);
        $kw                = input('kw', '');
        $paycode           = input('paycode', -1);
        $paystatus         = input('paystatus',-1);
        $where = []; 
        if (!empty($order_id)) {
            $where['uo.order_sn']    = array('like','%'.$order_id.'%');
        }
        if (!empty($invoice_no)) {
            $where['d.invoice_no']   = array('like','%'.$invoice_no.'%');
        }
        if($orderstatus >= 0){
            $where['uo.order_status'] = $orderstatus;
        }
        if($paycode >= 0){
            $where['uo.pay_code'] = $paycode;
        }
        if($paystatus >= 0){
            $where['uo.pay_status'] = $paystatus;
        }
        if(!empty($kw)){
            is_numeric($kw)?$where['uo.mobile'] = array('like','%'.$kw.'%'):$where['a.realname'] = array('like','%'.$kw.'%');
        }
         // 携带参数
        $carryParameter = [
            'kw'               => $kw,
            'begin_time'       => $begin_time,
            'end_time'         => $end_time,
            'invoice_no'       => $invoice_no,
            'orderstatus'      => $orderstatus,
            'paycode'          => $paycode,
            'paystatus'        => $paystatus,
        ];
        $list  = OrderModel::alias('uo')->field('uo.*,d.order_id as order_idd,d.invoice_no,a.realname')
                ->join("delivery_doc d",'uo.order_id=d.order_id','LEFT')
                ->join("member a",'a.id=uo.user_id','LEFT')
                ->where($where)
                ->order('uo.order_id DESC')
                ->paginate(10, false, ['query' => $carryParameter]);
        
        // 模板变量赋值
        //订单状态
        $order_status     = config('ORDER_STATUS');
        $order_status['-1'] = '默认全部';
        //支付方式
        $pay_type         = config('PAY_TYPE');
        $pay_type['-1']     = '默认全部';
        //支付状态
        $pay_status         = config('PAY_STATUS');
        $pay_status['-1']     = '默认全部';

        // 导出
        $exportParam            = $carryParameter;
        $exportParam['tplType'] = 'export';
        $tplType                = input('tplType', '');
        if ($tplType == 'export') {
            $list  = OrderModel::alias('uo')->field('uo.*,d.order_id as order_idd,d.invoice_no,a.realname')
                ->join("delivery_doc d",'uo.order_id=d.order_id','LEFT')
                ->join("member a",'a.id=uo.user_id','LEFT')
                ->where($where)
                ->order('uo.order_id DESC')
                ->select();
            $str = "订单ID,用户id,订单金额\n";

            foreach ($list as $key => $val) {
                $str .= $val['order_id'] . ',' . $val['user_id'] . ',' . $val['order_amount'] . ',';
                $str .= "\n";
            }
            export_to_csv($str, '订单列表', $exportParam);
        }
        return $this->fetch('',[ 
            'list'         => $list,
            'exportParam'  => $exportParam,
            'order_status' => $order_status,
            'pay_type'     => $pay_type,
            'pay_status'   => $pay_status,
            'kw'           => $kw,
            'invoice_no'   => $invoice_no,
            'paystatus'    => $paystatus,
            'orderstatus'  => $orderstatus,
            'paycode'      => $paycode,
            'order_id'     => $order_id,
            'begin_time'   => empty($begin_time)?date('Y-m-d'):$begin_time,
            'end_time'     => empty($end_time)?date('Y-m-d'):$end_time,
            'meta_title'   => '订单列表',
        ]);
      
    }

    //参与群抢名单
    public function join_list(){
        $order_id       =  input('order_id',''); 
        $list  = M('gift_order_join')->alias('goj')->join('member m','goj.user_id=m.id','left')->join('order o','goj.order_id=o.order_id','left')->field('goj.*,m.nickname,m.avatar,o.lottery_time')->where(['goj.order_id'=>$order_id])->whereor(['o.parent_id'=>$order_id])->select();

        foreach($list as $k=>$v){
            $list[$k]['start'] = (($v['lottery_time'] < time()) && ($v['lottery_time'] > 0)) ? 1 : 0;
        }

        return $this->fetch('',[ 
            'list'         => $list,
            'order_id'     => $order_id,
            'meta_title'   => '群抢参与列表',
        ]);
    }

    public function setgift(){
        $id       =  input('id',''); 
        $status   =  input('status','0');  
        if(!$id || !$status){
            echo json_encode(['status' => -1 , 'msg'=>'参数错误','data'=>'']);
            return;
        }

        $info = M('gift_order_join')->find($id);
        if($status == 1){  //设置为中奖
            $giftorderid = M('Order')->where(['parent_id'=>$info['order_id'],'gift_uid'=>0])->column('order_id'); //需开奖的订单
            if($giftorderid){
                Db::startTrans();
                $r1 = M('gift_order_join')->update(['id'=>$id,'order_id'=>$giftorderid[0],'status'=>1]);     
                $r2 = M('Order')->update(['order_id'=>$giftorderid[0],'gift_uid'=>$info['user_id']]);
                if((false !== $r1) && (false !== $r2)){
                    Db::commit();     
                    echo json_encode(['status' => 1 , 'msg'=>'设置成功！','data'=>'']);  
                }else{
                    Db::rollback();
                    echo json_encode(['status' => -1 , 'msg'=>'设置失败！','data'=>'']);  
                    return;
                }
            }else{
                echo json_encode(['status' => -1 , 'msg'=>'开奖订单已全部中奖！','data'=>'']);  
                return;
            }
        }elseif($status == 2){  //设置为未中奖
            if($info['join_status'] != 0){
                echo json_encode(['status' => -1 , 'msg'=>'已不能设置','data'=>'']);   
                return;
            }
            
            Db::startTrans();    
            $parent_id = M('Order')->where(['order_id'=>$info['order_id']])->value('parent_id');    
            $r1 = M('gift_order_join')->update(['id'=>$id,'order_id'=>$parent_id,'status'=>2]);      
            $r2 = M('Order')->update(['order_id'=>$info['order_id'],'gift_uid'=>0]);  

            if((false !== $r1) && (false !== $r2)){
                Db::commit();     
                echo json_encode(['status' => 1 , 'msg'=>'设置成功！','data'=>'']);  
                return;
            }else{
                Db::rollback();
                echo json_encode(['status' => -1 , 'msg'=>'设置失败！','data'=>'']);  
                return;
            }
        }
    }
    
    /**
     * 订单详情
     */
    public function edit(){
        $order_id       =  input('order_id','');
        $orderGoodsMdel =  new OrdeGoodsModel();
        $orderModel     =  new OrderModel();
        $order_info     =  $orderModel->where(['order_id'=>$order_id])->find();
        $orderGoods     =  $orderGoodsMdel::all(['order_id'=>$order_id,'is_send'=>['lt',2]]);
        
         //订单状态
         $this->assign('order_status', config('ORDER_STATUS'));
         $this->assign('pay_status', config('PAY_STATUS'));
         $this->assign('shipping_status', config('SHIPPING_STATUS'));
         //支付方式
         $this->assign('type_list',config('PAY_TYPE'));
        //物流
        // $Api = new Api;
        // $data = M('delivery_doc')->where('order_id', $order_id)->find();
        // $shipping_code = $data['shipping_code'];
        // $invoice_no = $data['invoice_no'];
        // $result = $Api->queryExpress($shipping_code, $invoice_no);
        // if ($result['status'] == 0) {
        //     $result['result'] = $result['result']['list'];
        // }
        // $this->assign('invoice_no', $invoice_no);
        // $this->assign('result', $result);
        $this->assign('orderGoods', $orderGoods);
        $this->assign('order_info', $order_info);
        $this->assign('meta_title', '订单详情');
        return $this->fetch();
    }

    /***
     * 退换货列表
     */
    /* public function order_refund(){
        $refundstatus  = input('refundstatus',-1);
        $order_id       = input('order_id','');
        $where = array();
        if(!empty($order_id)){
            $where['order_sn']       = $order_id;
        }

        if($refundstatus >= 0){
            $where['uo.refund_status']   = $refundstatus;
        }

        $list  = Db::name('order_refund')->alias('uo')->field('uo.*,order_sn,order_amount')
                ->join("order d",'uo.order_id=d.order_id','LEFT')
                ->where($where)
                ->order('uo.id DESC')
                ->paginate(10, false, ['query' => [
                    'refundstatus' => $refundstatus,
                    'order_id'      => $order_id,
                ]]);
        //退换货状态
       
        $refund_status           = config('REFUND_STATUS');
        $refund_status['-1']     = '默认全部';
        //退货原因
        $refund_reason           = config('REFUND_REASON');
        return $this->fetch('',[
            'meta_title'    => '退换货列表', 
            'list'          => $list, 
            'refund_reason' => $refund_reason,
            'refund_status' => $refund_status,
            'order_id'      => $order_id,
            'refundstatus'  => $refundstatus,
        ]);
    } */
    public function order_refund(){
        $refundstatus  = input('refundstatus',-1);
        $order_id       = input('order_id','');
        $where = array();
        if(!empty($order_id)){
            $where['o.order_sn']       = $order_id;
        }

        if($refundstatus >= 0){
            $where['ra.status']   = $refundstatus;
        }

        $list  = Db::name('refund_apply')->alias('ra')->field('ra.*,o.order_sn,og.spec_key_name')
                ->join("order o",'ra.order_id=o.order_id','LEFT')
                ->join("order_goods og",'ra.rec_id=og.rec_id','LEFT')
                ->where($where)
                ->order('ra.addtime DESC')
                ->paginate(10, false, ['query' => [
                    'ra.status' => $refundstatus,
                    'o.order_sn'      => $order_id,
                ]]);
        //退换货状态
       
        $refund_status           = config('REFUND_STATUS');
        $refund_status['-1']     = '默认全部';
        //退货原因
        $refund_reason           = config('REFUND_REASON');
        return $this->fetch('',[
            'meta_title'    => '退换货列表', 
            'list'          => $list, 
            'refund_reason' => $refund_reason,
            'refund_status' => $refund_status,
            'order_id'      => $order_id,
            'refundstatus'  => $refundstatus,
        ]);
    }
    /**
     *退换货详情
     */
    public function refund_edit(){
        $id    = input('id');
        $info  =  Db::name('refund_apply')->alias('ra')->field('ra.*,o.order_sn,og.spec_key_name,m.nickname')
                ->join("order o",'ra.order_id=o.order_id','LEFT')
                ->join("order_goods og",'ra.rec_id=og.rec_id','LEFT')
                ->join("member m",'ra.user_id=m.id','LEFT')
                ->where(['ra.id' => $id])
                ->find();
        $info['pic'] && ($info['pic'] = explode(',',info['pic']));
        if( Request::instance()->isPost()){

            $status = input('status/d',0);
            $handle_remark = input('handle_remark/s','');
            $update = [
                //'end_time'        => time(),
                'remark'   => $handle_remark,
                'status'   => $status,
            ]; 
            if($status == 2){
                $update['on_time'] = time();
            }
            /*
            if($refund_status == 2){
                //todo::调用退款程序 
               $relut = OrderRefund::refund_obj($info);
               


            }*/
            $res = Db::name('refund_apply')->where(['id' => $id])->update($update);

            if($res !== false){
                $this->success('审核成功', url('order/refund_edit',['id' => $id]));
            }
            $this->error('审核失败');


        }
        return $this->fetch('',[
            'meta_title'    => '退换货详情', 
            'info'          => $info, 
            'refund_reason' => config('REFUND_REASON'), //退货原因
            'refund_status' => config('REFUND_STATUS'),//退换货状态
            'refund_apply_type' => config('REFUND_APPLY_TYPE'),//退换类型
        ]);

    }
    /***
     * 发货单列表
     */
    public function  delivery_list(){

        
        $shipping_status = input('shipping_status',-1);
        $consignee       = input('consignee','');
        $order_sn        = input('order_sn','');

        $where = array();

        if(!empty($consignee)){
            $where['uo.consignee']  = $consignee;
        }

        if(!empty($order_sn)){
            $where['uo.order_sn']   = $order_sn;
        }

        if($shipping_status >= 0){
            $where['uo.shipping_status']   = $shipping_status;
        }

        $where['uo.pay_status']   = 1;

        // $where['uo.pay_status']   = 1;

        // $where['uo.order_status'] = array('in','0,1,2,4');

        $list  = OrderModel::alias('uo')->field('uo.*')
                ->order('uo.order_id DESC')
                ->where($where)
                ->paginate(10, false, ['query' => [
                    'shipping_status' => $shipping_status,
                    'consignee'       => $consignee,
                    'order_sn'        => $order_sn
                ]]);
        return $this->fetch('',[
            'meta_title'  => '发货单列表', 
            'list'        => $list,
            'consignee'   => $consignee,
            'order_sn'    => $order_sn
        ]);
        

    }
    
    /***
     *发货单编辑
     */
    public function delivery_info($id=''){
        if($id){
            $order_id   = $id; 
        }else{
            $ids        = input('order_id','');
            $order_id   = trim($ids,',');
        }
    	$orderGoodsMdel =  new OrdeGoodsModel();
        $orderModel     =  new OrderModel();
        $orderObj       =  $orderModel->where(['order_id'=>$order_id])->find();
      
        $order          =  $orderObj->append(['full_address'])->toArray();
        $orderGoods     =  $orderGoodsMdel::all(['order_id'=>$order_id,'is_send'=>['lt',2]]);
       
        
        if($id){
            if(!$orderGoods){
                $this->error('所选订单有商品已完成退货或换货');//已经完成售后的不能再发货
            }
        }else{
            if(!$orderGoods){
                $this->error('此订单商品已完成退货或换货');//已经完成售后的不能再发货  
            }
        }
        

        $delivery_record = Db::name('delivery_doc')->alias('d')->where('d.order_id='.$order_id)->select();
        if(!empty($delivery_record)){
            $order['invoice_no'] = $delivery_record[count($delivery_record)-1]['invoice_no'];
        }else{
            $order['invoice_no'] = '';
        }
        $this->assign('order',$order);
        $this->assign('orderGoods',$orderGoods);
        $this->assign('delivery_record',$delivery_record);//发货记录
        $shipping_list = Db::name('shipping')->field('shipping_name,shipping_code')->where('')->select();
        $this->assign('shipping_list',$shipping_list);
        $this->assign('express_switch',0);
        $this->assign('meta_title','发货单编辑');
        return $this->fetch();    
    }


        /**
        *批量发货
        */
      public function delivery_batch(){
            $order_id  = input('order_id','');
            $order_id  = trim($order_id,',');
        
            $orderGoodsMdel = new OrdeGoodsModel();
            $orderModel     = new OrderModel();
            $orderObj       = $orderModel->whereIn('order_id',$order_id)->select();//订单
            $orderGoods     = $orderGoodsMdel::all(['order_id'=>['in',$order_id],'is_send'=>['lt',2]]);
            //订单商品
            
            if ($orderObj){
                $order = collection($orderObj)->append(['orderGoods','full_address'])->toArray();
            }
            if (!$orderGoods){
                $this->error('此订单商品已完成退货或换货');//已经完成售后的不能再发货  
            }
            print_r($order);
            die;
            $this->assign('order',$order);
            $this->assign('orderGoods',$orderGoods);
            $shipping_list = Db::name('shipping')->field('shipping_name,shipping_code')->where('')->select();
            $this->assign('shipping_list',$shipping_list);
            $this->assign('express_switch',0);
            $this->assign('order_ids',$order_id);
            return $this->fetch();    
        
    }


    /**
     * 生成发货单
     */
    public function deliveryHandle(){
        $data  = input('post.');
      
       
        if($data['send_type'] == 0 && isset($data['invoice_no']) && empty($data['invoice_no'])){
            $this->error('请输入配送单号');
        }

        if($data['send_type'] == 0 && isset($data['invoice_no']) && empty($data['invoice_no'])){
            $this->error('请输入配送单号');
        }

        if($data['send_type'] != 3 && isset($data['shipping_code']) && empty($data['shipping_code'])){
            $this->error('请选择物流');
        }
       
        if(!isset($data['goods']) || $data['shipping']  != 1 && count($data['goods']) < 1) {
            $this->error('请选择发货商品');
        }
       
        $count = 0;
        if(isset($data['pldelivery'])){
            foreach($data['order_id'] as $k => $v){
                $count++;
                $datas['shipping']      = $data['shipping'][$v];
                $datas['shipping_code'] = $data['shipping_code'][$v];
                $datas['send_type']     = $data['send_type'][$v];
                $datas['invoice_no']    = $data['invoice_no'][$v];
                $datas['order_id']      = $v;
                $datas['note']          = $data['note'][$v];
                $datas['goods']         = $data['goods'][$v];
                if(!empty($data['shipping_name'][$v])){
                    $datas['shipping_name'] = $data['shipping_name'][$v];
                }
                if(!empty($data['shipping_code'][$v])){
                    $datas['shipping_code'] = $data['shipping_code'][$v];
                }
                if(!empty($data['invoice_no'][$v])){
                    $datas['invoice_no'] = $data['invoice_no'][$v];
                }
                $res =(new OrderModel())->deliveryHandle($datas);
                if($count == count($data['order_id'])){
                    break;
                }
             }
             if($res['status'] == 1 && $count == count($data['order_id'])){
                $this->success('操作成功',url('order/delivery_list'));
             }else{
                $this->error($res['msg']);
             }
        }else{
             $res = (new OrderModel())->deliveryHandle($data);
             if($res['status'] == 1){
                  $this->success('操作成功',url('order/delivery_list'));
            }else{
                $this->success($res['msg']);
            }
        }
		
    }






    /***
     * 发货单信息管理
     */
    public function senduser(){
        $where      = array();
        $list       = Db::table('exhelper_senduser')->field('*')
        
                    ->where($where)
                    ->order('id')
                    ->paginate(10, false, ['query' => $where]);
        $this->assign('list', $list);
        $this->assign('meta_title', '发货单信息管理');
        return $this->fetch();
    }




    /***
     * 发货单打印
     */
    public function doprint(){
        $this->assign('meta_title', '发货单打印');
        return $this->fetch();
    }

    /***
     * 快递单和发货单模板管理
     */
    public function express(){
        $this->assign('meta_title', '模板管理');
        return $this->fetch();
    }

    /***
     * 打印设置
     */
    public function printset(){
        $printset = Db::table('exhelper_sys')->find();
        $this->assign('printset',$printset);
        $this->assign('meta_title', '打印设置');
        return $this->fetch();
    }

    /**
     * 获取where条件，一般用于列表或者筛选，整体项目结构统一
     * TODO: 获取的where对应的表前缀可能有点问题，输入变量与筛选字段的区别，...
     * @param  array $params_key  条件key数组
     * @param  array &$params_arr 结果参数数组，便于调用处使用
     * @return array              $where
     */
    private function &get_where()
    {
        $begin_time  = input('begin_time', '');
        $end_time    = input('end_time', '');
        $order_id    = input('order_id', '');
        $invoice_no  = input('invoice_no', '');
        $status      = input('order_status','');
        $kw                = input('kw', '');
        $pay_code          = input('pay_code', '');
        $pay_status        = input('pay_status', '');
        $where = [];
        if (!empty($order_id)) {
            $where['uo.order_id']    = $order_id;
        }
        if (!empty($invoice_no)) {
            $where['d.invoice_no']   = $invoice_no;
        }
        if(!empty($status)){
            $where['uo.order_status'] = $status;
        }
        if(!empty($pay_code)){
            $where['uo.pay_code'] = $pay_code;
        }
        if(!empty($pay_status)){
            $where['uo.pay_status'] = $pay_status;
        }
        // var_dump($where);
        // die;
        if(!empty($kw)){
            is_numeric($kw)?$where['uo.mobile'] = $kw:$where['a.realname'] = $kw;
        }
       

        // if ($begin_time && $end_time) {
        //     $where['uo.create_time'] = [['EGT', $begin_time], ['LT', $end_time]];
        // } elseif ($begin_time) {
        //     $where['uo.create_time'] = ['EGT', $begin_time];
        // } elseif ($end_time) {
        //     $where['uo.create_time'] = ['LT', $end_time];
        // }
        // $params_arr = array(
        //     'begin_time'      => $begin_time,
        //     'end_time'        => $end_time,
        // );
        $this->assign('kw', $kw);
        $this->assign('invoice_no', $invoice_no);
        $this->assign('status', $status);
        $this->assign('pay_status', $pay_status);
        $this->assign('pay_code', $pay_code);
        $this->assign('order_id', $order_id);
        $this->assign('begin_time', empty($begin_time)?date('Y-m-d'):$begin_time);
        $this->assign('end_time', empty($end_time)?date('Y-m-d'):$end_time);
        // $this->assign('params_arr', $params_arr);
        
        return $where;
    }

    //积分支付订单
    public function jifen()
    {
        $status = input('status',0);
        $order_sn = input('order_sn','');
        $where['id'] = array('gt',0);
        $query = array();
        if($status != ''){
           $where['oe.status'] = $status;
           $query['status'] = $status;
        }
        if($order_sn){
            $where['o.order_sn'] = array('like','%'.$order_sn.'%');
            $query['order_sn'] = $order_sn;
        }
        $status_arr = ['未审核','审核通过','审核不通过'];
        $order_list = Db::name('order')->alias('o')->field('o.order_sn,o.order_id,oe.id,o.pay_status,oe.addtime,oe.examine_time,oe.status,o.order_amount,oe.card_num,oe.card_name,oe.admin_name')->join('order_examine oe','oe.order_id=o.order_id')->where($where)->order('oe.addtime desc')->paginate(5, false, ['query' => $query]);
        //支付状态
        $pay_status         = config('PAY_STATUS');
        $this->assign('list',$order_list);
        $this->assign('status',$status);
        $this->assign('status_arr',$status_arr);
        $this->assign('pay_status',$pay_status);
        $this->assign('order_sn',$order_sn);
        return $this->fetch();
    }
    
    //积分审核
    public function jifen_examine()
    {
        if(Request::instance()->isPost()){
            $id = input('id',0);
            $status = input('status');
            $remarks = input('remarks');
            $admin_name = Session::get('admin_user_auth.username');
            if($status){
                $data['status'] = $status;
                $data['examine_time'] = time();
                $data['admin_name'] = $admin_name;
            }
            $data['remarks'] = $remarks;
            Db::name('order_examine')->where('id',$id)->update($data);
            $info = Db::name('order_examine')->where('id',$id)->find();
            //修改订单状态
            if($status == 1 && $info){
                Db::name('order')->where('order_id',$info['order_id'])->update(['pay_status'=>$status,'pay_time'=>time()]);
            }
            $this->success('操作成功',url('jifen'));
        }
        $id = input('id');
        $info = Db::name('order_examine')->where('id',$id)->find();
        $order = Db::name('order')->field('user_id,order_id,order_sn,order_amount')->where('order_id',$info['order_id'])->find();
        $info['nickname'] = Db::name('member')->where('id',$order['user_id'])->value('nickname');
        $this->assign('info',$info);
        $this->assign('order',$order);
        return $this->fetch();
    }

    //微信退款
    public function wx_refund()
    {
        $data['appid'] = Db::name('config')->where('name','appid')->value('value');
        $data['mch_id'] = Db::name('config')->where('name','mch_id')->value('value');
        $data['nonce_str'] = '';
        $data['sign'] = '';
        $data['sign_type'] = '';
        $data['transaction_id'] = '';
        $data['out_refund_no'] = '';
        $data['total_fee'] = '';
        $data['refund_fee'] = '';
        $data['refund_fee_type'] = 'CNY';
        $data['refund_desc'] = '';
        $data['notify_url'] = '';
    }
}
