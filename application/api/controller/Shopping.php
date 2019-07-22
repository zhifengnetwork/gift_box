<?php
/**
 * 购物车API
 */
namespace app\api\controller;
use think\Request;
use think\Db;

class Shopping extends ApiBase
{
    //获取用户余额和购物卡号
    public function get_card_info()
    {
        $user_id =  $this->get_user_id();
        $shop_card_balance = Db::name('member')->where('id',$user_id)->value('shop_card_balance');
        $user_id =  'NO.'.str_pad($user_id,6,"0",STR_PAD_LEFT);
        $data['user_no'] = $user_id;
        $data['shop_card_balance'] = $shop_card_balance;
        $this->ajaxReturn(['status' => 1 , 'msg'=>'请求成功！','data'=>$data]);
    }

    //获取交易明细
    public function get_order_list()
    {
        $page = input('page',1);
        $num = input('num',10);
        $user_id =  86;
        $list = Db::name('member_order')->field('id,order_sn,shop_name,desc,addtime,money')->where('user_id',$user_id)->where('status',1)->page($page,$num)->order('addtime desc')->select();
        foreach($list as $key=>$val){
            $list[$key]['addtime'] = date('Y-m-d H:i:s',$val['addtime']);
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'请求成功！','data'=>$list]);
    }

}
