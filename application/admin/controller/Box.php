<?php
namespace app\admin\controller;

use think\Db;
use think\Request;

/**
 * 电子礼盒
 */
class Box extends Common
{
    /**
     * 礼盒列表
     */
    public function index()
    {
        $list = Db::table('box')->field('id,cate_id,user_id,sender_id,music_id,addtime')->order('id desc')->paginate(10);
        $this->assign('list',$list);
        return $this->fetch();
    }

}
