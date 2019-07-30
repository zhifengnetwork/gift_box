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
        $data['type'] = input('type');
        $data['content'] = input('content');
        $data['user_id'] = $user_id;
        $data['lat'] = input('lat');
        $data['lon'] = input('lon');
        $data['priture'] = input('priture');
        $data['topic_id'] = input('topic_id');
        $data['topic_id'] = str_replace(SITE_URL,'',$data['topic_id']);
        $data['cover'] = input('cover');
        $data['cover'] = str_replace(SITE_URL,'',$data['cover']);
        if(!$data['cover'] && $data['type'] == 0){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'请上传图片','data'=>'']);
        }
        if(!$data['cover']){
            $cover = explode(',',$data['priture']);
            $data['cover'] = $cover[0];
        }

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
        $where['sc.status'] = 1;
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

    //分享详情
    public function Sharing_info()
    {
        $id = input('id',0);
        $info = Db::name('sharing_circle')->field('lat,lon,status',true)->where('id',$id)->find();
        if(!$info){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'享物圈不存在','data'=>'']);
        }
        $user = Db::name('member')->field('avatar,nickname')->where('id',$info['user_id'])->find();
        $info['avatar'] = substr($user['avatar'],0,1) != 'h'?SITE_URL.$user['avatar']:$user['avatar'];
        $info['nickname'] = $user['nickname'];
        $info['addtime'] = date('Y-m-d H:i:s',time());
        $info['priture'] = explode(',',$info['priture']);
        $info['cover'] = $info['cover']?SITE_URL.$info['cover']:'';
        foreach($info['priture'] as $key=>$val){
            $info['priture'][$key] = SITE_URL.$val;
        }
        $info['comment'] = Db::name('sharing_comment')->where('sharing_id',$id)->order('addtime desc')->limit(3)->select();
        foreach($info['comment'] as $key=>$val){
            $info['comment'][$key]['nickname'] = Db::name('member')->where('id',$val['user_id'])->value('nickname');
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$info]);
    }

    //评论列表
    public function comment_list()
    {
        $sharing_id = input('sharing_id',0);
        $page = input('page',1);
        $num = input('num',10);
        $list = Db::name('sharing_comment')->where('sharing_id',$sharing_id)->order('addtime desc')->page($page,$num)->select();
        foreach($list as $key=>$val){
            $list[$key]['nickname'] = Db::name('member')->where('id',$val['user_id'])->value('nickname');
            $list[$key]['addtime'] = date('Y-m-d H:i:s',$val['addtime']);
            $list[$key]['avatar'] = Db::name('member')->where('id',$val['user_id'])->value('avatar');
            $list[$key]['avatar']=substr($list[$key]['avatar'],0,1) != 'h'?SITE_URL.$list[$key]['avatar']:$list[$key]['avatar'];
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //评论
    public function add_comment()
    {
        $user_id =  $this->get_user_id();
        $sharing_id = input('sharing_id');
        $content = input('content');
        if(!$sharing_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'请提供享物圈id','data'=>'']);
        }
        if(!$content){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'请输入评论内容','data'=>'']);
        }
        $data['user_id'] = $user_id;
        $data['sharing_id'] = $sharing_id;
        $data['content'] = $content;
        $data['addtime'] = time();
        $res = Db::name('sharing_comment')->insert($data);
        if($res){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'评论成功','data'=>'']);
        }else{
            $this->ajaxReturn(['status' => -1 , 'msg'=>'评论失败','data'=>'']);
        }
    }

    //个人中心-评论列表
    public function my_comment_list()
    {
        $user_id =  $this->get_user_id();
        $page = input('page',1);
        $num = input('num',10);
        $content_num = input('content_num',20);
        $list = Db::name('sharing_circle')->alias('s')->join('sharing_comment sc','sc.sharing_id=s.id')->where('s.user_id',$user_id)->join('member m','m.id=s.user_id')->field('sc.id,sc.sharing_id,sc.content,sc.addtime,m.nickname,m.avatar,s.title,s.cover,s.content')->page($page,$num)->select();
        foreach($list as $key=>$val){
            $list[$key]['avatar'] = substr($list[$key]['avatar'],0,1) != 'h'?SITE_URL.$list[$key]['avatar']:$list[$key]['avatar'];
            $list[$key]['addtime'] = date('Y-m-d H:i:s',$val['addtime']);
            $list[$key]['cover'] = $val['cover']?SITE_URL.$val['cover']:'';
            $list[$key]['content'] = mb_substr($val['content'],0,$content_num,'UTF8');
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

}
