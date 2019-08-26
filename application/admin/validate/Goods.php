<?php
namespace app\admin\validate;
use think\Validate;
class Goods extends Validate
{
    protected $rule = [
        'goods_name'     => 'require',
        'cat_id1'        => 'require',
        'cat_id2'        => 'require',
        'type_id'        => 'require',
        'goods_attr1'        => 'require',
        'brand_id'        => 'require',
    ];

    protected $message = [
        'goods_name.require'    => '商品名称必须填写',
        'cat_id1.require'       => '请选择商品一级分类',
        'cat_id2.require'       => '请选择商品二级分类',
        'goods_attr1.require'   => '请选择商品一级栏目',
        'type_id.require'       => '类型必须选择',
        'brand_id.require'       => '请选择品牌',
    ];

    protected $scene = [
        'add'     => ['goods_name','cat_id1','cat_id2','goods_attr1','brand_id'],
        'edit'    => ['goods_name','cat_id1','cat_id2','goods_attr1','brand_id'],
    ];
}
