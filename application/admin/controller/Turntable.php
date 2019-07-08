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
       exit();
    }
    
    // 俏皮话列表
    public function joke_list()
    {
        return $this->fetch();
    }
}
