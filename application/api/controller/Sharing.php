<?php
/**
 * 享物圈
 */
namespace app\api\controller;
use think\Request;
use think\Db;

class Sharing extends ApiBase
{
    //添加分享
    public function add_Sharing()
    {
        $id = input('id');
        $user_id = $this->get_user_id();
        if($id && $user_id){
            $count = Db::name('sharing_circle')->where(['id'=>$id,'user_id'=>$user_id])->count();
            if(!$count){
                $this->ajaxReturn(['status' => -1 , 'msg'=>'该条分享不是该用户的','data'=>'']);
            }
        }
        $data['title'] = input('title');
        $data['content'] = input('content');
        $data['user_id'] = $user_id;
        $data['lat'] = input('lat');
        $data['lon'] = input('lon');
        $data['priture'] = input('priture');
        $data['topic_id'] = input('topic_id');
        // $data['topic_id'] = input('topic_id');
        $data['topic_id'] = str_replace(SITE_URL,'',$data['topic_id']);
        $data['addtime'] = time();
        if($id){
            $res = Db::name('sharing_circle')->where('id',$id)->update($data);
        }else{
            $res = Db::name('sharing_circle')->insert($data);
        }
        if($res){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>'']);
        }else{
            $this->ajaxReturn(['status' => -1 , 'msg'=>'失败','data'=>'']);
        }
    }
    
    //话题圈
    public function get_sharing_topic()
    {
        $list = Db::name('sharing_topic')->where('status',0)->order('sort,addtime desc')->field('id,name')->select();
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //图片上传
    public function upload_imgs()
    {
        $user_id = $this->get_user_id();
        $resule = $this->UploadFile('images','sharing');
        $this->ajaxReturn($resule);
    }

    //分享列表
    public function Sharing_list()
    {
        $page = input('page',1);
        $num = input('num',10);
        $topic_id = input('topic_id',0);//0推荐 -1附近
        if($topic_id == '-1'){
            $where['sc.is_rec'] = 1;//待完善
        }else if($topic_id){
            $where['sc.topic_id'] = $topic_id;
        }else{
            $where['sc.is_rec'] = 1;
        }
        $list = Db::name('sharing_circle')->alias('sc')->join('member m','m.id=sc.user_id','LEFT')->field('m.nickname,sc.id,sc.cover,sc.title,sc.point_num,m.avatar')->where($where)->page($page,$num)->select();
        foreach($list as $key=>$val){
            $list[$key]['avatar'] = substr($val['avatar'],0,1) != 'h'?SITE_URL.$val['avatar']:$val['avatar'];
            $list[$key]['cover'] = $val['cover']?SITE_URL.$val['cover']:'';
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

}
