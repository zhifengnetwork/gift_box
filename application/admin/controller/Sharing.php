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
        $status = ['未审核','审核通过','审核不通过','草稿箱','已下架'];
        $where['status'] = array('neq',4);
        $where['is_del'] = array('neq',1);
        $status_ed = input('status_ed','');
        $keyword = input('keyword','');
        $pageParam = array();
        if($status_ed !== ''){
            $where['status'] = $status_ed;
            $pageParam['query']['status'] = $status_ed;
        }
        if($keyword){
            $where['title'] = array('like','%'.$keyword.'%');
            $pageParam['query']['keyword'] = $keyword;
        }
        $list  = Db::name('sharing_circle')->where($where)->order('sort,addtime desc')->paginate(10,false,$pageParam)->each(function($v,$k) use($status){
            $v['nickname'] = Db::name('member')->where('id',$v['user_id'])->value('nickname');
            if(mb_strlen( $v['title'],'UTF8') > 10){
                $v['title'] = mb_substr($v['title'],0,10).'...';
            }
            $v['status_name'] = $status[$v['status']];
            return $v;
        });
        $this->assign('list',$list);
        $this->assign('status_ed',$status_ed);
        $this->assign('keyword',$keyword);
        return $this->fetch();
    }

    //审核
    public function edit()
    {
        $id = input('id',0);
        if(Request::instance()->isPost()){
            $sort = input('sort');
            $status = input('status');
            Db::name('sharing_circle')->where('id',$id)->update(['sort'=>$sort,'status'=>$status]);
            $this->success('审核成功','index');
        }
        $info = Db::name('sharing_circle')->where('id',$id)->find();
        $info['priture'] = explode(',',$info['priture']);
        $info['topic_name'] = Db::name('sharing_topic')->where('id',$info['topic_id'])->value('name');
        $info['nickname'] = Db::name('member')->where('id',$info['user_id'])->value('nickname');
        $this->assign('info',$info);
        return $this->fetch();
    }
 
    //添加话题
    public function add_topic()
    {
        $id = input('id');
        if(Request::instance()->isPost()){
            $sort = input('sort');
            $name = input('name');
            $status = input('status');
            $img = input('img');
            $pid = input('pid');
            if(!$name){
                $this->error('请填写话题名称');
            }
            $count = Db::name('sharing_topic')->where(['name'=>$name,'id'=>array('neq',$id)])->count();
            if($count){
                $this->error('话题名称已存在');
            }
            $data['sort'] = $sort;
            $data['name'] = $name;
            $data['status'] = $status;
            $data['img'] = $img;
            $data['pid'] = $pid;
            if($id){
                if($pid){
                    $count = Db::name('sharing_topic')->where('pid',$id)->count();
                    if($count){
                        $this->error('该话题拥有下级，请先删除下级在规划到别的话题');
                    }
                }
                Db::name('sharing_topic')->where('id',$id)->update($data);
            }else{
                $data['addtime'] = time();
                Db::name('sharing_topic')->insert($data);
            }
            $this->success('操作成功','topic_list');
        }
        $topic_list  = Db::name('sharing_topic')->where('pid',0)->select();
        if($id){
            $info = Db::name('sharing_topic')->where('id',$id)->find();
        }else{
            $info = getTableField('sharing_topic');
        }
        $pid = input('pid',0);
        $this->assign('info',$info);
        $this->assign('pid',$pid);
        $this->assign('topic_list',$topic_list);
        return $this->fetch();
    }
    
    //话题列表 
    public function topic_list()
    {
        $list  = Db::name('sharing_topic')->where('pid',0)->order('sort,addtime desc')->paginate(5)->each(function($v,$k){
            $v['list'] =  Db::name('sharing_topic')->where('pid',$v['id'])->select();
            return $v;
        });
        $this->assign('list',$list);
        return $this->fetch();
    }

    //删除话题
    public function del_topic()
    {
        $id = input('id');
        Db::table('sharing_topic')->where('id',$id)->delete();
        return json(['status'=>1,'msg'=>'删除成功']);
    }

    //删除享物圈
    public function del()
    {
        $id = input('id');
        Db::table('sharing_circle')->where('id',$id)->update(['is_del'=>1]);
        return json(['status'=>1,'msg'=>'删除成功']);
    }

    //标签列表
    public function label_list()
    {
        
    }
    
}
