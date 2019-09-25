<?php
namespace app\common\logic;
use think\Db;

require VENDOR_PATH . 'GifFrameExtractor/GifFrameExtractor.php';

/**
 * 处理gif图 逻辑
 * Class CatsLogic
 * @package common\Logic
 */
class GifLogic 
{

    /**
     * 获取GIF图持续时间
     */
    public function get_duration_time($gifFilePath)
    {

        $gfe = new \GifFrameExtractor\GifFrameExtractor();

        if ($gfe::isAnimatedGif($gifFilePath)) { // check this is an animated GIF
            
            $gfe->extract($gifFilePath);
            
            // Do something with extracted frames ...

            // foreach ($gfe->getFrames() as $frame) {
    
            //     // The frame resource image var
            //     $img = $frame['image'];
                
            //     // The frame duration
            //     $duration = $frame['duration'];
            // }
    
            // $frameImages = $gfe->getFrameImages();

            $getTotalDuration = $gfe->getTotalDuration();

            return [
                'status' => 1,
                'msg' => '获取GIF图时长 成功',
                'data' => $getTotalDuration * (0.01)
            ];

        }else{
            
            return [
                'status' => -1,
                'msg' => '这不是GIF图'
            ];
        }

    }


}