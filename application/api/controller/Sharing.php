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
        $status = input('status',0);
        if($status){
            $data['status'] = 3;
        }else{
            $data['status'] = 0;
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
        $type = input('type',0);
        $result[] = ['id'=>'0','name'=>'推荐'];
        $result[] = ['id'=>'-1','name'=>'附近'];
        $list = Db::name('sharing_topic')->where('status',0)->order('sort,addtime desc')->field('id,name')->select();
        foreach($list as $val){
            $result[] = $val;
        }
        if($type){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$result]);
    }

    //图片上传
    public function upload_imgs()
    {
        $user_id = $this->get_user_id();
        $resule = $this->UploadFile('images','sharing');
        $this->ajaxReturn($resule);
    }

    //分享列表
    public function sharing_list()
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
            $list[$key]['show'] = false;
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //分享详情
    public function sharing_info()
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

    //添加评论
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
            Db::name('sharing_circle')->where('id',$sharing_id)->setInc('comment_num',1);
            $this->ajaxReturn(['status' => 1 , 'msg'=>'评论成功','data'=>'']);
        }else{
            $this->ajaxReturn(['status' => -1 , 'msg'=>'评论失败','data'=>'']);
        }
    }

    //点赞
    public function add_point()
    {
        $user_id =  $this->get_user_id();
        $sharing_id = input('sharing_id');
        $data['user_id'] = $user_id;
        $data['sharing_id'] = $sharing_id;
        if(!$sharing_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'请提供享物圈id','data'=>'']);
        }
        $count = Db::name('sharing_point')->where($data)->count();
        if($count){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'您已经点过赞了','data'=>'']);
        }
        $data['addtime'] = time();
        $res =  Db::name('sharing_point')->insert($data);
        if($res){
            Db::name('sharing_circle')->where('id',$sharing_id)->setInc('point_num',1);
            $this->ajaxReturn(['status' => 1 , 'msg'=>'点赞成功','data'=>'']);
        }else{
            $this->ajaxReturn(['status' => -1 , 'msg'=>'点赞失败','data'=>'']);
        }
    }

    //收藏
    public function add_collection()
    {
        $user_id =  $this->get_user_id();
        $sharing_id = input('sharing_id');
        $data['user_id'] = $user_id;
        $data['sharing_id'] = $sharing_id;
        if(!$sharing_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'请提供享物圈id','data'=>'']);
        }
        $id = Db::name('sharing_collection')->where($data)->value('id');
        if($id){
            Db::name('sharing_circle')->where('id',$sharing_id)->setDec('collection_num',1);
            $res = Db::name('sharing_collection')->where('id',$id)->delete();
            if($res){
                $this->ajaxReturn(['status' => 1 , 'msg'=>'取消收藏','data'=>'']);
            }
            // $this->ajaxReturn(['status' => -2 , 'msg'=>'您已经收藏了','data'=>'']);
        }
        $data['addtime'] = time();
        $res =  Db::name('sharing_collection')->insert($data);
        if($res){
            Db::name('sharing_circle')->where('id',$sharing_id)->setInc('collection_num',1);
            $this->ajaxReturn(['status' => 1 , 'msg'=>'收藏成功','data'=>'']);
        }else{
            $this->ajaxReturn(['status' => -1 , 'msg'=>'收藏失败','data'=>'']);
        }
    }

    //个人中心-评论列表
    public function user_comment_list()
    {
        $user_id =  $this->get_user_id();
        $page = input('page',1);
        $num = input('num',10);
        $content_num = input('content_num',20);
        $list = Db::name('sharing_circle')->alias('s')->join('sharing_comment sc','sc.sharing_id=s.id')->where('s.user_id',$user_id)->join('member m','m.id=s.user_id')->field('sc.id,sc.sharing_id,sc.content as comment_content,sc.addtime,m.nickname,m.avatar,s.title,s.cover,s.content')->page($page,$num)->select();
        foreach($list as $key=>$val){
            $list[$key]['avatar'] = substr($list[$key]['avatar'],0,1) != 'h'?SITE_URL.$list[$key]['avatar']:$list[$key]['avatar'];
            $list[$key]['addtime'] = date('Y-m-d H:i:s',$val['addtime']);
            $list[$key]['cover'] = $val['cover']?SITE_URL.$val['cover']:'';
            $list[$key]['content'] = mb_substr($val['content'],0,$content_num,'UTF8');
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //个人中心-点赞列表-别人点赞我的
    public function user_point_list()
    {
        $user_id =  $this->get_user_id();
        $page = input('page',1);
        $num = input('num',10);
        $content_num = input('content_num',20);
        $list = Db::name('sharing_circle')->alias('s')->join('sharing_point sp','sp.sharing_id=s.id')->where('s.user_id',$user_id)->join('member m','m.id=s.user_id')->field('sp.id,sp.sharing_id,sp.addtime,m.nickname,m.avatar,s.title,s.cover,s.content')->page($page,$num)->select();
        foreach($list as $key=>$val){
            $list[$key]['avatar'] = substr($list[$key]['avatar'],0,1) != 'h'?SITE_URL.$list[$key]['avatar']:$list[$key]['avatar'];
            $list[$key]['addtime'] = date('Y-m-d H:i:s',$val['addtime']);
            $list[$key]['cover'] = $val['cover']?SITE_URL.$val['cover']:'';
            $list[$key]['content'] = mb_substr($val['content'],0,$content_num,'UTF8');
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //个人中心-收藏列表-别人收藏我的
    public function user_collection_list()
    {
        $user_id =  $this->get_user_id();
        $page = input('page',1);
        $num = input('num',10);
        $content_num = input('content_num',20);
        $list = Db::name('sharing_circle')->alias('s')->join('sharing_collection sc','sc.sharing_id=s.id')->where('s.user_id',$user_id)->join('member m','m.id=s.user_id')->field('sc.id,sc.sharing_id,sc.addtime,m.nickname,m.avatar,s.title,s.cover,s.content')->page($page,$num)->select();
        foreach($list as $key=>$val){
            $list[$key]['avatar'] = substr($list[$key]['avatar'],0,1) != 'h'?SITE_URL.$list[$key]['avatar']:$list[$key]['avatar'];
            $list[$key]['addtime'] = date('Y-m-d H:i:s',$val['addtime']);
            $list[$key]['cover'] = $val['cover']?SITE_URL.$val['cover']:'';
            $list[$key]['content'] = mb_substr($val['content'],0,$content_num,'UTF8');
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //转发
    public function add_forward()
    {
        $user_id =  $this->get_user_id();
        $sharing_id = input('sharing_id');
        $data['user_id'] = $user_id;
        $data['sharing_id'] = $sharing_id;
        if(!$sharing_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'请提供享物圈id','data'=>'']);
        }
        $res =  Db::name('sharing_circle')->where('id',$sharing_id)->setInc('forward_num',1);
        if($res){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'转发成功','data'=>'']);
        }else{
            $this->ajaxReturn(['status' => -1 , 'msg'=>'转发失败','data'=>'']);
        }
    }
    
    //关注
    public function add_follow()
    {
        $user_id = $this->get_user_id();
        $follow_user_id = input('follow_user_id');
        if(!$follow_user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'请提供被关注的用户id','data'=>'']);
        }
        $data['follow_user_id'] = $follow_user_id;
        $data['user_id'] = $user_id;
        $id = Db::name('sharing_follow')->where($data)->value('id');
        if($id){
            $res = Db::name('sharing_follow')->where('id',$id)->delect();
            if($res){
                Db::name('member')->where('id',$follow_user_id)->setDec('follow_num',1);
                $this->ajaxReturn(['status' => 1 , 'msg'=>'取消关注','data'=>'']);
            }
            // $this->ajaxReturn(['status' => -2 , 'msg'=>'您已经关注该用户了','data'=>'']);
        }
        $data['addtime'] = time();
        $res = Db::name('sharing_follow')->insert($data);
        if($res){
            Db::name('member')->where('id',$follow_user_id)->setInc('follow_num',1);
            $this->ajaxReturn(['status' => 1 , 'msg'=>'关注成功','data'=>'']);
        }else{
            $this->ajaxReturn(['status' => -1 , 'msg'=>'关注失败','data'=>'']);
        }
    }

    //搜索
    public function search_sharing()
    {
        $page = input('page',1);
        $num = input('num',10);
        $keyword = input('keyword','');
        $user_id = $this->get_user_id();
        if(!$keyword){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'请输入搜索关键字','data'=>'']);
        }
        //写进搜索记录
        $search['user_id'] = $user_id;
        $search['addtime'] = time();
        $search['keyword'] = $keyword;
        if($page == 1){
            Db::table('search')->insert($search);
        }
        $where['sc.status'] = 1;
        $where['sc.title'] = array('like','%'.$keyword.'%');
        $list = Db::name('sharing_circle')->alias('sc')->join('member m','m.id=sc.user_id','LEFT')->field('m.nickname,sc.id,sc.cover,sc.title,sc.point_num,m.avatar')->where($where)->page($page,$num)->select();
        foreach($list as $key=>$val){
            $list[$key]['avatar'] = substr($val['avatar'],0,1) != 'h'?SITE_URL.$val['avatar']:$val['avatar'];
            $list[$key]['cover'] = $val['cover']?SITE_URL.$val['cover']:'';
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //获取表情包列表
    public function emojis()
    {
        $route = 'static/emojis';
        $url = SITE_URL.'/'. $route.'/';
        $list = scandir($route);
        $result = array();
        foreach($list as $key=>$val){
            if(substr($val,0,1) != '.' && $val){
                $result[] = $url.$val;
            }
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$result]);
    }

    //发送消息
    public function send_news()
    {
        $user_id = $this->get_user_id();
        $receive_id = input('receive_id');
        $content = input('content','');
        if(!$content){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'请输入要发送的内容','data'=>$result]);
        }
        if(!$receive_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'请提供要发送的对象','data'=>$result]);
        }
        $data['receive_id'] = $receive_id;
        $data['user_id'] = $user_id;
        $data['content'] = $content;
        $data['addtime'] = time();
        $data['status'] = 0;
        $res = Db::name('member_news')->insert($data);
        if($res){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'发送成功','data'=>'']);
        }else{
            $this->ajaxReturn(['status' => -1 , 'msg'=>'发送失败','data'=>'']);
        }
    }

    /**
     * 单文件上传
     */
    public function upload_file()
    {
    	// 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('file');
	    // 移动到框架应用根目录/public/uploads/ 目录下
	    if($file){
	        $info = $file->validate(['size'=>1024*1024*10])->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . 'user' . DS);
	        if($info){
	            // 成功上传后 获取上传信息
	            $result['data'] = SITE_URL.'/public/uploads/user/'.$info->getSaveName();
	            $result['status'] = 1;
	            $result['msg'] = '上传成功';
	            $this->ajaxReturn($result);
	        }else{
	            // 上传失败获取错误信息
	            $result['msg'] = $file->getError();
	            $result['status'] = -1;
	            $result['data'] = '';
	            $this->ajaxReturn($result);
	        }
        }
        // 上传失败获取错误信息
        $result['msg'] = '上传文件不存在';
        $result['status'] = -1;
        $result['data'] = '';
        $this->ajaxReturn($result);
    }

    //清空历史搜索记录
    public function empty_search()
    {
        $user_id = $this->get_user_id();
        Db::name('search')->where('user_id',$user_id)->delete();
        $result['msg'] = '操作成功';
        $result['status'] = 1;
        $result['data'] = '';
        $this->ajaxReturn($result);
    }

    //获取某个用户未读消息
    public function get_unread_news()
    {
        $user_id = $this->get_user_id();
        $receive_id = input('receive_id',0);
        if(!$receive_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'获取的用户id不能为空','data'=>'']);
        }
        $list = Db::name('member_news')->where(['user_id'=>$user_id,'receive_id'=>$receive_id,'status'=>0])->field('id,addtime,content')->order('addtime desc')->select();
        foreach($list as $key=>$val){
            $list[$key]['addtime'] = date('Y-m-d H:i:s',$val['addtime']);
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //商品列表
    public function goods_list()
    {
        $page = input('page',1);
        $num = input('num',10);
        $keyword = input('keyword');
        $where['g.is_del'] = 0;
        $where['g.is_show'] = 1;
        $where['i.main'] = 1;
        if($keyword){
            $where['g.goods_name'] = array('like','%'.$keyword.'%');
        }
        $list = Db::name('goods')->alias('g')->join('goods_img i','i.goods_id=g.goods_id')->field('g.goods_id,g.goods_name,g.desc,i.picture')->where($where)->order('add_time desc')->page($page,$num)->select();
        foreach($list as $key=>$val){
            $list[$key]['picture'] = $val['picture']?SITE_URL.$val['picture']:'';
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //品牌列表
    public function brand_list()
    {
        $page = input('page',1);
        $num = input('num',10);
        $keyword = input('keyword','');
        $where['status'] = 0;
        if($keyword){
            $where['name'] = array('like','%'.$keyword.'%');
        }
        $list = Db::name('goods_brand')->where($where)->order('addtime desc')->page($page,$num)->select();
        foreach($list as $key=>$val){
            $list[$key]['priture'] = $val['priture']?SITE_URL.$val['priture']:'';
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

}
