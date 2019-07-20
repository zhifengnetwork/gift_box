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

}
