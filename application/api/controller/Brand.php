<?php
/**
 * 购物车API
 */
namespace app\api\controller;
use think\Request;
use think\Db;

class Brand extends ApiBase
{
    //品牌详情页
    public function brand_info()
    {
        $id = input('id');
        $order = input('order','goods_id');
        if(!$id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'品牌id不能为空','data'=>[]]);
        }
        $page = input('page',1);
        $num = input('num',10);
        $info = Db::name('goods_brand')->field('status,addtime',true)->where('id',$id)->find();
        if(!$info){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'该品牌不存在','data'=>[]]);
        }
        $info['priture'] =  $info['priture3']?SITE_URL.$info['priture3']:'';
        $where['g.is_show'] = 1;
        $where['g.is_del'] = 0;
        $where['i.main'] = 1;
        //判断排序方式
        switch ($order)
        {
        case 'goods_id':
            $order = 'g.goods_id';
            break;  
        case 'new':
            $order = 'g.add_time desc';
            break;
        case 'sales_volume':
            $order = 'g.number_sales desc';
            break;
        case 'price':
            $order = 'g.price';
            break;
        case 'price_desc':
            $order = 'g.price desc';
            break;
        default:
            $order = 'g.goods_id';
        }
        $goods_list = Db::table('goods')->alias('g')
                ->join('goods_img i','i.goods_id=g.goods_id','LEFT')
                ->field('g.goods_id,g.goods_name,g.price,i.picture')
                ->where($where)->order($order)->page($page,$num)
                ->select();
        foreach($goods_list as $key=>$val){
            $goods_list[$key]['picture'] = $val['picture']?SITE_URL.$val['picture']:'';
            $goods_list[$key]['brand_name'] = $info['name'];
        }
        $data['goods_list'] = $goods_list;
        $data['info'] = $info;
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取数据成功','data'=>$data]);
    }

    /**
    * 获取品牌列表
    */
    public function getGoodsBrand()
    {
        $list = Db::name('goods_brand')->field('id,name,priture2 as priture')->where('status',0)->select();
        $new_list = array();
        foreach($list as $key=>$val){
            $val['key'] = getFirstChar($val['name']);
            $val['priture'] = $val['priture']?SITE_URL.$val['priture']:'';
            $new_list[$val['key']][] = $val;
        }
        ksort($new_list);
        $category = Db::table('category')->field('cat_id,cat_name')->where('pid',0)->where('is_show',1)->order('sort')->select();
        $data['category_list'] = $category;
        $data['brand_list'] = $new_list;
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$data]);
    }
}
