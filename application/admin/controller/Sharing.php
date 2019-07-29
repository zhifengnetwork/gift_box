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
        $list  = Db::name('sharing_circle')->order('addtime desc')->paginate(10);
        $this->assign('list',$list);
        return $this->fetch();
    }

    //添加话题
    public function add_topic()
    {
        $id = input('id');
        if($id){
            $info = Db::name('sharing_topic')->where('id',$id)->find();
        }else{
            $info = getTableField('sharing_topic');
        }
        $this->assign('info',$info);
        return $this->fetch();
    }
    
    //话题列表
    public function topic_list()
    {
        $list  = Db::name('sharing_topic')->order('addtime desc')->paginate(10);
        $this->assign('list',$list);
        return $this->fetch();
    }
}
