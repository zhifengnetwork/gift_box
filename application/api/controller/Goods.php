<?php
/**
 * 用户API
 */
namespace app\api\controller;
use app\common\model\Users;
use app\common\logic\UsersLogic;
use app\common\logic\GoodsLogic;
use app\common\logic\GoodsPromFactory;
use app\common\model\GoodsCategory;
use think\AjaxPage;
use think\Page;
use think\Db;

class Goods extends ApiBase
{

    
   /**
    * 商品分类接口
    * 有pid就是 获取 当前 pid
    * 没有 pid 就是获取 最大分类
    */
    public function categoryList()
    {
        $list = Db::name('goods_brand')->field('id,name,priture')->where('status',0)->select();
        $new_list = array();
        foreach($list as $key=>$val){
            $val['english'] = getFirstChar($val['name']);
            $val['priture'] = $val['priture']?SITE_URL.$val['priture']:'';
            $new_list[$val['english']][] = $val;
        }
        dump($new_list);
        // $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$list]);
    }

    
     
  


    public function brand(){
        $list = M('brand')->field('name,img')->select();
        

       $list = $this->chartSort($list);

        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$list]);
    }

    /**
    * 将数组按字母A-Z排序
    * @return [type] [description]
    */

     // $data['key'] = $vs['key'];
    // $data[$vs['key']][] = $vs;

    protected function chartSort($list){
        foreach ($list as $k => &$v) {
            $v['key'] = getFirstChar( $v['name'] );
            $list[$k]['img'] = SITE_URL.'/public/upload/images/'.$v['img'];
        }

        array_multisort(array_column($list,'key'),SORT_ASC,$list);

        $data=[];
        $dang = "A";
        $num = 0;
        foreach ($list as $ks => $vs) {
            if($vs['key'] == $dang){
                $data[$num][] = $vs;
            }else{
                $dang = $vs['key'];
                $num  = $num + 1;
                $data[$num][] = $vs;
            }
        }

        // ksort($data);
        return $data;
    }

    

    /**
     * 商品详情
     */
    public function goodsDetail()
    {   
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $goods_id = input('goods_id');
        if(!$goods_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'goods_id不存在','data'=>'']);
        }


        $goodsRes = Db::table('goods')->alias('g')
                    ->join('goods_attr ga','FIND_IN_SET(ga.id,g.goods_attr)','LEFT')
                    ->field('g.*,GROUP_CONCAT(ga.name) attr_name')
                    ->where('g.is_show',1)
                    ->find($goods_id);
        if (empty($goodsRes)) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'商品不存在！']);
        }

        if($goodsRes['attr_name']){
            $goodsRes['attr_name'] = explode(',',$goodsRes['attr_name']);
        }else{
            $goodsRes['attr_name'] = [];
        }

        $goodsRes['spec'] = $this->getGoodsSpec($goods_id);
        $goodsRes['stock'] = $goodsRes['spec']['count_num'];
        $goodsRes['groupon_price'] = $goodsRes['spec']['min_groupon_price'];
        unset($goodsRes['spec']['count_num'],$goodsRes['spec']['min_groupon_price']);

        //组图
        $goodsRes['img'] = Db::table('goods_img')->where('goods_id',$goods_id)->field('picture')->order('main DESC')->select();
        foreach($goodsRes['img'] as $k=>$v){
            $v['picture'] && ($goodsRes['img'][$k]['picture'] = SITE_URL . $v['picture']);
        }
        
        //收藏
        $goodsRes['collection'] = Db::table('collection')->where('user_id',$user_id)->where('goods_id',$goods_id)->find();
        if($goodsRes['collection']){
            $goodsRes['collection'] = 1;
        }else{
            $goodsRes['collection'] = 0;
        }

        //评论总数
        $goodsRes['comment_count'] = Db::table('goods_comment')->where('goods_id',$goods_id)->count();

        //限时购
        $goodsRes['is_limited'] = 0;
        $attr = explode(',',$goodsRes['goods_attr']);
        if( in_array(6,$attr) ){
            if($goodsRes['limited_end'] < time()){
                $k =  array_search(6,$attr);
                unset($attr[$k]);
                $goods_attr = implode(',',$attr);
                Db::table('goods')->where('goods_id',$goods_id)->update(['goods_attr'=>$goods_attr]);
                $goodsRes['is_limited'] = 0;
            }else{
                $goodsRes['is_limited'] = 1;
            }
        }

        //优惠券
        $where = [];
        $where['start_time'] = ['<', time()];
        $where['end_time'] = ['>', time()];
        $where['goods_id'] = ['in',$goods_id.',0'];
        $goodsRes['coupon'] = Db::table('coupon')->where($where)->select();
        if($goodsRes['coupon']){
            foreach($goodsRes['coupon'] as $key=>$value){
                $res = Db::table('coupon_get')->where('user_id',$user_id)->where('coupon_id',$value['coupon_id'])->find();
                if($res){
                    $goodsRes['coupon'][$key]['is_lq'] = 1;
                }else{
                    $goodsRes['coupon'][$key]['is_lq'] = 0;
                }
            }
        }
        
        //拼团
        $goodsRes['group'] = [];
        $goodsRes['group_user'] = [];
        $group = Db::table('goods_groupon')->where('goods_id',$goods_id)->where('is_show',1)->where('is_delete',0)->where('status',2)->order('period DESC')->find();
        if($group){
            $goodsRes['group'] = $group;
            $goodsRes['group']['surplus'] = $group['target_number'] - $group['sold_number'];      //剩余量

            //过期或者拼团人数已满，重新生成新团购信息
            if( !$goodsRes['group']['surplus'] || $group['end_time'] < time() ){
                //更改团购过期状态
                $update_res = Db::name('goods_groupon')->where('groupon_id',$group['groupon_id'])->update(['is_show'=>0,'status'=>3]);
                if($update_res){
                    //生成新一期团购
                    $new_roupon = action('Groupon/new_groupon',[$group]);
                    if ($new_roupon) $goodsRes['group'] = $new_roupon;
                }
            }else{
                $goodsRes['group']['surplus_percentage'] = $goodsRes['group']['surplus'] / $group['target_number'];      //剩余百分比

                $group_list = Db::table('order')->alias('o')
                                ->join('member m','m.id=o.user_id','LEFT')
                                ->where('o.groupon_id',$group['groupon_id'])
                                ->where('o.pay_status',1)
                                ->order('o.order_id DESC')
                                ->field('id user_id,nickname,realname,avatar')
                                ->select();
                if($group_list){
                    for($i=0;$i<$group['sold_number'];$i++){
                        $group_list[$i]['cha'] = $group['target_number'] - $group['sold_number'] + $i;
                    }
                }
                
                $goodsRes['group_user'] = $group_list;
            }
        }
        
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$goodsRes]);

    }

    /**
     * 获取评论列表
     */
    public function comment_list(){

        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $goods_id = input('goods_id');
        $page = input('page');

        $pageParam['query']['goods_id'] = $goods_id;

        $comment = Db::table('goods_comment')->alias('gc')
                ->join('member m','m.id=gc.user_id','LEFT')
                ->field('m.mobile,gc.user_id,gc.id comment_id,gc.content,gc.star_rating,gc.replies,gc.praise,gc.add_time,gc.img,gc.sku_id')
                ->where('gc.goods_id',$goods_id)
                ->paginate(10,false,$pageParam);

        $comment = $comment->all();

        if (empty($comment)) {
            $this->ajaxReturn(['status' => 1 , 'msg'=>'暂无评论！','data'=>[]]);
        }
        
        foreach($comment as $key=>$value ){
            
            $comment[$key]['mobile'] = $value['mobile'] ? substr_cut($value['mobile']) : '';

            if($value['img']){
                $comment[$key]['img'] = explode(',',$value['img']);
            }else{
                $comment[$key]['img'] = [];
            }

            $comment[$key]['spec'] = $this->get_sku_str($value['sku_id']);

            $comment[$key]['is_praise'] = Db::table('goods_comment_praise')->where('comment_id',$value['comment_id'])->where('user_id',$user_id)->count();
        }
        
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$comment]);
    }

    //attr_id规格，spec_id属性，属性对应多规格
    public function getGoodsAttrSpec(){
        $goods_id = I('post.goods_id/d',0);
        $spec_id = I('post.spec_id/d',0);
        $attr_id = I('post.attr_id/d',0);
        if(!$goods_id)$this->ajaxReturn(['status' => -1 , 'msg'=>'参数错误！','data'=>[]]);
        if($attr_id && $spec_id){
            $data0 = M('goods_sku')->where(['goods_id'=>$goods_id])->select();
            $GoodsSpecAttr = M('Goods_spec_attr');
            $data = [];
            foreach($data0 as $k=>$v){
                $attrs = explode(',',trim(trim($v['sku_attr'],'{'),'}'));
                if(!checkAttr($attrs,$attr_id,$spec_id))continue;

                $str = '';
                $attributes = [];
                foreach($attrs as $v1){
                    $v1 = explode(':',$v1);
                    $attr_name = $GoodsSpecAttr->where(['attr_id'=>$v1[1]])->value('attr_name');
                    $attr_name && $str .= '_' . $attr_name;
                    $v['attr_name'] = $str;
                    $attributes[] = ['spec_id'=>$v1[0],'attr_id'=>$v1[1]];
                }
                $v['attributes'] = $attributes;
                $data[] = $v;
            }
        }else
            $data = $this->getGoodsSpec($goods_id);

        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$data]);
    }

    public function getGoodsSpec($goods_id){

        //从规格-属性表中查到所有规格id
        //$spec = Db::name('goods_spec_attr')->field('spec_id')->where('goods_id',$goods_id)->select();
        $spec = Db::name('goods_sku')->field('sku_attr')->where('goods_id',$goods_id)->select();
        $specArray = array();
        $specvalArray = array();

        foreach ($spec as $spec_k => $spec_v){   
            $sku_attr = explode(',', trim(trim($spec_v['sku_attr'], '{'), '}'));
            foreach($sku_attr as $v){
                $arr = explode(':',$v);
                array_push($specArray,$arr[0]);
            }
            //array_push($specArray,$spec_v['spec_id']);
        }
        $specArray = array_unique($specArray);
        $specStr = implode(',',$specArray);

        $specRes = Db::name('goods_spec')->field('spec_id,spec_name')->where('spec_id','in',$specStr)->select();

        $data = array();
        $data['goods_id'] = $goods_id;
        foreach ($specRes as $key=>$value) {
            //商品规格下的属性
            $data['spec_id'] = $value['spec_id'];
            $res = Db::name('goods_spec_attr')->field('attr_id,attr_name,false as enable,false as `select`')->where($data)->select();;
            //$specRes[$key]['res'] = Db::name('goods_spec_attr')->field('attr_id,attr_name')->where($data)->select();
            //$res && $res['enable'] = false;
            //$res && $res['select'] = false;
            $specRes[$key]['res'] = $res;
        }

        //sku信息
        $skuRes = Db::name('goods_sku')->where('goods_id',$goods_id)->select();
        $count_num = 0;
        $min = [];
        foreach ($skuRes as $sku_k=>$sku_v){
            $min[] = $sku_v['groupon_price'];
            $skuRes[$sku_k]['inventory'] = $skuRes[$sku_k]['inventory'] - $skuRes[$sku_k]['frozen_stock'];
            $count_num += $skuRes[$sku_k]['inventory'];
            $skuRes[$sku_k]['sku_attr'] = preg_replace("/(\w):/",  '"$1":' ,  $sku_v['sku_attr']);
            $str = preg_replace("/(\w):/",  '"$1":' ,  $sku_v['sku_attr']);
            $arr = json_decode($str,true);
            $str = '';
            foreach($arr as $k=>$v){
                $str .= $v . ',';
            }
            $str = rtrim($str,',');
            $skuRes[$sku_k]['sku_attr1'] = $str;

            // $skuRes[$sku_k]['sku_attr'] = json_decode($sku_v['sku_attr'],true);
        }
        $specData = array();
        $specData['spec_attr'] = $specRes;
        $specData['goods_sku'] = $skuRes;
        $specData['count_num'] = $count_num;
        $specData['min_groupon_price'] = min($min);
        return $specData;
    }

    //获取商品sku字符串
    public function get_sku_str($sku_id)
    {
        $sku_attr = Db::name('goods_sku')->where('sku_id', $sku_id)->value('sku_attr');
        
        $sku_attr = preg_replace("/(\w):/",  '"$1":' ,  $sku_attr);
        $sku_attr = json_decode($sku_attr, true);
        
        foreach($sku_attr as $key=>$value){
            $spec_name = Db::table('goods_spec')->where('spec_id',$key)->value('spec_name');
            $attr_name = Db::table('goods_spec_attr')->where('attr_id',$value)->value('attr_name');
            $sku_attr[$spec_name] = $attr_name;
            unset($sku_attr[$key]);
        }

        $sku_attr = json_encode($sku_attr, JSON_UNESCAPED_UNICODE);
        $sku_attr = str_replace(array('{', '"', '}'), array('', '', ''), $sku_attr);

        return $sku_attr;
    }

    /**
     *  商品分类
     */
    public function goods_cate () {
        $where['level'] = 1;
        $where['is_show'] = 1;
        $field = 'cat_id,cat_name,img,is_show';
        $list = Db::table('category')->where($where)->order('sort desc')->field($field)->select();
        return json(['cede'=>1,'msg'=>'','data'=>$list]);
    }

    /**
     *  商品列表
     */
    public function goods_list () {
        $keyword = request()->param('keyword','');
        $cat_id = request()->param('cat_id',0,'intval');
        $page = request()->param('page',0,'intval');
        $goods = new Goods();
        $list = $goods->getGoodsList($keyword,$cat_id,$page);
        if (!empty($list)){
            return json(['code'=>1,'msg'=>'','data'=>$list]);
        }else{
            return json(['code'=>-2,'msg'=>'没有数据哦','data'=>$list]);
        }
    }

    /**
     *  指定属性商品
     */
    public function selling_goods ()
    {
        $where['is_show'] = 1;
        $where['is_del'] = 0;
        $page = request()->param('page',0,'intval');
        $attr = request()->param('attr',0,'intval');
        $field = 'goods_id,goods_name,price,stock,number_sales,desc';
        $list = model('Goods')->where($where)->where("find_in_set($attr,`goods_attr`)")->field($field)->paginate(4,'',['page'=>$page]);
        if (!empty($list)){
            foreach ($list as &$v){
                $v['picture'] = Db::table('goods_img')->where(['goods_id'=>$v['goods_id'],'main'=>1])->value('picture');
            }
        }
        return json(['code'=>1,'msg'=>'','data'=>$list]);
    }

    /**
     * 限时购
     */
    public function limited_list(){

        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        //限时购专区图片
        $limited_img = Db::table('category')->where('cat_name','like',"%限时购%")->value('img');

        $page = input('page');
        
        $where['is_show'] =  1;
        $where['main'] =  1;
        $where['is_del'] =  0;

        $pageParam['query']['is_show'] = 1;
        $pageParam['query']['is_del'] = 0;


        $list = Db::table('goods')->alias('g')
                ->join('goods_img gi','gi.goods_id=g.goods_id','LEFT')
                ->where($where)
                ->where("FIND_IN_SET(6,goods_attr)")
                ->field('g.goods_id,goods_name,goods_attr,gi.picture img,desc,limited_start,limited_end,price,original_price,stock,stock1')
                ->paginate(5,false,$pageParam);
        $list = $list->all();
        $arr = [];
        if($list){
            foreach($list as $key=>$value){
                if($value['limited_end'] < time()){
                    $attr = explode(',',$value['goods_attr']);
                    $k =  array_search(6,$attr);
                    unset($attr[$k]);
                    $goods_attr = implode(',',$attr);
                    Db::table('goods')->where('goods_id',$value['goods_id'])->update(['goods_attr'=>$goods_attr]);
                    continue;
                }
                $value['purchased'] = $value['stock1'] - $value['stock'];
                $value['surplus'] = $value['stock1'] - $value['purchased'];      //剩余量
                if($value['surplus']){
                    $value['surplus_percentage'] = $value['surplus'] / $value['stock1'];      //剩余百分比
                }else{
                    $value['surplus_percentage'] = 0;      //剩余百分比
                }
                unset($value['goods_attr'],$value['stock'],$value['stock1']);

                $arr[] = $value;
            }
        }

        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>['list'=>$arr,'limited_img'=>$limited_img]]);
    }

    function ts(){
        phpinfo();
    }

    public function praise(){

        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $comment_id = input('comment_id');

        $where['comment_id'] = $comment_id;
        $where['user_id'] = $user_id;

        $res = Db::table('goods_comment_praise')->where($where)->find();

        if($res){
            Db::table('goods_comment')->where('id',$comment_id)->setDec('praise',1);
            Db::table('goods_comment_praise')->where($where)->delete();
            
            $this->ajaxReturn(['status' => 1 , 'msg'=>'取消点赞成功！','data'=>'']);
        }else{
            Db::table('goods_comment')->where('id',$comment_id)->setInc('praise',1);
            Db::table('goods_comment_praise')->insert($where);
            $this->ajaxReturn(['status' => 1 , 'msg'=>'点赞成功！','data'=>'']);
        }
    }

    /*
    attrList: [
        {
          attrName: '空调类型',                    // 规格名称
          attrType: '1',                          // 规格类型
          id: '915859d5376a46d5834f27edcf3dc114', // 规格id
          attr: [                                 // 规格属性列表
            {
              attributeId: '915859d5376a46d5834f27edcf3dc114',   // 规格id
              id: '5',                                           // 此规格属性id
              attributeValue: '正1匹',                           // 属性名称
              enable: false,                                     // 是否可选
              select: false,                                     // 是否选择
            },
            {
              attributeId: '915859d5376a46d5834f27edcf3dc114',
              id: '6',
              attributeValue: '正1.5匹',
              enable: false,
              select: false,
            },
            {
              attributeId: '915859d5376a46d5834f27edcf3dc114',
              id: '7',
              attributeValue: '小1.5匹',
              enable: false,
              select: false,
            },
            {
              attributeId: '915859d5376a46d5834f27edcf3dc114',
              id: '8',
              attributeValue: '正2匹',
              enable: false,
              select: false,
            },
            {
              attributeId: '915859d5376a46d5834f27edcf3dc114',
              id: '9',
              attributeValue: '正3匹',
              enable: false,
              select: false,
            },
          ],
        },
        {
          attrName: '颜色',
          attrType: 'text',
          id: 'e95a7777c08c41769d5207c075a25ddc',
          attr: [
            {
              attributeId: 'e95a7777c08c41769d5207c075a25ddc',
              id: '236bbb1d5c654e9cb3a1493a2bb4785b',
              attributeValue: '红色',
              enable: false,
              select: false,
            },
            {
              attributeId: 'e95a7777c08c41769d5207c075a25ddc',
              id: 'bc6aa3592ab94ad9bd81a319a72c25fe',
              attributeValue: '白色',
              enable: false,
              select: false,
            },
            {
              attributeId: 'e95a7777c08c41769d5207c075a25ddc',
              id: 'f52cf21afd2c42b68cfc3f9c601458f7',
              attributeValue: '黑色',
              enable: false,
              select: false,
            },
          ],
        },
      ], // 清单列表
      skuBeanList: [
        {
          name: '正1匹_红色_', // 名称
          price: '1002',      // 价钱
          count: 100,         // 库存量
          attributes: [
            {
              attributeId: '915859d5376a46d5834f27edcf3dc114', // 规格id
              attributeValId: '5',                             // 属性id
            },
            {
              attributeId: 'e95a7777c08c41769d5207c075a25ddc',
              attributeValId: '236bbb1d5c654e9cb3a1493a2bb4785b',
            },
          ]
        },
        {
          name: '正1匹_白色_',
          price: '1002',
          count: 100,
          attributes: [
            {
              attributeId: '915859d5376a46d5834f27edcf3dc114',
              attributeValId: '5',
            },
            {
              attributeId: 'e95a7777c08c41769d5207c075a25ddc',
              attributeValId: 'bc6aa3592ab94ad9bd81a319a72c25fe',
            }
          ]
        },
        {
          name: '正1.5匹_红色_',
          price: '1002',
          count: 100,
          attributes: [
            {
              attributeId: '915859d5376a46d5834f27edcf3dc114',
              attributeValId: '6',
            },
            {
              attributeId: 'e95a7777c08c41769d5207c075a25ddc',
              attributeValId: '236bbb1d5c654e9cb3a1493a2bb4785b',
            }
          ]
        },
      ], // 存库列表
      */

    public function detail(){

        // $user_id = $this->get_user_id();
        // if(!$user_id){
        //     $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        // }

        $goods_id = input('goods_id');
        if(!$goods_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'goods_id不存在','data'=>'']);
        }

        $data = Db::table('goods')->alias('g')
                    ->join('goods_attr ga','FIND_IN_SET(ga.id,g.goods_attr)','LEFT')
                    ->field('g.*,GROUP_CONCAT(ga.name) attr_name')
                    ->where('g.is_show',1)
                    ->find($goods_id);
        if (empty($data)) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'商品不存在！']);
        }

        $attrList = Db::table('goods_spec_attr')->where(['goods_id'=>$goods_id])->field('spec_id as id')->select();
        $attrList = array_unique($attrList,SORT_REGULAR);

        foreach($attrList as $k => $v){
            $attrList[$k]['attrName'] = Db::table('goods_spec')->where(['spec_id'=>$v['id']])->value('spec_name');
            $attrList[$k]['attr'] = Db::table('goods_spec_attr')->where(['goods_id'=>$goods_id,'spec_id'=>$v['id']])->field('attr_id as attributeId,attr_name as attributeValue,spec_id as id')->select();
            foreach($attrList[$k]['attr'] as $kk => $vv){
                $attrList[$k]['attr'][$kk]['attributeId'] = md5($v['id']);
                $attrList[$k]['attr'][$kk]['enable'] = false;
                $attrList[$k]['attr'][$kk]['select'] = false;
            }
            $attrList[$k]['id'] = md5($v['id']);
        }

        $skuBeanList=Db::table('goods_sku')->where(['goods_id'=>$goods_id])->field('img as name,price,inventory as count,sku_attr')->select();
        foreach ($skuBeanList as $sku_k=>$sku_v){
            $spec=$sku_v['sku_attr'];
            $skuBeanList[$sku_k]['name']="";
            $sku_attr = explode(',', trim(trim($spec, '{'), '}'));
            foreach($sku_attr as $kkk=>$vvv){
                $arr = explode(':',$vvv);
                $skuBeanList[$sku_k]['attributes'][$kkk]['attributeId']=md5($arr[0]);
                $skuBeanList[$sku_k]['attributes'][$kkk]['attributeValId']=md5($arr[1]);
                $sku_name=Db::table('goods_spec_attr')->where(['attr_id'=>$arr[1]])->value('attr_name');
                $skuBeanList[$sku_k]['name']=$skuBeanList[$sku_k]['name'].$sku_name."_";
            }
            unset($skuBeanList[$sku_k]['sku_attr']);
        }


        $data['skuBeanList'] = $skuBeanList;
        $data['attrList'] = $attrList;
        $this->ajaxReturn(['status' => 1 , 'msg'=>'请求成功！','data'=>$data]);
    } 


    /**
     * 获取商品详情.
     */
    public function goodsinfo()
    {
        $goods_id = I('goods_id');
        if(!$goods_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'goods_id不存在','data'=>'']);
        }

        $data = Db::table('goods')->alias('g')
        ->join('goods_attr ga','FIND_IN_SET(ga.id,g.goods_attr)','LEFT')
        ->field('g.*,GROUP_CONCAT(ga.name) attr_name')
        ->where('g.is_show',1)
        ->find($goods_id);

        if (empty($data)) {
        $this->ajaxReturn(['status' => -2 , 'msg'=>'商品不存在！']);
        }

        $attrList = Db::table('goods_spec_attr')->where(['goods_id'=>$goods_id])->field('spec_id as id')->select();
        $attrList = array_unique($attrList,SORT_REGULAR);

        foreach($attrList as $k => $v){
            $goods_spec_list[$k] =  Db::table('goods_spec_attr')->where(['goods_id'=>$goods_id,'spec_id'=>$v['id']])->field('attr_id as item_id,attr_name as item')->select();
            foreach($goods_spec_list[$k] as $kk => $vv){
                $goods_spec_list[$k][$kk]['spec_name'] = Db::table('goods_spec')->where(['spec_id'=>$v['id']])->value('spec_name');
                if($kk == 0){
                    $goods_spec_list[$k][$kk]['isClick'] = 1;
                }else{
                    $goods_spec_list[$k][$kk]['isClick'] = 0;
                }
                $goods_spec_list[$k][$kk]['src'] = '';
            }
        }

        $goods_sku = Db::table('goods_sku')->where(['goods_id'=>$goods_id])->field('sku_id,img,price,inventory as store_count,sku_attr')->select();
        foreach($goods_sku as $k => $v){

            $str = preg_replace("/(\w):/",  '"$1":' ,  $v['sku_attr']);
            $arr = json_decode($str,true);
            $key = '';
            foreach($arr as $kkk => $vvv){
                $key = $key."_".$vvv;
            }
            $key = substr($key,1,strlen($key)-1);

            $spec_goods_price[$key]['key'] = $key; 
            $spec_goods_price[$key]['img'] = $v['img']?SITE_URL.$v['img']:''; 
            $spec_goods_price[$key]['sku_id'] = $v['sku_id']; 
            $spec_goods_price[$key]['price'] = $v['price']; 
            $spec_goods_price[$key]['store_count'] = $v['store_count']; 
        }
        sort($goods_spec_list);
        $data['spec_goods_price'] = $spec_goods_price;
        $data['goods_spec_list'] = $goods_spec_list;
        $this->ajaxReturn(['status' => 1 , 'msg'=>'请求成功！','data'=>$data]);
    }


}
