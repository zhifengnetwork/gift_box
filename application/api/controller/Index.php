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
        $user_id = $this->get_user_id();

        $data = '首页数据';

        $this->ajaxReturn(['status' => 0, 'msg' => '获取成功', 'data' => $data]);
    }

    /***
     * 首页ID
     */
    public function page()
    {
        // $user_id = $this->get_user_id();
        // if(!$user_id){
        //     $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        // }
        $ewei = Db::name('diy_ewei_shop')->where(['status' => 1])->find();

        $this->ajaxReturn(['status' => 1, 'msg' => '获取首页成功！', 'data' => $ewei['id']]);
    }

}
