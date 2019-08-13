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
        }else{
            $where['status'] = array('in','0,1,2');
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
        $list =  Db::name('sharing_label')->order('addtime desc')->paginate(10);
        $this->assign('list',$list);
        return $this->fetch();
    }

    //添加修改标签
    public function add_label()
    {
        $id = input('id',0);
        if(Request::instance()->isPost()){
            $post = input('post.');
            if($id){
                Db::name('sharing_label')->where('id',$id)->update($post);
            }else{
                $post['addtime'] = time();
                Db::name('sharing_label')->insert($post);
            }
            $this->success('操作成功','label_list');
        }
        if($id){
            $info = Db::name('sharing_label')->where('id',$id)->find();
        }else{
            $info = getTableField('sharing_label');
        }
        $this->assign('info',$info);
        return $this->fetch();
    }
    
    //删除标签
    public function del_label()
    {
        $id = input('id');
        Db::name('sharing_label')->where('id',$id)->delete();
        return json(['status'=>1,'msg'=>'删除成功']);
    }

    //文章列表
    public function article_list()
    {
        $list = Db::name('sharing_article')->order('sort,addtime desc')->paginate(10);
        $this->assign('list',$list);
        return $this->fetch();
    }

    //添加文章
    public function add_article()
    {
        $id = input('id',0);
        if(Request::instance()->isPost()){
            $post = input('post.');
            $post['cover'] = implode(',',$post['cover']);
            if($id){
                Db::name('sharing_article')->where('id',$id)->update($post);
            }else{
                $post['addtime'] = time();
                Db::name('sharing_article')->insert($post);
            }
            $this->success('操作成功','article_list');
        }
        if($id){
            $info = Db::name('sharing_article')->where('id',$id)->find();
            $info['cover'] = explode(',',$info['cover']);
        }else{
            $info = getTableField('sharing_article');
        }
        $this->assign('info',$info);
        return $this->fetch();
    }

    //删除文章
    public function del_article()
    {
        $id = input('id');
        Db::name('sharing_article')->where('id',$id)->delete();
        return json(['status'=>1,'msg'=>'删除成功']);
    }

    //文件上传
    public function UploadFile()
    {
    	// 获取表单上传文件 例如上传了001.jpg
        $files = request()->file('image');
        // 移动到框架应用根目录/public/uploads/ 目录下
        foreach($files as $file){
            // 移动到框架应用根目录/public/uploads/ 目录下
            $info = $file->validate(['size'=>1024*1024*10])->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . 'sharing' . DS);
            
            if($info){
                // 成功上传后 获取上传信息
                $data[] = '/public/uploads/'.'sharing'.'/'.$info->getSaveName();
            }else{
                // 上传失败获取错误信息
                return ['msg'=>$file->getError(),'status'=>-1,'data'=>''];
            }    
        }
        return ['msg'=>'上传成功','status'=>1,'data'=>$data];          
    }  

    //配乐列表
    public function music_list()
    {
        $list  = Db::name('sharing_music')->where('pid',0)->order('sort,addtime desc')->paginate(5)->each(function($v,$k){
            $v['list'] =  Db::name('sharing_music')->where('pid',$v['id'])->select();
            return $v;
        });
        $this->assign('list',$list);
        return $this->fetch();
    }

    //添加配乐
    public function add_music()
    {
        $id = input('id');
        if(Request::instance()->isPost()){
            $sort = input('sort');
            $name = input('name');
            $status = input('status');
            $url = input('url');
            $pid = input('pid');
            if(!$name){
                $this->error('请填写名称');
            }
            $data['sort'] = $sort;
            $data['name'] = $name;
            $data['status'] = $status;
            $data['url'] = $url;
            $data['pid'] = $pid;
            $data['desc'] = input('desc','');
            if($id){
                if($pid){
                    $count = Db::name('sharing_music')->where('pid',$id)->count();
                    if($count){
                        $this->error('该话题拥有下级，请先删除下级在规划到别的话题');
                    }
                }
                Db::name('sharing_music')->where('id',$id)->update($data);
            }else{
                $data['addtime'] = time();
                Db::name('sharing_music')->insert($data);
            }
            $this->success('操作成功','music_list');
        }
        $music_list  = Db::name('sharing_music')->where('pid',0)->select();
        if($id){
            $info = Db::name('sharing_music')->where('id',$id)->find();
        }else{
            $info = getTableField('sharing_music');
        }
        $pid = input('pid',0);
        $this->assign('info',$info);
        $this->assign('pid',$pid);
        $this->assign('music_list',$music_list);
        return $this->fetch();
    }

    //删除音乐
    public function del_music()
    {
        $id = input('id');
        Db::name('sharing_music')->where('id',$id)->delete();
        return json(['status'=>1,'msg'=>'删除成功']);
    }
}
