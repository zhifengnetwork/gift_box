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
    ];

    protected $message = [
        'goods_name.require'    => '商品名称必须填写',
        'cat_id1.require'       => '请选择一级分类',
        'cat_id2.require'       => '请选择二级分类',
        'goods_attr1.require'   => '请选择一级栏目',
        'goods_attr2.require'   => '请选择二级栏目',
        'type_id.require'       => '类型必须选择',
    ];

    protected $scene = [
        'add'     => ['goods_name','cat_id1','cat_id2','goods_attr1','goods_attr2'],
        'edit'    => ['goods_name','cat_id1','cat_id2','goods_attr1','goods_attr2'],
    ];
}
