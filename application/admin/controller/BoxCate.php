<?php
namespace app\admin\controller;

use think\Db;
use think\Request;
use app\common\model\Advertisement as Advertise;

/**
 * 电子礼盒类别
 */
class BoxCate extends Common
{
    /**
     * 礼盒列表列表
     */
    public function index()
    {
        $list = Db::table('box_cate')->where('pid',0)->order('sort asc')->paginate(10);;
        foreach($list as $key=>$val){
            $list[$key]['list'] = Db::table('box_cate')->where('pid',$val['id'])->order('sort')->select();
        }
        $this->assign('list',$list);
        return $this->fetch();
    }

    //添加
    public function add()
    {
        $pid = input('pid',0);
        $cate_list = Db::table('box_cate')->field('id,name')->where('pid',0)->select();
        $this->assign('pid',$pid);
        $this->assign('cate_list',$cate_list);
        return $this->fetch();
    }

    //提交
    public function cate_post()
    {
        //判断
        if(Request::instance()->isPost()){
            $post = input('post.');
            // 图片验证
            $res = Advertise::pictureUpload('box_cate', 0);
            if ($res[0] == 1) {
                $this->error($res[0]);
            } else {
                $pictureName                             = $res[1];
                !empty($pictureName) && $post['picture'] = '/public'.$pictureName;
            }
            unset($post['file']);
            $id = input('post.id');
            if($id){
                Db::table('box_cate')->where('id',$id)->update($post);
            }else{
                $post['addtime'] = time();
                Db::table('box_cate')->insert($post);
            }
            $this->success('操作成功',url('index'));
        }
    }

    // 删除
    public function del()
    {
        $id = input('id');
        $count = Db::table('box_cate')->where('pid',$id)->count();
        if($count){
            return json(['status'=>0,'msg'=>'该分类有下级，请先删除其下级']);
        }
        Db::table('box_cate')->where('id',$id)->delete();
        return json(['status'=>1,'msg'=>'删除成功']);
    }

    //修改
    public function edit()
    {
        $id = input('id');
        $info = Db::table('box_cate')->where('id',$id)->find();
        $cate_list = Db::table('box_cate')->field('id,name')->where('pid',0)->select();
        $this->assign('cate_list',$cate_list);
        $this->assign('info',$info);
        return $this->fetch();
    }

    //信封列表
    public function envelope_list()
    {
        $list =  Db::name('box_envelope')->paginate(10);
        $this->assign('list',$list);
        return $this->fetch();
    }

    //添加信封
    public function add_envelope()
    {
        $id = input('id');
        if($id){
            $info = Db::name('box_envelope')->where('id',$id)->find();
        }else{
            $info = getTableField('box_envelope');
        }
        return $this->fetch();
    }

    //提交
    public function envelope_post()
    {
        //判断
        if(Request::instance()->isPost()){
            $post = input('post.');
            if(!$post['name']){
                $this->error('请填写名称');
            }
            // 图片验证,路径
            $res = Advertise::pictureUpload('box_cate', 0);
            if ($res[0] == 1) {
                $this->error($res[0]);
            } else {
                $pictureName                             = $res[1];
                !empty($pictureName) && $post['picture'] = '/public'.$pictureName;
            }
            unset($post['file']);
            $id = input('post.id');
            if($id){
                Db::table('box_envelope')->where('id',$id)->update($post);
            }else{
                Db::table('box_envelope')->insert($post);
            }
            $this->success('操作成功',url('envelope_list'));
        }
    }

    // 删除
    public function del_envelope()
    {
        $id = input('id');
        Db::table('box_envelope')->where('id',$id)->delete();
        return json(['status'=>1,'msg'=>'删除成功']);
    }

}
