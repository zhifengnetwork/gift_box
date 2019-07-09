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
                //第一位不是h
                $banner[$key]['picture'] =  substr($val['picture'],0,1) == 'h'? $val['picture']: SITE_URL.$val['picture'];
            }
        }
    
        //热门推荐8大分类
        $hot_category = [
            ['name'=>'澳门星选','english'=>'Nine Point','id'=>1],
            ['name'=>'女士礼品','english'=>'Nine Point','id'=>2],
            ['name'=>'男士礼品','english'=>'Nine Point','id'=>3],
            ['name'=>'Boss直选','english'=>'Nine Point','id'=>4],
            ['name'=>'日本本土','english'=>'Nine Point','id'=>5],
            ['name'=>'澳新源产','english'=>'Nine Point','id'=>6],
            ['name'=>'朝   韩','english'=>'Nine Point','id'=>7],
            ['name'=>'欧洲北部','english'=>'Nine Point','id'=>8],
        ];
        //获取首页分类
        $data['jializhixuan'] = ['name'=>'佳礼之选','english'=>'Selection goods','id'=>1];
        $data['jializhixuan']['goods_list'] = $goods_list;
        $data['xingxuanyoupin'] = ['name'=>'星选优品','english'=>'Selection goods','id'=>2];
        $data['xingxuanyoupin']['goods_info'] = ['goods_name'=>'Rolex','price'=>'1999','goods_id'=>1,'picture'=>'http://articleimg.xbiao.com/2019/0705/201907051562323156794.jpg'];
        $data['shishangdapai'] = ['name'=>'时尚大牌','english'=>'Selection goods','id'=>3];
        $data['shishangdapai']['goods_list'][0] = $goods_list[0];
        $data['shishangdapai']['goods_list'][1] = $goods_list[1];
        $data['shishangdapai']['goods_list'][2] = $goods_list[2];
        $data['shishangzhinan'] = ['name'=>'时尚指南','english'=>'Selection goods','id'=>4];
        $data['shishangzhinan']['goods_list'] = $goods_list;
        $data['xinpinshangshi'] = ['name'=>'新品上市','english'=>'Selection goods','id'=>5];
        $data['xinpinshangshi']['goods_list'] = $goods_list;
        $data['xinpinshangshi']['goods_list'][8] = ['goods_name'=>'Rolex','price'=>'1999','goods_id'=>1,'picture'=>'http://articleimg.xbiao.com/2019/0705/201907051562323156794.jpg'];
        $data['chaoliudaogou'] = ['name'=>'潮流导购','english'=>'Selection goods','id'=>6];
        $data['chaoliudaogou']['goods_info'] = ['goods_name'=>'Rolex','price'=>'1999','goods_id'=>1,'picture'=>'http://articleimg.xbiao.com/2019/0705/201907051562323156794.jpg'];
        $data['cainixihuan'] = ['name'=>'猜你喜欢','english'=>'You May Also Like','id'=>7];
        $data['cainixihuan']['goods_list'] = $goods_list;

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

}
