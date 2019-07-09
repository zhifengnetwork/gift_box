<?php
namespace app\admin\controller;

use think\Db;
use think\Request;

/**
 * 电子礼盒
 */
class Turntable extends Common
{
    //主页
    public function index()
    {
        $list = array();
        return $this->fetch();
    }
    
    // 俏皮话列表
    public function joke_list()
    {
        $list = Db::table('turntable_joke')->paginate(10);
        $this->assign('list',$list);
        return $this->fetch();
    }
}
