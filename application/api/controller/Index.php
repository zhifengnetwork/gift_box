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
        // 定义假数据8条
        $goods_list = [
            ['goods_name'=>'Rolex','price'=>'1999','goods_id'=>1,'picture'=>'http://articleimg.xbiao.com/2019/0705/201907051562323156794.jpg'],
            ['goods_name'=>'Rolex','price'=>'1999','goods_id'=>2,'picture'=>'http://articleimg.xbiao.com/2019/0705/201907051562323156794.jpg'],
            ['goods_name'=>'Rolex','price'=>'1999','goods_id'=>3,'picture'=>'http://articleimg.xbiao.com/2019/0705/201907051562323156794.jpg'],
            ['goods_name'=>'Rolex','price'=>'1999','goods_id'=>4,'picture'=>'http://articleimg.xbiao.com/2019/0705/201907051562323156794.jpg'],
            ['goods_name'=>'Rolex','price'=>'1999','goods_id'=>5,'picture'=>'http://articleimg.xbiao.com/2019/0705/201907051562323156794.jpg'],
            ['goods_name'=>'Rolex','price'=>'1999','goods_id'=>6,'picture'=>'http://articleimg.xbiao.com/2019/0705/201907051562323156794.jpg'],
            ['goods_name'=>'Rolex','price'=>'1999','goods_id'=>7,'picture'=>'http://articleimg.xbiao.com/2019/0705/201907051562323156794.jpg'],
            ['goods_name'=>'Rolex','price'=>'1999','goods_id'=>8,'picture'=>'http://articleimg.xbiao.com/2019/0705/201907051562323156794.jpg'],
        ];
        //首页轮播图
        $banner = Db::table('advertisement')->field('id,picture,url')->where(['page_id'=>1,'state'=>1])->order('sort')->select();
        foreach($banner as $key=>$val){
            if($val['picture']){
                $banner[$key]['picture'] = $this->http_host.$val['picture'];
            }
        }
        //热门推荐8大分类
        $hot_category = [
            ['cat_name'=>'澳门星选','english_name'=>'Nine Point','cat_id'=>1],
            ['cat_name'=>'女士礼品','english_name'=>'Nine Point','cat_id'=>2],
            ['cat_name'=>'男士礼品','english_name'=>'Nine Point','cat_id'=>3],
            ['cat_name'=>'Boss直选','english_name'=>'Nine Point','cat_id'=>4],
            ['cat_name'=>'日本本土','english_name'=>'Nine Point','cat_id'=>5],
            ['cat_name'=>'澳新源产','english_name'=>'Nine Point','cat_id'=>6],
            ['cat_name'=>'朝   韩','english_name'=>'Nine Point','cat_id'=>7],
            ['cat_name'=>'欧洲北部','english_name'=>'Nine Point','cat_id'=>8],
        ];
        //获取首页分类
        $home_caetgory = Db::table('category')->field('cat_name,english_name,cat_id')->where(['pid'=>45,'is_show'=>1])->order('sort')->limit('6')->select();
        foreach($home_caetgory as $key=>$val){
            $home_caetgory[$key]['goods_list'] = $goods_list;
        }
        $data['banner'] = $banner;
        $data['home_caetgory'] = $home_caetgory;
        $data['hot_category'] = $hot_category;
        //猜你喜欢
        $data['guess_like'] = $goods_list;
        $data['status'] = 1;
        $data['msg'] = '成功获取数据';
        return json($data);
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

}
