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
        $banner = M('banner')->order('sort')->field('url')->select();
        
        $data['banner'] = $banner;

        $jializhigong = M('goods')->where(['is_jializhigong'=>1,'is_show'=>1])->limit(8)->field('goods_id')->select();
        foreach($jializhigong as $k => $v){
            $jializhigong[$k]['img'] = SITE_URL.M('goods_img')->where(['goods_id'=>$v['goods_id'],'main'=>1])->value('picture');
        }
        $data['jializhigong'] = $jializhigong;

        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $data]);
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
