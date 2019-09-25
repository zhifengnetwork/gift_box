<?php
namespace app\api\controller;
use Payment\Notify\PayNotifyInterface;
use Payment\Config;
use think\Loader;
use think\Db;

/**
 * @author: helei
 * @createTime: 2016-07-20 18:31
 * @description:
 */

/**
 * 客户端需要继承该接口，并实现这个方法，在其中实现对应的业务逻辑
 * Class TestNotify
 * anthor helei
 */
class TestNotify implements PayNotifyInterface
{
   
}