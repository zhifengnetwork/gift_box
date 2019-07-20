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
        $user_id = $this->get_user_id();
    }

}
