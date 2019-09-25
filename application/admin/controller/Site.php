<?php
namespace app\admin\controller;

use think\Db;
use think\Config;

class Site extends Common
{   
    public function _initialize()
    {   
        parent::_initialize();
        $this->info = Db::table('site')->find();
    }

    public function index()
    {

        if( request()->isPost() ){
            $data = input('post.');

            if( isset($data['logo']) ) $data['logo'] = $this->base_img($data['logo'],'site','logo',$this->info['logo']);

            if( isset($data['logo_mobile']) ) $data['logo_mobile'] = $this->base_img($data['logo_mobile'],'site','logo_mobile',$this->info['logo_mobile']);

            $data['shop_contact'] = serialize($data['shop_contact']);
            
            $data['noticeset'] = serialize($data['noticeset']);

            
            if($data['id']){
                Db::table('site')->update($data,$data['id']);
            }else{
                Db::table('site')->insert($data);
            }
            $this->success('修改成功!');
        }
        if( $this->info['shop_contact'] ) $this->info['shop_contact'] = unserialize( $this->info['shop_contact'] );

        if( $this->info['noticeset'] ) $this->info['noticeset'] = unserialize( $this->info['noticeset'] );

        return $this->fetch('',[
            'meta_title'    =>  '网站设置',
            'info'  =>  $this->info,
        ]);
    }

    //常见问题
    public function problem_list()
    {
        $list =  Db::table('problem')->alias('p')->join('problem_cate c','c.id=p.cate_id','LEFT')->field('p.id,p.title,p.sort,p.addtime,c.name')->order('c.sort desc')->paginate(10);
        $this->assign('list',$list);
        return $this->fetch();
    }

    //添加问题
    public function add_problem()
    {
        $id = input('id');
        $cate = Db::table('problem_cate')->order('sort desc')->select();
        if($id){
            $info = Db::table('problem')->where('id',$id)->find();
        }else{
            $info = getTableField('problem');
        }
        $this->assign('info',$info);
        $this->assign('cate',$cate);
        return $this->fetch();
    }

    //添加问题提交
    public function add_problem_post()
    {
        $post = input('post.');
        if(!$post['cate_id']){
            $this->error('请选择分类');
        }
        if(!$post['title']){
            $this->error('请填写标题');
        }
        if(!$post['content']){
            $this->error('请填写内容');
        }
        if($post['id']){
            Db::table('problem')->where('id',$post['id'])->update($post);
        }else{
            $post['addtime'] = time();
            Db::table('problem')->insert($post);
        }
        $this->success('操作成功',url('problem_list'));
    }

    // 删除问题
    public function del_problem()
    {
        $id = input('id');
        if(!$id){
            $result['status'] = 2;
            $result['msg'] = '分类id错误';
            return json($result);
        }
        Db::table('problem')->where('id',$id)->delete();
        $result['status'] = 1;
        $result['msg'] = '删除成功';
        return json($result);
    }

    // 删除分类
    public function del_problem_cate()
    {
        $id = input('id');
        if(!$id){
            $result['status'] = 2;
            $result['msg'] = '分类id错误';
            return json($result);
        }
        Db::table('problem_cate')->where('id',$id)->delete();
        $result['status'] = 1;
        $result['msg'] = '删除成功';
        return json($result);
    }

    //常见问题分类
    public function problem_cate_list()
    {
        $list =  Db::table('problem_cate')->order('sort desc')->paginate(10);
        $this->assign('list',$list);
        return $this->fetch();
    }

    //添加分类
    public function add_problem_cate()
    {
        $id = input('id');
        if($id){
            $info = Db::table('problem_cate')->where('id',$id)->find();
        }else{
            $info = getTableField('problem_cate');
        }
        $this->assign('info',$info);
        return $this->fetch();
    }

    //分类提交
    public function problem_cate_post()
    {
        $post = input('post.');
        if(!$post['name']){
            $this->error('请输入分类名称');
        }
        if($post['id']){
            Db::table('problem_cate')->where('id',$post['id'])->update($post);
        }else{
            $post['addtime'] = time();
            Db::table('problem_cate')->insert($post);
        }
        $this->success('操作成功',url('problem_cate_list'));
    }

}
