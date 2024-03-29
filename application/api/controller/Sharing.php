<?php
/**
 * 享物圈
 */
namespace app\api\controller;

use app\common\model\Config as ConfigModel;
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
            // $data['status'] = 0;
            $data['status'] = 1;//模仿小红书不需要审核
        }
        $data['title'] = input('title');
        $data['text'] = input('text');
        $data['type'] = input('type');
        $data['content'] = input('content');
        $data['user_id'] = $user_id;
        $data['lat'] = input('lat');
        $data['lon'] = input('lon');
        $data['priture'] = input('priture');
       
        $data['text2'] = input('text2');
        $data['music_id'] = input('music_id',0);
        $topic_name = input('topic_name');

        if(!$data['title'] && !$status){
            // $this->ajaxReturn(['status' => -1 , 'msg'=>'请输入标题','data'=>'']);
        }
        if(!$topic_name && !$status){
            // $this->ajaxReturn(['status' => -1 , 'msg'=>'请选择话题','data'=>'']);
        }
        $topic_id = Db::name('sharing_topic')->where('name',$topic_name)->value('id');
        if($topic_id){
            $data['topic_id'] = $topic_id;
        }else if($topic_name){
            $zdy_id = Db::name('sharing_topic')->where('name','自定义')->value('id');
            if(!$zdy_id){
                $zdy_id = Db::name('sharing_topic')->insertGetId(['name'=>'自定义','addtime'=>time()]);
            }
            $topic['addtime'] = time();
            $topic['name'] = $topic_name;
            $topic['pid'] = $zdy_id;
            $topic_id = Db::name('sharing_topic')->insertGetId($topic);
            $data['topic_id'] = $topic_id;
        }
        $data['cover'] = input('cover','');
        if($data['type'] && !$data['cover']){
            $data['cover'] = $data['priture'];
        }
        $data['cover'] = str_replace(SITE_URL,'',$data['cover']);
        // $data['priture'] = implode(',',$data['priture']);
        $data['priture'] = str_replace(SITE_URL,'',$data['priture']);
        if(!$data['priture'] && $data['type'] == 0  && !$status){
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
            if(!$status){
                Db::name('sharing_circle')->where(['user_id'=>$user_id,'status'=>3])->delete();
            }
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
        $list = Db::name('sharing_topic')->where('status',0)->where('pid',0)->order('sort,addtime desc')->field('id,name')->select();
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
        $lat = input('lat',0);
        $lon = input('lon',0);
        $topic_id = input('topic_id',0);//0推荐 -1附近
        $where['sc.status'] = 1;
        $where['sc.is_del'] = 0;
        if($topic_id == '-1'){
            // $where['sc.is_rec'] = 1;//附近不处理
        }else if($topic_id){
            $topic_arr = Db::name('sharing_topic')->where('pid',$topic_id)->column('id');
            if($topic_arr){
                $where['sc.topic_id'] = array('in',$topic_arr);
            }else{
                $where['sc.topic_id'] = $topic_id;
            }
        }else{
            $where['sc.is_rec'] = 1;
        }
        if($topic_id == '-1'){
            $list = Db::name('sharing_circle')->alias('sc')->join('member m','m.id=sc.user_id','LEFT')->field('m.nickname,sc.id,sc.cover,sc.title,sc.point_num,m.avatar,sc.lat,sc.lon,sc.type')->where($where)->limit(200)->select();
        }else{
            $list = Db::name('sharing_circle')->alias('sc')->join('member m','m.id=sc.user_id','LEFT')->field('m.nickname,sc.id,sc.cover,sc.title,sc.point_num,m.avatar,sc.type')->where($where)->order('sort')->page($page,$num)->select();
        } 
        foreach($list as $key=>$val){
            $list[$key]['avatar'] = substr($val['avatar'],0,1) != 'h'?SITE_URL.$val['avatar']:$val['avatar'];
            $list[$key]['cover'] = $val['cover']?SITE_URL.$val['cover']:'';
            $list[$key]['show'] = false;
            $list[$key]['point_count'] = $this->getCount('point',$val['id']);
        }

        //如果是附近的，根据距离排序
        if($topic_id == '-1'){
            foreach($list as $key=>$val){
                $val['lon'] = $val['lon']?$val['lon']:0;
                $val['lat'] = $val['lat']?$val['lat']:0;
                $list[$key]['distance'] = getdistance($lon,$lat,$val['lon'],$val['lat']);
            }
            //根据字段distance对数组$data进行降序排列
            $distance = array_column($list,'distance');
            array_multisort($distance,SORT_ASC,$list);
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //相关推荐
    public function relevant_sharing()
    {
        $topic_id = input('topic_id',0);
        $where['sc.status'] = 1;
        $where['sc.is_del'] = 0;
        $where['sc.topic_id'] = $topic_id;
        $page = input('page',1);
        $num = input('num',10);
        $list = Db::name('sharing_circle')->alias('sc')->join('member m','m.id=sc.user_id','LEFT')->field('m.nickname,sc.id,sc.cover,sc.title,sc.point_num,m.avatar,sc.type')->where($where)->order('sort')->page($page,$num)->select();
        foreach($list as $key=>$val){
            $list[$key]['avatar'] = substr($val['avatar'],0,1) != 'h'?SITE_URL.$val['avatar']:$val['avatar'];
            $list[$key]['cover'] = $val['cover']?SITE_URL.$val['cover']:'';
            $list[$key]['show'] = false;
            $list[$key]['point_count'] = $this->getCount('point',$val['id']);
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //获取某一项是否点赞关注收藏
    public function getCount($table,$id)
    {
        if(!$table || !$id){
            return 0;
        }
        $px = 'sharing_';
        $user_id = $this->get_user_id();
        $where['user_id'] = $user_id;
        $where['sharing_id'] = $id;
        $count = Db::name($px.$table)->where($where)->count();
        return $count;
    }

    //分享详情
    public function sharing_info()
    {
        $id = input('id',0);
        $user_id = $this->get_user_id();
        $info = Db::name('sharing_circle')->where('id',$id)->find();
        if(!$info){
            $info = Db::name('sharing_circle')->where('user_id',$user_id)->where('status',3)->order('addtime desc')->find();
            if(!$info){
                $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>'']);
            }
            // $this->ajaxReturn(['status' => -1 , 'msg'=>'享物圈不存在','data'=>'']);
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
        $info['music'] = Db::name('sharing_music')->where('id',$info['music_id'])->value('url');
        $info['music'] = $info['music']?SITE_URL.$info['music']:'';
        $info['point_count'] = $this->getCount('point',$id);
        $info['follow_count'] = Db::name('sharing_follow')->where(['user_id'=>$user_id,'follow_user_id'=>$info['user_id']])->count();
        $info['collection_count'] = $this->getCount('collection',$id);
        //记录读过这篇文章
        $log_id = Db::name('sharing_user_log')->where(['user_id'=>$user_id,'sharing_id'=>$id])->value('id');
        $data['addtime'] = time();
        if($log_id){
            Db::name('sharing_user_log')->where('id',$log_id)->update($data);
        }else{
            $data['user_id'] = $user_id;
            $data['sharing_id'] = $id;
            Db::name('sharing_user_log')->insert($data);
        }
        Db::name('sharing_circle')->where('id',$id)->setInc('read_num',1);
        $info['follow_count'] = Db::name('sharing_follow')->where(['user_id'=>$user_id,'follow_user_id'=>$info['user_id']])->count();
        //顶部显示3条评论
        $info['comment'] = Db::name('sharing_comment')->where('sharing_id',$id)->order('addtime desc')->limit(3)->select();
        foreach($info['comment'] as $key=>$val){
            $info['comment'][$key]['nickname'] = Db::name('member')->where('id',$val['user_id'])->value('nickname');
        }
        $info['topic_name'] = Db::name('sharing_topic')->where('id',$info['topic_id'])->value('name');

        //审核
        $shenhe = ConfigModel::where('name', 'shenhe')->value('value');
        if($shenhe == 1){
            $info['is_comment_open'] = 0;
        }else{
            $info['is_comment_open'] = 1;
        }
        //关掉 评论 

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
        $pid = input('pid',0);
        if(!$sharing_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'请提供享物圈id','data'=>'']);
        }
        if(!$content){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'请输入评论内容','data'=>'']);
        }
        $data['user_id'] = $user_id;
        $data['sharing_id'] = $sharing_id;
        $data['content'] = $content;
        $data['pid'] = $pid;
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
        $id = Db::name('sharing_point')->where($data)->value('id');
        if($id){
            Db::name('sharing_circle')->where('id',$sharing_id)->setDec('point_num',1);
            $res = Db::name('sharing_point')->where('id',$id)->delete();
            if($res){
                $this->ajaxReturn(['status' => 1 , 'msg'=>'取消点赞','data'=>'']);
            }
            // $this->ajaxReturn(['status' => -2 , 'msg'=>'您已经点过赞了','data'=>'']);
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
        $list = Db::name('sharing_circle')->alias('s')->join('sharing_comment sc','sc.sharing_id=s.id')->where('s.user_id',$user_id)->join('member m','m.id=s.user_id')->field('sc.id,sc.sharing_id,sc.content as comment_content,sc.addtime,m.nickname,m.avatar,s.title,s.cover,s.content,s.type')->page($page,$num)->select();
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
        $list = Db::name('sharing_circle')->alias('s')->join('sharing_point sp','sp.sharing_id=s.id')->where('s.user_id',$user_id)->join('member m','m.id=s.user_id')->field('sp.id,sp.sharing_id,sp.addtime,m.nickname,m.avatar,s.title,s.cover,s.content,s.type')->page($page,$num)->select();
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
        $list = Db::name('sharing_circle')->alias('s')->join('sharing_collection sc','sc.sharing_id=s.id')->where('s.user_id',$user_id)->join('member m','m.id=s.user_id')->field('sc.id,sc.sharing_id,sc.addtime,m.nickname,m.avatar,s.title,s.cover,s.content,s.type')->page($page,$num)->select();
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
        if($user_id == $follow_user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'你不能关注自己','data'=>'']);
        }
        $data['follow_user_id'] = $follow_user_id;
        $data['user_id'] = $user_id;
        $id = Db::name('sharing_follow')->where($data)->value('id');
        if($id){
            $res = Db::name('sharing_follow')->where('id',$id)->delete();
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
        $list = Db::name('sharing_circle')->alias('sc')->join('member m','m.id=sc.user_id','LEFT')->field('m.nickname,sc.id,sc.cover,sc.title,sc.point_num,m.avatar,sc.type')->where($where)->page($page,$num)->select();
        foreach($list as $key=>$val){
            $list[$key]['avatar'] = substr($val['avatar'],0,1) != 'h'?SITE_URL.$val['avatar']:$val['avatar'];
            $list[$key]['cover'] = $val['cover']?SITE_URL.$val['cover']:'';
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //获取表情包列表
    public function emojis()
    {
        // $route = 'static/emojis';
        // $url = SITE_URL.'/'. $route.'/';
        // $list = scandir($route);
        // $result = array();
        // foreach($list as $key=>$val){
        //     if(substr($val,0,1) != '.' && $val){
        //         $result[] = $url.$val;
        //     }
        // }

        $result = array();
        $list = Db::name('sharing_sticker')->order('sort,addtime desc')->column('img');
        foreach($list as $key=>$val){
            if($val){
                $result[] = SITE_URL.$val;
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

    //获取评论列表
    public function sharing_comment_list()
    {
        $page = input('page',1);
        $num = input('num',10);
        $sharing_id = input('sharing_id',0);
        if(!$sharing_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'缺少享物圈id','data'=>'']);
        }
        $user_id = $this->get_user_id();
        $point_array = Db::name('sharing_comment_point')->where(['sharing_id'=>$sharing_id,'user_id'=>$user_id])->column('comment_id');
        $list = Db::name('sharing_comment')->alias('sc')->join('member m','sc.user_id=m.id')->where('sharing_id',$sharing_id)->where('pid',0)->field('sc.id,sc.content,sc.addtime,sc.point_num,sc.user_id,m.nickname,m.avatar')->order('point_num desc,addtime desc')->page($page,$num)->select();
        foreach($list as $key=>$val){
            $list[$key]['avatar'] = substr($val['avatar'],0,1) != 'h'?SITE_URL.$val['avatar']:$val['avatar'];
            //判断有没有点赞
            if(in_array($val['id'],$point_array)){
                $list[$key]['count'] = 1;
            }else{
                $list[$key]['count'] = 0;
            }
            $list[$key]['addtime'] = $this->time_tran($val['addtime']);
            $tmp_list = array();
            //获取二级评论
            $tmp_list = Db::name('sharing_comment')->alias('sc')->join('member m','sc.user_id=m.id')->where('sharing_id',$sharing_id)->where('pid',$val['id'])->field('sc.id,sc.content,sc.addtime,sc.point_num,sc.user_id,m.nickname,m.avatar')->order('point_num desc,addtime desc')->select();
            foreach($tmp_list as $ko=>$vo){
                $tmp_list[$ko]['avatar'] = substr($vo['avatar'],0,1) != 'h'?SITE_URL.$vo['avatar']:$vo['avatar'];
                if(in_array($val['id'],$point_array)){
                    $tmp_list[$ko]['count'] = 1;
                }else{
                    $tmp_list[$ko]['count'] = 0;
                }
                $tmp_list[$ko]['addtime'] = $this->time_tran($ko['addtime']);
            }
            $list[$key]['list'] = $tmp_list;
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //时间转换
    public function time_tran($time='')
    {
        $zt_be_time = mktime(0,0,0,date('m'),date('d')-1,date('Y'));//昨天开始时间
        $jt_be_time = mktime(0,0,0,date('m'),date('d'),date('Y'));//今天开始时间
        if((time() - $time) < 120){
            return '刚刚';
        }
        if($time < $zt_be_time){
            return date('m-d');
        }
        if($time < $jt_be_time){
            return '昨天 '.date('H:i',$time);
        }
        return '今天 '.date('H:i',$time);
    }

    //对评论进行点赞
    public function comment_point()
    {
        $comment_id = input('comment_id');
        $sharing_id = input('sharing_id');
        $user_id = $this->get_user_id();
        $data['comment_id'] = $comment_id;
        $data['sharing_id'] = $sharing_id;
        $data['user_id'] = $user_id;
        $res = Db::name('sharing_comment_point')->where($data)->delete();
        if($res){
            Db::name('sharing_comment')->where('id',$comment_id)->setDec('point_num',1);
            $this->ajaxReturn(['status' => 1 , 'msg'=>'取消点赞成功','data'=>'']);
        }
        $data['addtime'] = time();
        $res = Db::name('sharing_comment_point')->insert($data);
        if($res){
            Db::name('sharing_comment')->where('id',$comment_id)->setInc('point_num',1);
            $this->ajaxReturn(['status' => 1 , 'msg'=>'点赞成功','data'=>'']);
        }else{
            $this->ajaxReturn(['status' => -1 , 'msg'=>'点赞失败','data'=>'']);
        }

    }

    //我/他发布的文章
    public function my_sharing_list()
    {
        $user_id = input('user_id','');
        if($user_id){
            $where['sc.status'] = 1;
        }else{
            $where['sc.status'] = array('lt',3);
        }
        if(!$user_id){
            $user_id = $this->get_user_id();
        }
        $page = input('page',1);
        $num = input('num',10);
        $where['sc.user_id'] = $user_id;
        $list = Db::name('sharing_circle')->alias('sc')->join('member m','m.id=sc.user_id','LEFT')->field('m.nickname,sc.id,sc.cover,sc.title,sc.point_num,m.avatar,sc.read_num,sc.status,sc.type')->order('sc.addtime desc')->where($where)->page($page,$num)->select();
        foreach($list as $key=>$val){
            $list[$key]['avatar'] = substr($val['avatar'],0,1) != 'h'?SITE_URL.$val['avatar']:$val['avatar'];
            $list[$key]['cover'] = $val['cover']?SITE_URL.$val['cover']:'';
            $list[$key]['show'] = false;
            $list[$key]['count'] = $this->getCount('point',$val['id']);
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //我/他收藏的文章
    public function my_collection_list()
    {
        $user_id = input('user_id','');
        if(!$user_id){
            $user_id = $this->get_user_id();
        }
        $page = input('page',1);
        $num = input('num',10);
        $where['sc.status'] = 1;
        $where['cc.user_id'] = $user_id;
        $list = Db::name('sharing_circle')
                ->alias('sc')
                ->join('member m','m.id=sc.user_id','LEFT')
                ->join('sharing_collection cc','sc.id=cc.sharing_id')
                ->field('m.nickname,sc.id,sc.cover,sc.title,sc.point_num,m.avatar,sc.type')
                ->order('cc.addtime desc')
                ->where($where)
                ->page($page,$num)
                ->select();
        foreach($list as $key=>$val){
            $list[$key]['avatar'] = substr($val['avatar'],0,1) != 'h'?SITE_URL.$val['avatar']:$val['avatar'];
            $list[$key]['cover'] = $val['cover']?SITE_URL.$val['cover']:'';
            $list[$key]['show'] = false;
            $list[$key]['count'] = $this->getCount('point',$val['id']);
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //我/他赞过的文章
    public function my_log_list()
    {
        $user_id = input('user_id','');
        if(!$user_id){
            $user_id = $this->get_user_id();
        }
        $page = input('page',1);
        $num = input('num',10);
        $where['sc.status'] = 1;
        $where['sul.user_id'] = $user_id;
        $list = Db::name('sharing_circle')
                ->alias('sc')
                ->join('member m','m.id=sc.user_id','LEFT')
                ->join('sharing_point sul','sc.id=sul.sharing_id')
                ->field('m.nickname,sc.id,sc.cover,sc.title,sc.point_num,m.avatar,sc.type')
                ->order('sul.addtime desc')
                ->where($where)
                ->page($page,$num)
                ->select();
        foreach($list as $key=>$val){
            $list[$key]['avatar'] = substr($val['avatar'],0,1) != 'h'?SITE_URL.$val['avatar']:$val['avatar'];
            $list[$key]['cover'] = $val['cover']?SITE_URL.$val['cover']:'';
            $list[$key]['show'] = false;
            $list[$key]['count'] = $this->getCount('point',$val['id']);
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //我/他的页面
    public function my_user()
    {
        $user_id = input('user_id',0);
        $new_user_id = $this->get_user_id();
        if(!$user_id){
            $user_id = $this->get_user_id();
        }
        $user = Db::name('member')->field('id,nickname,avatar')->where('id',$user_id)->find();
        
        if(!$user){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'用户不存在','data'=>$user]); 
        }

        $user['follow_num'] = Db::name('sharing_follow')->where('user_id',$user_id)->count();
        $user['fans_num'] = Db::name('sharing_follow')->where('follow_user_id',$user_id)->count();
        $user['article_num'] = Db::name('sharing_circle')->where('user_id',$user_id)->where('status',1)->count();
        $user['user_no'] = 'NO.'.str_pad($user_id,6,"0",STR_PAD_LEFT);        
        $user['avatar'] = substr($user['avatar'],0,1)!='h'?SITE_URL.$user['avatar']:$user['avatar'];
        if($new_user_id != $user_id){
            $user['follow_count'] = Db::name('sharing_follow')->where('user_id',$new_user_id)->where('follow_user_id',$user_id)->count();
        }else{
            $user['follow_count'] = 0;
        }

        //审核
        $shenhe = ConfigModel::where('name', 'shenhe')->value('value');
        if($shenhe == 1){
            $user['is_charge_open'] = 0;
        }else{
            $user['is_charge_open'] = 1;
        }
        //关掉 充值  页面

        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$user]);
    }

    //参与话题
    public function join_topic()
    {
        $pid = input('pid',0);
        $keyword = input('keyword','');
        $page = input('page',1);
        $num = input('num',10);
        //分类
        if(!$pid && !$keyword){
            $list = Db::name('sharing_topic')->field('id,name,img')->where(['pid'=>0,'status'=>0])->order('sort')->page($page,$num)->select();
            foreach($list as $key=>$val){
                $list[$key]['img'] = $val['img']?SITE_URL.$val['img']:'';
            }
            $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
        }
        //搜索或者分类下的话题
        $where['status'] = 0;
        $where['pid'] = array('neq',0);
        if($pid){
            $where['pid'] = $pid;
        }
        if($keyword){
            $where['name'] = array('like','%'.$keyword.'%');
        }
        $list  = Db::name('sharing_topic')->field('id,name')->where($where)->order('sort,addtime desc')->page($page,$num)->select();
        foreach($list as $key=>$val){
            $list[$key]['count'] = Db::name('sharing_circle')->where('topic_id',$val['id'])->where('status',1)->group('user_id')->count();
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //我/他的关注
    public function my_follow()
    {
        $user_id = input('user_id',0);
        $new_user_id = $this->get_user_id();
        $page = input('page',1);
        $num = input('num',10);
        if(!$user_id){
            $user_id = $this->get_user_id();
        }
        $list = Db::name('sharing_follow')->alias('sf')->field('sf.id,sf.follow_user_id as user_id,m.nickname,m.avatar')->where(['user_id'=>$user_id])->order('sf.addtime desc')->join('member m','m.id=sf.follow_user_id')->order('addtime desc')->page($page,$num)->select();
        foreach($list as $key=>$val){
            $list[$key]['avatar'] = substr($val['avatar'],0,1)!='h'?SITE_URL.$val['avatar']:$val['avatar'];
            $list[$key]['article_num'] = Db::name('sharing_circle')->where('user_id',$val['user_id'])->where('status',1)->count();
            $list[$key]['fans_num'] = Db::name('sharing_follow')->where('follow_user_id',$val['user_id'])->count();
            if($new_user_id != $user_id){
                $list[$key]['follow_count'] = Db::name('sharing_follow')->where('user_id',$new_user_id)->where('follow_user_id',$val['user_id'])->count();
            }else{
                $list[$key]['follow_count'] = 1;
            }
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //我/他的粉丝
    public function my_fans()
    {
        $user_id = input('user_id',0);
        $new_user_id = $this->get_user_id();
        $page = input('page',1);
        $num = input('num',10);
        if(!$user_id){
            $user_id = $this->get_user_id();
        }
        $list = Db::name('sharing_follow')->alias('sf')->field('sf.id,sf.user_id,m.nickname,m.avatar')->where(['follow_user_id'=>$user_id])->order('sf.addtime desc')->join('member m','m.id=sf.user_id')->order('addtime desc')->page($page,$num)->select();
        foreach($list as $key=>$val){
            $list[$key]['avatar'] = substr($val['avatar'],0,1)!='h'?SITE_URL.$val['avatar']:$val['avatar'];
            $list[$key]['article_num'] = Db::name('sharing_circle')->where('user_id',$val['user_id'])->where('status',1)->count();
            $list[$key]['fans_num'] = Db::name('sharing_follow')->where('follow_user_id',$val['user_id'])->count();
            $list[$key]['follow_count'] = Db::name('sharing_follow')->where('user_id',$new_user_id)->where('follow_user_id',$val['user_id'])->count();
            
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //地点标签和影视标签
    public function label_list()
    {
        $page = input('page',1);
        $num = input('num',10);
        $type = input('type',1);
        $keyword = input('keyword','');
        $where['type'] = $type;
        if($keyword){
            $where['title'] = array('like','%'.$keyword.'%');
        }
        $list = Db::name('sharing_label')->field('id,title,desc,img')->where($where)->order('addtime desc')->page($page,$num)->select();
        foreach($list as $key=>$val){
            $list[$key]['img'] = $val['img']?SITE_URL.$val['img']:'';
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //地点标签和影视标签
    public function label_list_all()
    {
        $data = array();
        $where = array();
        $where2 = array();
        $where3 = array();
        //地点标签和影视标签
        $keyword = input('keyword','');
        if($keyword){
            $where['title'] = array('like','%'.$keyword.'%');
        }
        $list = Db::name('sharing_label')->field('id,title,desc,img,type')->where($where)->order('addtime desc')->select();
        foreach($list as $key=>$val){
            $list[$key]['img'] = $val['img']?SITE_URL.$val['img']:'';
            if($val['type'] == 1){
                $data['address'][] = $list[$key];
            }else{
                $data['video'][] = $list[$key];
            }
        }
        //商品
        $where2['g.is_del'] = 0;
        $where2['g.is_show'] = 1;
        $where2['i.main'] = 1;
        if($keyword){
            $where2['g.goods_name'] = array('like','%'.$keyword.'%');
        }
        $list2 = Db::name('goods')->alias('g')->join('goods_img i','i.goods_id=g.goods_id')->field('g.goods_id,g.goods_name,g.desc,i.picture')->where($where2)->order('add_time desc')->select();
        foreach($list2 as $key=>$val){
            $list2[$key]['picture'] = $val['picture']?SITE_URL.$val['picture']:'';
        }
        //品牌
        $where3['status'] = 0;
        if($keyword){
            $where3['name'] = array('like','%'.$keyword.'%');
        }
        $list3 = Db::name('goods_brand')->field('id,name,priture,introduce')->where($where3)->order('addtime desc')->select();
        foreach($list3 as $key=>$val){
            $list3[$key]['desc'] = $val['introduce'];
            $list3[$key]['priture'] = $val['priture']?SITE_URL.$val['priture']:'';
        }
        $data['goods'] = $list2;
        $data['brand'] = $list3;
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$data]);
    }



    //通知消息
    public function article_list()
    {
        $page = input('page',1);
        $num = input('num',10);
        $list  = Db::name('sharing_article')->where(['status'=>1])->order('sort,addtime desc')->page($page,$num)->select();
        foreach($list as $key=>$val){
            $list[$key]['logo'] = $val['logo']?SITE_URL.$val['logo']:'';
            $list[$key]['addtime'] = date('Y-m-d H:i:s',$val['addtime']);
            $tmp = explode(',',$val['cover']);
            foreach($tmp as $k=>$v){
                $tmp[$k] = $v?SITE_URL.$v:$v;
            }
            $list[$key]['cover'] = $tmp;
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$list]);
    }

    //通知消息详情
    public function article_info()
    {
        $article_id = input('article_id',0);
        if(!$article_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'消息不存在','data'=>'']);
        }
        $info = Db::name('sharing_article')->where('id',$article_id)->find();
        $info['logo'] = $info['logo']?SITE_URL.$info['logo']:'';
        $info['addtime'] = date('Y-m-d H:i:s',$info['addtime']);
        $tmp = explode(',',$info['cover']);
        foreach($tmp as $k=>$v){
            $tmp[$k] = $v?SITE_URL.$v:$v;
        }
        $info['cover'] = $tmp;
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$info]);
    }

    //获取视频的配乐
    public function get_sharing_music()
    {
        $pid = input('pid',0);
        $page = input('page',1);
        $num = input('num',10);
        if($pid == '-1'){
            $sharing_music = Db::name('sharing_music')->field('id,name,url,desc,length')->where(['status'=>0,'pid'=>array('neq',0)])->page($page,$num)->select();
        }else{
            $sharing_music = Db::name('sharing_music')->field('id,name,url,desc,length')->where(['status'=>0,'pid'=>$pid])->page($page,$num)->select();
        }
        $data = array();
        if(!$pid){
            $data[] = ['id'=>-1,'name'=>'all','url'=>'','desc'=>''];
        }
        foreach($sharing_music as $key=>$val){
            $val['url'] = $val['url']?SITE_URL.$val['url']:'';
            $f = '0'.intval($val['length'] / 60);
            $m = $val['length'] - intval($val['length'] / 60)*60;
            if(count($m)==1){
                $m = '0'.$m;
            }
            $val['length'] =  $f.':'.$m;
            $data[] = $val;
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'成功','data'=>$data]);
    }

    //视频上传
    public function video_path()
    {
        $res = aliyun_upload('timg.gif');
        dump($res);
    }

    //删除文章
    public function del_sharing()
    {
        $id = input('id');
        if(!$id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'文章id不存在','data'=>'']);
        }
        $user_id =  $this->get_user_id();
        $res = Db::name('sharing_circle')->where(['id'=>$id,'user_id'=>$user_id])->delete();
        if($res){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'删除成功','data'=>'']);
        }else{
            $this->ajaxReturn(['status' => -1 , 'msg'=>'删除失败','data'=>'']);
        }
    }

    //删除草稿
    public function del_caogao(){
        $user_id = $this->get_user_id();
        $res = Db::name('sharing_circle')->where(['status'=>3,'user_id'=>$user_id])->delete();
        if($res){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'删除成功','data'=>'']);
        }else{
            $this->ajaxReturn(['status' => -1 , 'msg'=>'删除失败','data'=>'']);
        }
    }

}
