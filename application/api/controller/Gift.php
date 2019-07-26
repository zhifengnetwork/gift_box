<?php
/**
 * 订单API
 */
namespace app\api\controller;
use app\common\logic\PointLogic;
use think\Db;
use app\api\controller\Goods;
use think\Request;

class Gift extends ApiBase
{
    //领取/参与
    public function receive_join(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $order_id = input('order_id/d',0);
        $join_type = input('join_type/d',0); //参与类型，1：领取，2：参与群抢
        
        // $pwdstr = input('pwdstr/s',''); //加密字符串
        // $arr = $this->decode_token($pwdstr);
        // if(!$arr || !$arr['exp'] || ($arr['exp'] < time())){
        //     $this->ajaxReturn(['status' => -1 , 'msg'=>'该链接已失效','data'=>'']);
        // }else{  //分享回调接口，user_id化用为id-order_id
        //     $resarr = explode('-',$arr['user_id']);
        //     $joinid = 0;
        //     if(count($resarr) == 2){
        //         $joinid = $resarr[0];
        //     }
        //     if((count($resarr) == 1) && ($order_id != $resarr[0]))
        //         $this->ajaxReturn(['status' => -1 , 'msg'=>'警告，参数错误！','data'=>'']);
        //     elseif((count($resarr) == 2) && ($order_id != $resarr[1]))
        //         $this->ajaxReturn(['status' => -1 , 'msg'=>'警告，参数错误！','data'=>'']);
        // }

        $order = Db::name('order')->field('order_status,shipping_status,pay_status,parent_id,order_type,lottery_time,giving_time,overdue_time,gift_uid')->where(['order_id'=>$order_id,'user_id'=>$user_id,'deleted'=>0])->find();
        if(!$order){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'订单不存在','data'=>'']);
        }elseif(($order['parent_id'] > 0) && ($join_type == 2)){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'子订单不能进行此操作','data'=>'']);
        }elseif($order['pay_status'] != 1){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'订单还未支付','data'=>'']);
        }elseif(!in_array($order['order_status'],[0,1])){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单状态已不允许执行此操作','data'=>'']);
        }elseif($order['order_type'] == 0){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单不是赠送订单','data'=>'']);
        }elseif(($order['order_type'] == 1) && ($join_type != 1)){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'参与类型不符合','data'=>'']);
        }elseif(($order['order_type'] == 2) && ($join_type != 2)){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'参与类型不符合','data'=>'']);
        }elseif($order['giving_time'] > 0){
            if(($order['order_type'] == 1) && ($order['overdue_time'] < time()))
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单赠送已过期啦！','data'=>'']);
            elseif(($order['order_type'] == 2) && ($order['lottery_time'] < time()))
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该群抢已经开奖啦！','data'=>'']);
            elseif(($order['order_type'] == 2) && ($order['overdue_time'] < time()))
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单赠送已过期啦！','data'=>'']);
            elseif($order['gift_uid'])
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单已有领取人啦！','data'=>'']);
        }elseif($order['giving_time'] == 0){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单未赠送！','data'=>'']);
        }

        $gojnum = Db::name('gift_order_join')->where(['order_id'=>$order_id,'user_id'=>$user_id,'join_status'=>['neq',4]])->count();
        if($gojnum){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'您已参与过啦！','data'=>['type'=>1]]);
        }

        $data = [
            'order_id'      => $order_id,
            'order_type'    => $order['order_type'],
            // 'order_type'    => $joinid ? 3 : $order['order_type'],
            'addtime'       => time(),
            'status'        => ($join_type == 1) ? 1 : 0,
            'user_id'       => $user_id,
            'parentid'      => 0,
            // 'parentid'      => $joinid,
        ];

        // 启动事务
        if($join_type == 1)Db::startTrans();
        $res = Db::name('gift_order_join')->insertGetId($data);
        if($res){
            if($join_type == 1){
                //领取成功则将 赠送时间，赠送/群抢过期时间，群抢开奖时间 设置为空，以转赠
                M('Order')->where(['order_id'=>$order_id])->update(['lottery_time'=>0,'giving_time'=>0,'overdue_time'=>0,'gift_uid'=>$user_id]);
                Db::name('gift_order_join')->where(['id'=>['neq',$res],'order_id'=>$order_id,'order_type'=>1])->update(['join_status'=>4]);
                // 提交事务
                Db::commit(); 
            }
            $this->ajaxReturn(['status' => 1 , 'msg'=>'请求成功！','data'=>$res]); 
        }else{
            // 回滚事务
            if($join_type == 1)Db::rollback();
            $this->ajaxReturn(['status' => -1 , 'msg'=>'请求失败！','data'=>'']); 
        }
    }

    //添加地址
    public function set_address(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $joinid = input('joinid/d',0); //参与ID
        $addressid = input('addressid/d',0); //地址ID
        $info = M('gift_order_join')->field('id')->where(['status'=>1,'user_id'=>$user_id])->find($joinid);
        if(!$info)
            $this->ajaxReturn(['status' => -1 , 'msg'=>'不存在此次参与','data'=>'']);
        if(!M('user_address')->where(['user_id'=>$user_id])->find($addressid))
            $this->ajaxReturn(['status' => -1 , 'msg'=>'不存在此用户地址','data'=>'']);    

        $res = Db::name('gift_order_join')->where(['id'=>$joinid])->update(['join_status'=>1,'addressid'=>$addressid]);
        if(false !== $res){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'请求成功！','data'=>'']); 
        }else{
            $this->ajaxReturn(['status' => 1 , 'msg'=>'请求失败！','data'=>'']); 
        }
    }

    //分享回调
    public function share_callback(){
        $user_id = $this->get_user_id();
        // $user_id = 86;
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $order_id = input('order_id/d',0);
        $act = input('act/d',0);  //操作，0回调，1：检测是否可分享，2：转赠检测，3：转赠回调
        
        $where = ['order_id'=>$order_id,'deleted'=>0];
        if(!in_array($act,[2,3]))$where['user_id'] = $user_id;

        $order = Db::name('order')->field('order_status,shipping_status,pay_status,parent_id,order_type,lottery_time,giving_time,overdue_time,gift_uid')->where($where)->find();
        
        if(!$order){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'订单不存在','data'=>'']);
        }elseif($order['pay_status'] != 1){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'订单还未支付','data'=>'']);
        }elseif(!in_array($order['order_status'],[0,1])){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单状态已不允许执行此操作','data'=>'']);
        }elseif($order['order_type'] == 0){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单不是赠送订单','data'=>'']);
        }elseif($order['giving_time'] > 0){
            //因无法监控微信分享是否成功，故取消以下判断
            /* if(($order['order_type'] == 1) && ($order['overdue_time'] < time()))
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单已赠送过啦！','data'=>'']);
            elseif($order['order_type'] == 2)
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单已赠送过啦！','data'=>'']);
            elseif($order['order_type'] == 2)
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单已赠送过啦！','data'=>'']);
            else */if($order['gift_uid'])
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单已有领取人啦！','data'=>'']);
        }elseif(($order['order_type'] == 2) && in_array($act,[2,3])){
            if($order['giving_time'] > 0){
                //因无法监控微信分享是否成功，故取消以下判断
                /* if($order['lottery_time'] < time())
                    $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单已赠送过啦！','data'=>'']);
                elseif($order['overdue_time'] < time())
                    $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单已赠送过啦！','data'=>'']);
                else */if($order['gift_uid'])
                    $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单已有领取人啦！','data'=>'']);
            }elseif(!$order['parent_id'])
                $this->ajaxReturn(['status' => -1 , 'msg'=>'群抢母订单不能转赠！','data'=>'']);
        }elseif(in_array($act,[2,3]) && ($order['gift_uid'] != $user_id))
            $this->ajaxReturn(['status' => -1 , 'msg'=>'您不能转赠该礼物！','data'=>'']);

        if(in_array($act,[2,3])){ //查看是否可以转赠
            $joininfo = M('gift_order_join')->field('id')->where(['order_id'=>$order_id,'status'=>1,'user_id'=>$user_id,'join_status'=>0,'addressid'=>0])->find();

            //只能转赠一次
            $joinnum = M('gift_order_join')->where(['order_id'=>$order_id,'join_status'=>5])->count();
            if($joinnum)
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该礼物已经被转赠过一次啦！','data'=>'']);
            
            if(!$joininfo)
                $this->ajaxReturn(['status' => -1 , 'msg'=>'您不能转赠该礼物啦！','data'=>'']);
        }

        if(in_array($act,[1,2]))
            $this->ajaxReturn(['status' => 1 , 'msg'=>'可以分享！','data'=>'']);
        
        //盒子发送多久开奖（分钟）
        $start_time = M('Config')->where(['id'=>50])->value('value'); 
        //盒子发送多久结束（分钟）
        $end_time = M('Config')->where(['id'=>51])->value('value'); 

        $data = [
            'giving_time'   => time(),
        ];

        if($act == 3){
            $order_id = $joininfo['id'] . '-' . $order_id;
        }

        if($order['order_type'] == 1){
            $data['overdue_time'] = (time() + $end_time * 60);
            $pwdstr = $this->create_token($order_id,$order['overdue_time']);
        }if($order['order_type'] == 2){
            $data['lottery_time'] = (time() + $end_time * 60);
            $data['overdue_time'] = (time() + $start_time * 60);
            $pwdstr = $this->create_token($order_id,$order['overdue_time']);
        }

        if($act == 3){
            // 启动事务
            Db::startTrans();

            $res = M('gift_order_join')->field('id')->where(['id'=>$joininfo['id']])->update(['join_status'=>5]);
            if($res !== false){
                $data['order_type'] = 1;
                $r = M('Order')->where(['order_id'=>$order_id])->update($data);
                // 提交事务
                Db::commit(); 
                $this->ajaxReturn(['status' => 1 , 'msg'=>'操作成功','data'=>$pwdstr]);
            }else{
                // 回滚事务
                Db::rollback();
                $this->ajaxReturn(['status' => -1 , 'msg'=>'操作失败','data'=>$pwdstr]);    
            }
        }else
            $r = M('Order')->where(['order_id'=>$order_id])->whereor(['parent_id'=>$order_id])->update($data);

        if(false !== $r){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'操作成功','data'=>$pwdstr]);
        }else{
            $this->ajaxReturn(['status' => -1 , 'msg'=>'操作失败','data'=>$pwdstr]);    
        }
    }

    //领取礼物/参与抽奖-----未用
    public function order_join()
    {
        $user_id = $this->get_user_id();
        $order_id = input('order_id',0);
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        if(!$order_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'群抢id不能为空','data'=>'']);
        }
        //获取订单详情
        $info = Db::name('order')->field('order_id,order_type,lottery_time,overdue_time')->where('order_id',$order_id)->find();
        $result['order_type'] = $info['order_type'];
        if($info['order_type'] == 1){
            //判断有没有人领取过
            $gift = Db::name('gift_order_join')->where(['order_id'=>$order])->find();
            if($gift['user_id'] == $user_id){
                $this->ajaxReturn(['status' => -1 , 'msg'=>'您已经领取过该礼物','data'=>$result['order_type']]);
            }
            if($gift){
                $data['order_type'] = 3;
                $data['parentid'] = $gift['user_id'];
            }else{
                $data['order_type'] = 1;
            }
            $data['status'] = 1;
        }else if($info['order_type'] == 2){
            if(time() > $info['lottery_time']){
                $this->ajaxReturn(['status' => -1 , 'msg'=>'活动已终止','data'=>$result['order_type']]);
            }
            $count = Db::name('gift_order_join')->where(['user_id'=>$user_id,'order_id'=>$order_id])->count();
            if($count){
                $this->ajaxReturn(['status' => -1 , 'msg'=>'您已经参与过该次抽奖了','data'=>$result['order_type']]);
            }
            $data['order_type'] = 2;
            $data['status'] = 0;
            $data['parentid'] = 0;
        }else{
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单不能进行参与操作','data'=>$result['order_type']]);
        }
        $data['order_id'] = $order_id;
        $data['addtime'] = time();
        $data['user_id'] = $user_id;
        $res = Db::name('gift_order_join')->insert($data);
        if($res){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'参与抽奖成功','data'=>$result['order_type']]);
        }else{
            $this->ajaxReturn(['status' => -1 , 'msg'=>'参与抽奖失败','data'=>$result['order_type']]);
        }
    }

    //获取中奖名单
    public function get_gift_order()
    {
        $order_id = input('order_id',0);
        $status = input('status',0); //获取全部参与者，1获取中奖名单（已转动转盘的）
        $where = ['order_id'=>$order_id,'parentid'=>0];
        if($status == 1){
            $where['status']  = 1;
            // $where['join_status']  = ['notin',[0,4]];
        }
        $user_list = Db::name('gift_order_join')->where($where)->column('user_id');
        if(!$user_list){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'暂未有人中奖','data'=>'无']);
        }
        //获取昵称
        $result = Db::name('member')->where('id','in',$user_list)->column('nickname');
        //$result = implode('、',$result);
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$result]);
    }

    //转盘-中奖情况
    public function smoke_gift()
    {
        $user_id = $this->get_user_id();
        $order_id = input('order_id',0);
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        if(!$order_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'订单id不能为空','data'=>'']);
        }

        $info = Db::name('gift_order_join')->where(['order_id'=>$order_id,'order_type'=>2,'user_id'=>$user_id])->find();
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>['status'=>$info]]);
    }

    //转动转盘
    public function turn_the_wheel(){
        $user_id = $this->get_user_id();
        $order_id = input('order_id',0);
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        if(!$order_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'订单id不能为空','data'=>'']);
        }

        $info = Db::name('gift_order_join')->field('id,status,join_status')->where(['order_id'=>$order_id,'order_type'=>2,'user_id'=>$user_id])->find();
        if(!$info)
            $this->ajaxReturn(['status' => -1 , 'msg'=>'您没有参与此次群抢','data'=>'']);
        elseif($info['join_status'] == 4)
            $this->ajaxReturn(['status' => -1 , 'msg'=>'此次参与已取消','data'=>'']);
        elseif($info['join_status'] != 0)
            $this->ajaxReturn(['status' => -1 , 'msg'=>'您已经参与过此次抽奖','data'=>'']);

        $order = Db::name('order')->field('order_status,shipping_status,pay_status,parent_id,order_type,lottery_time,giving_time,overdue_time,gift_uid')->where(['order_id'=>$order_id])->find();
        if(!$order['lottery_time']){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单已不能抽奖！','data'=>'']);
        }elseif(!$order['lottery_time'] < time())
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单已不能抽奖！','data'=>'']);
        elseif(!$order['overdue_time'] < time())
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该订单已不能抽奖！','data'=>'']);            

        //有人转动转盘，则给此次群抢全部设置开奖用户
        $giftorderid = M('Order')->where(['parent_id'=>$order_id,'gift_uid'=>0])->column('order_id'); //需开奖的订单
        $res = 0;
        if($giftorderid){
            $joinuserid = Db::name('gift_order_join')->where(['order_id'=>$order_id,'order_type'=>2,'status'=>0,'join_status'=>0])->column('user_id'); //参与人数
            if($joinuserid){
                $joinuserid = shuffle($joinuserid);
                Db::startTrans();
                foreach($giftorderid as $k=>$v){
                    if(!$joinuserid['$k'])continue;
                                        
                    M('Order')->where(['order_id'=>$v])->update(['lottery_time'=>0,'giving_time'=>0,'overdue_time'=>0,'gift_uid'=>$joinuserid['$k']]);    
                    Db::name('gift_order_join')->where(['order_id'=>$order_id,'order_type'=>2,'user_id'=>$joinuserid['$k'],'join_status'=>0])->update(['status'=>1]);
                }
                //其他人设置成未中奖
                $r = Db::name('gift_order_join')->where(['order_id'=>$order_id,'order_type'=>2,'status'=>0])->update(['status'=>2]);
                if(false !== $r){
                    $res = Db::name('gift_order_join')->where(['id'=>$info['id']])->update(['join_status'=>6]);
                    Db::commit(); 
                }else{
                    Db::rollback();
                    $this->ajaxReturn(['status' => -1 , 'msg'=>'请求失败','data'=>$info]);
                }
            }
        }
        //再次查看是否中奖
        $info = Db::name('gift_order_join')->field('id,status,join_status')->where(['order_id'=>$order_id,'order_type'=>2,'user_id'=>$user_id])->find();
        if($res){
            $info['join_status'] = 0;
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'请求成功','data'=>$info]);
        
    }

    /**
     *  已送礼物
     *  type 1 已领取 2未领取
     */
    public function get_send_gift()
    {
        $user_id = $this->get_user_id();
        $type = input('type',2);
        $page = input('page',1);
        $num = input('num',100);
        if($type == 1){
            $where['o.pay_status'] = 1;
            $where['goj.join_status'] = 1;
            $where['goj.status'] = 1;
            $where['o.user_id'] = $user_id;
            $order = Db::name('order')->alias('o')
                ->join('order_goods og','og.order_id=o.order_id','LEFT')
                ->join('gift_order_join goj','goj.order_id=o.order_id','LEFT')
                ->join('member m','goj.user_id=m.id','LEFT')
                ->field('o.order_id,o.order_sn,o.add_time,og.goods_name,og.spec_key_name,og.goods_price,og.goods_num,o.order_amount,m.nickname,og.goods_id')
                ->where($where)
                ->page($page,$num)
                ->select();
            foreach($order as $key=>$val){
                $order[$key]['nickname'] = $val['nickname']?$val['nickname']:'';
            }
        }else{
            $where['o.pay_status'] = 1;
            // $where['o.parent_id'] = 0;
            $where['o.order_type'] = ['neq',0];
            $where['o.order_status']=['in',[0,1]];
            $order = Db::name('order')->alias('o')
                ->join('order_goods og','og.order_id=o.order_id','LEFT')
                ->field('o.order_id,o.order_sn,o.add_time,og.goods_name,og.spec_key_name,og.goods_price,og.goods_num,o.order_amount,og.goods_id,o.parent_id')
                ->where($where)
                ->page($page,$num)
                ->select();
            $parent_id = array();
            foreach($order as $key=>$val){
                if(!in_array($val['parent_id'],$parent_id)){
                    $parent_id[] = $val['parent_id'];
                }
                //子订单商品总价给他显示单价
                if($val['parent_id']){
                    $order[$key]['order_amount'] = $val['goods_price'];
                }
            }
            foreach($order as $key=>$val){
                if(in_array($val['order_id'],$parent_id)){
                    unset($order[$key]);
                }
            }
            if($order){
                sort($order);
            }
        }
        foreach($order as $key=>$val){
            $order[$key]['img'] = Db::name('goods_img')->where(['goods_id'=>$val['goods_id'],'main'=>1])->value('picture');
            $order[$key]['img'] = $order[$key]['img']?SITE_URL.$order[$key]['img']:'';
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'请求成功','data'=>$order]);
    }

    //获取某个商品获取运费
    public function get_shipping($goods_id=0,$num=1)
    {
        $shipping_price = '0'; //订单运费
        $goods_res = Db::table('goods')->field('shipping_setting,shipping_price,delivery_id')->where('goods_id',$goods_id)->find();
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
                    $number = $num - $delivery['firstweight'];
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
        return $shipping_price;
    }

}