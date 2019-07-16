<?php
/**
 * 用户API
 */
namespace app\api\controller;

use think\Db;

class Index extends ApiBase
{

    /**
     * 首页接口
     */
    public function index()
    {
        
        //首页轮播图
        $banner = Db::table('advertisement')->field('id,picture,url')->where(['page_id'=>1,'state'=>1])->order('sort')->select();
        foreach($banner as $key=>$val){
            if($val['picture']){
                //第一位不是h
                $banner[$key]['picture'] =  substr($val['picture'],0,1) == 'h'? $val['picture']: SITE_URL.$val['picture'];
            }
        }
    
        //热门推荐8大分类
        $hot_category = Db::table('goods_attr')->field('id,name,english')->where('id','>',7)->where('pid',0)->order('sort')->limit(8)->select();
        //获取首页栏目
        $goods_attr = Db::table('goods_attr')->field('id,name,english')->where('id','<',8)->order('id')->where('pid',0)->select();
        
        //佳礼只选-猜你喜欢的商品
        $goods_list1 = Db::table('goods')->alias('g')->join('goods_img i','g.goods_id=i.goods_id','LEFT')->field('g.goods_id,g.goods_name,g.price,i.picture,g.picture as sy_picture')->where(['goods_attr1'=>1,'g.is_recommend'=>1,'is_del'=>0,'is_show'=>1,'i.main'=>1])->order('add_time desc')->limit(8)->select();
        foreach($goods_list1 as $key=>$val){
            $goods_list1[$key]['picture'] = $val['sy_picture']?$val['sy_picture']:$val['picture'];
        }
        $goods_info2 = Db::table('goods')->alias('g')->join('goods_img i','g.goods_id=i.goods_id','LEFT')->field('g.goods_id,g.goods_name,g.price,i.picture,g.picture as sy_picture')->where(['goods_attr1'=>2,'g.is_recommend'=>1,'is_del'=>0,'is_show'=>1,'i.main'=>1])->order('add_time desc')->find();
        if($goods_info2){
            $goods_info2['picture'] = $goods_info2['sy_picture']?$goods_info2['sy_picture']:$goods_info2['picture'];
            $goods_info2['picture'] = $goods_info2['picture']?SITE_URL.$goods_info2['picture']:'';
        }else{
            $goods_info2 = array();
        }

        $goods_list3 = Db::table('goods')->alias('g')->join('goods_img i','g.goods_id=i.goods_id','LEFT')->field('g.goods_id,g.goods_name,g.price,i.picture')->where(['goods_attr1'=>3,'g.is_recommend'=>1,'is_del'=>0,'is_show'=>1,'i.main'=>1])->order('add_time desc')->limit(3)->select();

        $goods_list4 = Db::table('goods')->alias('g')->join('goods_img i','g.goods_id=i.goods_id','LEFT')->field('g.goods_id,g.goods_name,g.price,i.picture,g.picture as sy_picture')->where(['goods_attr1'=>4,'g.is_recommend'=>1,'is_del'=>0,'is_show'=>1,'i.main'=>1])->order('add_time desc')->limit(8)->select();
        foreach($goods_list4 as $key=>$val){
            $goods_list4[$key]['picture'] = $val['sy_picture']?$val['sy_picture']:$val['picture'];
        }

        $goods_list5 = Db::table('goods')->alias('g')->join('goods_img i','g.goods_id=i.goods_id','LEFT')->field('g.goods_id,g.goods_name,g.price,i.picture')->where(['goods_attr1'=>5,'g.is_recommend'=>1,'is_del'=>0,'is_show'=>1,'i.main'=>1])->order('add_time desc')->limit(9)->select();

        $goods_info6 = Db::table('goods')->alias('g')->join('goods_img i','g.goods_id=i.goods_id','LEFT')->field('g.goods_id,g.goods_name,g.price,i.picture,g.picture as sy_picture')->where(['goods_attr1'=>6,'g.is_recommend'=>1,'is_del'=>0,'is_show'=>1,'i.main'=>1])->order('add_time desc')->find();
        if($goods_info6){
            $goods_info6['picture'] = $goods_info6['sy_picture']?$goods_info6['sy_picture']:$goods_info6['picture'];
            $goods_info6['picture'] = $goods_info6['picture']?SITE_URL.$goods_info6['picture']:'';
        }else{
            $goods_info6 = array();
        }

        $goods_list7 = Db::table('goods')->alias('g')->join('goods_img i','g.goods_id=i.goods_id','LEFT')->field('g.goods_id,g.goods_name,g.price,i.picture')->where(['goods_attr1'=>7,'g.is_recommend'=>1,'is_del'=>0,'is_show'=>1,'i.main'=>1])->order('add_time desc')->limit(8)->select();
        //佳礼只选-猜你喜欢的商品

        //组装返回的数据
        $data['jializhixuan'] = $goods_attr[0];
        $data['jializhixuan']['goods_list'] = $this->setGoodsList($goods_list1);
        $data['xingxuanyoupin'] = $goods_attr[1];
        $data['xingxuanyoupin']['goods_info'] = $goods_info2;
        $data['shishangdapai'] =  $goods_attr[2];
        $data['shishangdapai']['goods_list'] = $this->setGoodsList($goods_list3);
        $data['shishangzhinan'] = $goods_attr[3];
        $data['shishangzhinan']['goods_list'] = $this->setGoodsList($goods_list4);
        $data['xinpinshangshi'] = $goods_attr[4];
        $data['xinpinshangshi']['goods_list'] = $this->setGoodsList($goods_list5);
        $data['chaoliudaogou'] = $goods_attr[5];
        $data['chaoliudaogou']['goods_info'] = $goods_info6;
        $data['cainixihuan'] = $goods_attr[6];
        $data['cainixihuan']['goods_list'] = $this->setGoodsList($goods_list7);

        $data['banner'] = $banner;
        $data['hot_category'] = $hot_category;
        
        $this->ajaxReturn(['status' => 1, 'msg' => '成功获取数据','data'=>$data]);
    }

    /**
     * ajax 修改指定表数据字段  一般修改状态 比如 是否推荐 是否开启 等 图标切换的
     * table,id_name,id_value,field,value
     */
    public function changeTableVal(){  
    
        $table = I('table'); // 表名
        $id_name = I('id_name'); // 表主键id名
        $id_value = I('id_value'); // 表主键id值
        $field  = I('field'); // 修改哪个字段
        $value  = I('value'); // 修改字段值
       
        $res = M($table)->where([$id_name => $id_value])->update(array($field=>$value));
        if($res){
            $this->ajaxReturn(['status' => 1, 'msg' => '修改成功']);
        }else{
            $this->ajaxReturn(['status' => 0, 'msg' => '无修改']);
        }
        // 根据条件保存修改的数据
    }

    //获取一级栏目下的二级栏目
    public function get_attr()
    {
        $id = input('id');
        if(!$id){
            $this->ajaxReturn(['status' => -1, 'msg' => '请提供栏目id']);
        }
        //二级
        $list = Db::name('goods_attr')->field('addtime,sort,pid,english,priture',true)->where('pid',$id)->order('sort')->select();
        $info = Db::name('goods_attr')->field('id,name,priture')->where('id',$id)->find();
        $info['priture'] = $info['priture']?SITE_URL.$info['priture']:'';
        $data['list'] = $list;
        $data['info'] = $info;
        $this->ajaxReturn(['status' => 1, 'msg' => '获取数据成功','data'=>$data]);
    }

    //根据二级栏目获取商品
    public function getGoodsList()
    {
        $goods_attr2 = input('id');
        $page = input('page',1);
        $num = input('num',10);
        if(!$goods_attr2){
            $this->ajaxReturn(['status' => -1, 'msg' => '请提供二级栏目id','data'=>[]]);
        }
        $list = Db::table('goods')->alias('g')->join('goods_img i','g.goods_id=i.goods_id','LEFT')->field('g.goods_id,g.goods_name,g.price,i.picture')->where(['goods_attr2'=>$goods_attr2,'is_del'=>0,'is_show'=>1,'i.main'=>1])->order('add_time desc')->page($page,$num)->select();
        $list  = $this->setGoodsList($list);
        $this->ajaxReturn(['status' => 1, 'msg' => '获取数据成功','data'=>$list]);

    }

    //获取常见问题
    public function getProblem()
    {
        $list = Db::name('problem_cate')->field('id,name')->order('sort')->select();
        foreach($list as $key=>$val){
            $list[$key]['list'] = Db::name('problem')->field('id,title,content')->where('cate_id',$val['id'])->order('sort')->select();
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取数据成功','data'=>$list]);
    }

    //获取客服电话
    public function getPhone()
    {
        $phone = Db::name('config')->where(['name'=>'phone','status'=>1])->value('value');
        $this->ajaxReturn(['status' => 1, 'msg' => '获取数据成功','data'=>$phone]);
    }

    //获取随机一组俏皮话
    public function getJoke()
    {
        $list =  Db::name('turntable_joke')->field('status,addtime',true)->where('status',0)->select();
        if(count($list) > 1){
            $data = $list[rand(0,count($list)-1)];
        }else{
            $data = $list;
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取数据成功','data'=>$data]);
    }

    //赠礼须知
    public function getGiftNotice()
    {
        $gift_notice =  Db::name('config')->where(['name'=>'gift_notice','status'=>1])->value('value');
        $this->ajaxReturn(['status' => 1, 'msg' => '获取数据成功','data'=>$gift_notice]);
    }

    //纳税人识别号说明
    public function getIdentification()
    {
        $getIdentification =  Db::name('config')->where(['name'=>'getIdentification','status'=>1])->value('value');
        $this->ajaxReturn(['status' => 1, 'msg' => '获取数据成功','data'=>$getIdentification]);
    }

}
