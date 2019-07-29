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

}
