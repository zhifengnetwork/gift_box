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
        
        $pid = I('pid');

        if($pid){
            $list = Db::name('category')->where(['is_show'=>1,'pid'=>$pid])->order('sort DESC,cat_id')->select();
        }else{
            $list = Db::name('category')->where(['is_show'=>1,'level'=>1])->order('sort DESC,cat_id')->select();
        }

        foreach($list as $k=>$v){
            $list[$k]['img'] = SITE_URL.'/public/upload/images/'.$v['img'];
            unset($list[$k]["is_show"]);
            unset($list[$k]["desc"]);
        }
        
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$list]);
    }


    public function brand(){

        $list = M('brand')->select();
    
        $list = $this->chartSort($list);

        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$list]);
    }


    /**
    * 将数组按字母A-Z排序
    * @return [type] [description]
    */
    protected function chartSort($list){
        
        foreach ($list as $k => &$v) {
            $v['key'] = $this->getFirstChart( $v['name'] );
            $list[$k]['img'] = SITE_URL.'/public/upload/images/'.$v['img'];
        }
        $data=[];
        foreach ($list as $ks => $vs) {
            $data[$vs['key']][] = $vs;
        }
        

        ksort($data);
       
        return $data;
    }

     /**
    * 返回取汉字的第一个字的首字母
    * @param  [type] $str [string]
    * @return [type]      [strind]
    */
    protected function getFirstChart($str){
      
        if( empty($str) ){
            return '';
        }
        $char=ord($str[0]);
        if( $char >= ord('A') && $char <= ord('z') ){
            return strtoupper($str[0]);
        } 
        $s0 = mb_substr($str,0,3); //获取名字的姓
        // dump($s0);
        // $s1 = iconv('UTF-8','gb2312//IGNORE',$str);
        // $s2 = iconv('gb2312','UTF-8',$s1);
        // $s = $s2 == $str?$s1:$str;
       // $s = $s0;
        // if($str=='嗯'){
        //     echo $s;die;
        // }
        // $s = iconv("utf-8","gb2312//IGNORE",$str);
        // // $s = iconv('UTF-8','gb2312', $s0); //将UTF-8转换成GB2312编码
        $s = $str;
        // dump($s);
        if (ord($s0)>128) { //汉字开头，汉字没有以U、V开头的
        $asc=ord($s{0})*256+ord($s{1})-65536;
            if($asc>=-20319 and $asc<=-20284)return "A";
            if($asc>=-20283 and $asc<=-19776)return "B";
            if($asc>=-19775 and $asc<=-19219)return "C";
            if($asc>=-19218 and $asc<=-18711)return "D";
            if($asc>=-18710 and $asc<=-18527)return "E";
            if($asc>=-18526 and $asc<=-18240)return "F";
            if($asc>=-18239 and $asc<=-17760)return "G";
            if($asc>=-17759 and $asc<=-17248)return "H";
            if($asc>=-17247 and $asc<=-17418)return "I";
            if($asc>=-17417 and $asc<=-16475)return "J";
            if($asc>=-16474 and $asc<=-16213)return "K";
            if($asc>=-16212 and $asc<=-15641)return "L";
            if($asc>=-15640 and $asc<=-15166)return "M";
            if($asc>=-15165 and $asc<=-14923)return "N";
            if($asc>=-14922 and $asc<=-14915)return "O";
            if($asc>=-14914 and $asc<=-14631)return "P";
            if($asc>=-14630 and $asc<=-14150)return "Q";
            if($asc>=-14149 and $asc<=-14091)return "R";
            if($asc>=-14090 and $asc<=-13319)return "S";
            if($asc>=-13318 and $asc<=-12839)return "T";
            if($asc>=-12838 and $asc<=-12557)return "W";
            if($asc>=-12556 and $asc<=-11848)return "X";
            if($asc>=-11847 and $asc<=-11056)return "Y";
            if($asc>=-11055 and $asc<=-10247)return "Z";
        }else if(ord($s)>=48 and ord($s)<=57)
        { //数字开头
            switch(iconv_substr($s,0,1,'utf-8')){
                case 1:return "#";
                case 2:return "#";
                case 3:return "#";
                case 4:return "#";
                case 5:return "#";
                case 6:return "#";
                case 7:return "#";
                case 8:return "#";
                case 9:return "#";
                case 0:return "#";
            }
        }else if(ord($s)>=65 and ord($s)<=90){//大写英文开头
            return substr($s,0,1);
        }else if(ord($s)>=97 and ord($s)<=122){//小写英文开头
            return strtoupper(substr($s,0,1));
        }else{
            return iconv_substr($s0,0,1,'utf-8');
            //中英混合的词语，不适合上面的各种情况，因此直接提取首个字符即可
        }
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

        $goodsRes = Db::table('goods')->alias('g')
                    ->join('goods_attr ga','FIND_IN_SET(ga.attr_id,g.goods_attr)','LEFT')
                    ->field('g.*,GROUP_CONCAT(ga.attr_name) attr_name')
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


    public function getGoodsSpec($goods_id){

        //从规格-属性表中查到所有规格id
        $spec = Db::name('goods_spec_attr')->field('spec_id')->where('goods_id',$goods_id)->select();

        $specArray = array();
        foreach ($spec as $spec_k => $spec_v){
            array_push($specArray,$spec_v['spec_id']);
        }

        $specArray = array_unique($specArray);
        $specStr = implode(',',$specArray);

        $specRes = Db::name('goods_spec')->field('spec_id,spec_name')->where('spec_id','in',$specStr)->select();

        $data = array();
        $data['goods_id'] = $goods_id;
        foreach ($specRes as $key=>$value) {
            //商品规格下的属性
            $data['spec_id'] = $value['spec_id'];
            $specRes[$key]['res'] = Db::name('goods_spec_attr')->field('attr_id,attr_name')->where($data)->select();
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
}
