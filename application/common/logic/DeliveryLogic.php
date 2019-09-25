<?php


namespace app\common\logic;
use think\Db;

/**
 * 购物车 逻辑定义
 * Class CatsLogic
 * @package Home\Logic
 */
class DeliveryLogic
{

    /**
     * 查询物流
     */
    public function queryExpress($shipping_code, $invoice_no)
    {     
        //判断变量是否为空
        if((!$shipping_code) or (!$invoice_no)){
            return ['status' => -1, 'message' => '参数有误', 'result' => ''];
        }

        //快递公司转换
        switch ($shipping_code) {
            case 'YD':
            $shipping_code = 'YUNDA';
                break;
            
            case 'shunfeng':
            $shipping_code = 'SFEXPRESS';
                break;
        
            case 'YZPY':
            $shipping_code = 'CHINAPOST';
                break;
            
            case 'YTO':
            $shipping_code = 'YTO';
                break;

            case 'ZTO':
            $shipping_code = 'ZTO';
                break;

            default:
            $shipping_code = '';
                break;
        }

        $condition = array(
            'shipping_code' => $shipping_code,
            'invoice_no' => $invoice_no,
        );
        $is_exists = M('delivery_express')->where($condition)->find();
       
       //判断物流记录表是否已有记录,没有则去请求新数据
        if($is_exists){
            $result = unserialize($is_exists['result']);

            //1为订单签收状态,订1单已经签收,已签收则不去请求新数据
            if($is_exists['issign'] == 1){
                return $result;
            }

            $pre_time = time();
            $flag_time = (int)$is_exists['update_time'];
            $space_time = $pre_time - $flag_time;
            //请求状态正常的数据请求时间间隔小于两小时则不请求新数据
            //其他数据请求时间间隔小于半小时则不请求新数据
            if($result['status'] == 0){
                if($space_time < 7200){
                    return $result;
                }
            }else{
                if($space_time < 1800){
                    return $result;
                }
            }
            
            $result = $this->getDelivery($shipping_code, $invoice_no);
            $result = json_decode($result, true);
            //更新表数据
            $flag = $this->updateData($result, $is_exists['id']);
            return $result;
            
        }else{
            $result = $this->getDelivery($shipping_code, $invoice_no);
            $result = json_decode($result, true);

            $flag = $this->insertData($result, $shipping_code, $invoice_no);
            return $result;
        }

    }

    //物流插表
    public function insertData($result, $shipping_code, $invoice_no)
    {
        $data = array(
            'shipping_code' => $shipping_code,
            'invoice_no' => $invoice_no,
            'result' => serialize($result),
            // 'issign' => $result['result']['issign'],
            'update_time' => time(),
        );
        if(isset($result['result']['issign'])){
            $data['issign'] = $result['result']['issign'];
        }
       
        return M('delivery_express')->insert($data);
    }

    //物流表更新
    public function updateData($result, $id)
    {
        $data = array(
            'result' => serialize($result),
            // 'issign' => $result['result']['issign'],
            'update_time' => time(),
        );
        if(isset($result['result']['issign'])){
            $data['issign'] = $result['result']['issign'];
        }
        
        return M('delivery_express')->where('id', $id)->update($data);
    }

    /**
    *物流接口
    */
    private function getDelivery($shipping_code, $invoice_no)
    {
        $host = "https://wuliu.market.alicloudapi.com";//api访问链接
        $path = "/kdi";//API访问后缀
        $method = "GET";
        $appcode = config('appcode');//替换成自己的阿里云appcode
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        $querys = "no=".$invoice_no."&type=".$shipping_code;  //参数写在这里
        $bodys = "";
        $url = $host . $path . "?" . $querys;//url拼接

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        //curl_setopt($curl, CURLOPT_HEADER, true); //如不输出json, 请打开这行代码，打印调试头部状态码。
        //状态码: 200 正常；400 URL无效；401 appCode错误； 403 次数用完； 500 API网管错误
        if (1 == strpos("$".$host, "https://"))
        {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }

        return curl_exec($curl);
    }

}