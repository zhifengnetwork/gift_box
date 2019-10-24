<?php

namespace app\api\controller;

class Xs extends ApiBase
{
    /**
     * describe:上传个人中心背景图片
     * method:post
     * user:xs
     * $param user_id
     * $param file
     */
    public function upload(){
        $user_id = request()->param('user_id');
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('file');
        // 移动到框架应用根目录/public/uploads/ 目录下
        if(!$file){
            echo '图片上传有误';die;
        }
        $info = $file->validate(['ext'=>'jpg,png,gif'])->move(ROOT_PATH . 'public' . DS . 'uploads');
        if($info){
            //成功上传后 获取上传信息
            //输出 jpg
            //echo $info->getExtension();
            //输出 20160820/42a79759f284b767dfcb2a0197904287.jpg
            //echo $info->getSaveName();
            //输出 42a79759f284b767dfcb2a0197904287.jpg
            //echo $info->getFilename();
            //echo $info->pathName;
            //获取图片的存放相对路径
            $filePath = SITE_URL . DS . 'public' . DS . 'uploads' . DS .$info->getSaveName();
            $getInfo = $info->getInfo();
            //获取图片的原名称
            $name = $getInfo['name'];
            //整理数据,写入数据库
            $data = [
//                'user_id' => $user_id,
                'user_diy_background' => $filePath,
//                'add_time' => time(),
//                date('Y-m-d H:i:s')
            ];
            // 插入数据库
            $res = \think\Db::name('member')->where(['id'=>$user_id])->find();
            if($res){
                $affected = \think\Db::name('member')->where(['id'=>$user_id])->update($data);
                if($affected){
                    $this->ajaxReturn( ['status'=>1,'msg'=>'上传图片成功','data'=>$data]);
                }
            }elseif (!$res){
                echo '参数错误';die;
            }
        }else{
            // 上传失败获取错误信息
            echo $file->getError();
        }
    }


}
