<?php
namespace app\admin\controller;

use think\Db;
use think\Request;

/**
 * 享物圈
 */
class Sharing extends Common
{
    /**
     * 享物圈列表
     */
    public function index()
    {
        $list  = Db::name('sharing_circle')->paginate(10);
        $this->assign('list',$list);
        return $this->fetch();
    }

}
