<?php
/**
 * 购物车API
 */
namespace app\api\controller;
use think\Request;
use think\Db;

class Box extends ApiBase
{
    
    /*
     * 获取应用场景
     */
    public function box_cate_list()
    {
        //获取应用场景
        $list = Db::name('box_cate')->field('id,name')->where(['pid'=>0,'status'=>1])->order('sort')->select();
        foreach($list as $key=>$val){
            $list[$key]['list'] = Db::table('box_cate')->field('id,name,picture')->where('pid',$val['id'])->select();
            foreach($list[$key]['list'] as $k=>$v){
                $list[$key]['list'][$k]['picture'] = $v['picture']?$this->http_host.$v['picture']:'';
            }
        }
        $data['status'] = 1;
        $data['data'] = $list;
        $data['msg'] = '获取数据成功';
        return json($data);
    }

    /*
     * 获取应用场景的数据
     * 
     */
    public function get_cate(){
        $id = input('post.id',0);
        $list = Db::table('box_cate')->field('id,name,picture')->where('pid',$id)->order('sort')->select();
        if(!$list){
            $data['data'] = array();
            $data['status'] = 1;
            $data['msg'] = '获取数据成功';
            return json($data);
        }
        if($id == 0){
            $list[0]['list'] = Db::table('box_cate')->field('id,name,picture')->where('pid',$list[0]['id'])->order('sort')->select();
            foreach($list[0]['list'] as $key=>$val){
                $list[0]['list'][$key]['picture'] = $val['picture']?$this->http_host.$val['picture']:'';
            }
        }
        foreach($list as $k=>$v){
            $list[$key]['picture'] = $v['picture']?$this->http_host.$v['picture']:'';
        }
        $data['data'] = $list;
        $data['status'] = 1;
        $data['msg'] = '获取数据成功';
        return json($data);
    }
    

    /**
     * 文件上传
     */
    public function upload_file()
    {
    	// 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('file');
	    // 移动到框架应用根目录/public/uploads/ 目录下
	    if($file){
	        $info = $file->validate(['size'=>1024*1024*10])->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . 'box' . DS);
	        if($info){
	            // 成功上传后 获取上传信息
	            $result['url'] = $this->http_host.'/public/uploads/box/'.$info->getSaveName();
	            $result['status'] = 1;
	            return json($result);
	        }else{
	            // 上传失败获取错误信息
	            $result['msg'] = $file->getError();
	            $result['status'] = 2;
	            return json($result);
	        }
        }
        // 上传失败获取错误信息
        $result['msg'] = '上传文件不存在';
        $result['status'] = 2;
        return json($result);
    }

    //选择音乐页面
    public function get_music_list()
    {
        $list = Db::table('box_music')->field('id,name,music_url,musician')->where('status',1)->select();
        foreach($list as $key=>$val){
            $list[$key]['music_url'] = $val['music_url']?$this->http_host.$val['music_url']:'';
        }
        $result['status'] = 1;
        $result['data'] = $list;
        $result['msg'] = '获取数据成功';
        return json($result);
    }
}
