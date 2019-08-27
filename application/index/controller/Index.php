<?php
namespace app\index\controller;
use app\common\logic\GifLogic;

class Index
{
    public function index()
    {
    
        echo "首页";
    }

    /**
     * 获取 gif 图的 播放时长
     */
    public function test(){

        $dir = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/') . '/public';

        $gifFilePath = $dir.'/test/1.gif';

        $logic = new GifLogic;
        $res = $logic->get_duration_time($gifFilePath);

       dump($res);


    }
}
