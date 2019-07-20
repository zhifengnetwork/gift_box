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
        $user_id = 88;
        $shop_card_balance = Db::name('member')->where('id',$user_id)->value('shop_card_balance');
        $user_id =  str_pad($user_id,5,"0",STR_PAD_LEFT);
        dump($user_id);
    }

}
