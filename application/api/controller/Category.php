<?php
/**
 * 购物车API
 */
namespace app\api\controller;
use think\Request;
use think\Db;

class Category extends ApiBase
{
    /**
    * 获取分类
    */
    public function getCategoryList()
    {
        $pid = input('cat_id',0);
        $list = Db::table('category')->field('cat_id,cat_name,img')->where('pid',$pid)->where('is_show',1)->order('sort')->select();
        foreach($list as $key=>$val){
            $list[$key]['img'] = $val['img']?SITE_URL.'/public/upload/images/'.$val['img']:'';
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$list]);
    }

    /**
    * 搜索商品
    */
    public function search_goods()
    {
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $keyword = input('keyword');
        $page = input('page',1);
        $num = input('num',10);
        $order = input('order','goods_id');
        if(!$keyword){
            $this->ajaxReturn(['status'=>-1,'msg'=>'请输入搜索关键字']);
        }
        //写进搜索记录
        $search['user_id'] = $user_id;
        $search['addtime'] = time();
        $search['keyword'] = $keyword;
        if($page == 1){
            Db::table('search')->insert($search);
        }
        
        $where['g.goods_name'] = ['like','%'.$keyword.'%'];
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
        $list = Db::table('goods')->alias('g')
                ->join('goods_brand b','g.brand_id=b.id','LEFT')
                ->join('goods_img i','i.goods_id=g.goods_id','LEFT')
                ->field('g.goods_id,g.goods_name,g.price,i.picture,b.name')
                ->where($where)->order($order)->page($page,$num)
                ->select();
        $list = $this->setGoodsList($list);
        $this->ajaxReturn(['status'=>1,'msg'=>'获取数据成功','data'=>$list]);
    }

    //获取热门搜索
    public function hot_search()
    {
        $time = time()-24*3600*7;
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        //搜索热词
        $list = Db::table('search')->field('id,keyword,count(*) count')->where('addtime','>',$time)->group('keyword')->order('count desc')->limit(10)->select();
        //搜索关键字
        $data = Db::table('search')->field('id,keyword')->where('user_id',$user_id)->order('addtime desc')->limit(8)->select();
        $result['hot'] = $list;
        $result['history'] = $data;
        $this->ajaxReturn(['status'=>1,'msg'=>'获取数据成功','data'=>$result]);
    }
}
