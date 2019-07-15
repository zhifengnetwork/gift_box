<?php
/**
 * 继承
 */
namespace app\api\controller;

use app\common\model\Config;
use app\common\util\jwt\JWT;
use app\common\util\Redis;
use think\Controller;
use think\Db;
use think\Request;
use think\Session;

class ApiBase extends Controller
{
    protected $uid;
    protected $user_name;
    protected $is_bing_mobile;
    protected $http_host;
    
    public function _initialize()
    {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Headers:*");
        header("Access-Control-Allow-Methods:GET, POST, OPTIONS, DELETE");
        header('Content-Type:application/json; charset=utf-8');

        config((new Config)->getConfig());
    }

    private static $redis = null;
    /*获取redis对象*/
    protected function getRedis()
    {
        if (!self::$redis instanceof Redis) {
            self::$redis = new Redis(Config('cache.redis'));
        }
        return self::$redis;
    }

    /*
     *  开放有可能不需登录controller
     */
    private function freeLoginController()
    {
        $controller = [
            'Shop' => 'shop',
//            'User' => 'user',
        ];
        return $controller;
    }

    public function ajaxReturn($data)
    {
        header('Access-Control-Allow-Origin:*');
        header('Access-Control-Allow-Headers:*');
        header("Access-Control-Allow-Methods:GET, POST, OPTIONS, DELETE");
        header('Content-Type:application/json; charset=utf-8');
        exit(str_replace("\\/", "/", json_encode($data, JSON_UNESCAPED_UNICODE)));
    }

    /**
     * 生成token
     */
    public function create_token($user_id)
    {
        $time = time();
        $payload = array(
            "iss" => "DC",
            "iat" => $time,
            "exp" => $time + 36000,
            "user_id" => $user_id,
        );
        $key = 'zhelishimiyao';
        $token = JWT::encode($payload, $key, $alg = 'HS256', $keyId = null, $head = null);
        return $token;
    }

    /**
     * 解密token
     */
    public function decode_token($token)
    {
        $key = 'zhelishimiyao';
        $payload = json_decode(json_encode(JWT::decode($token, $key, ['HS256'])), true);
        return $payload;
    }

    /**
     *
     *接收头信息
     **/
    public function em_getallheaders()
    {
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

    /**
     * 获取user_id
     */
    public function get_user_id()
    {
        // $headers = $this->em_getallheaders();
        // $token = isset($headers['Token']) ? $headers['Token'] : '';
        // if(!$token){
        //     $token = input('token');
        // }
        // if (!$token) {
        //     $this->ajaxReturn(['status' => -1, 'msg' => 'token不存在', 'data' => null]);
        // }
        // $res = $this->decode_token($token);
        // if (!$res) {
        //     $this->ajaxReturn(['status' => -1, 'msg' => 'token已过期', 'data' => null]);
        // }
        // if (!isset($res['iat']) || !isset($res['exp']) || !isset($res['user_id'])) {
        //     $this->ajaxReturn(['status' => -1, 'msg' => 'token已过期：' . $res, 'data' => null]);
        // }
        // if ($res['iat'] > $res['exp']) {
        //     $this->ajaxReturn(['status' => -1, 'msg' => 'token已过期', 'data' => null]);
        // }
        // if (!isset($res['user_id'])) {
        //     $this->ajaxReturn(['status' => -1, 'msg' => '用户不存在', 'data' => '']);
        // }
        return 83;
        return $res['user_id'];
    }
    
    /**
     *  判断是否绑定手机号码
     */
    protected function is_bing_mobile($openid)
    {
        $mobile = Db::table('member')->where('openid', $openid)->value('mobile');
        if (empty($mobile)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 空
     */
    public function _empty()
    {
        $this->ajaxReturn(['status' => -1, 'msg' => '接口不存在', 'data' => null]);
    }

    /**
     * 如果商品图片是本地的加上链接
     */
    public function setGoodsList($goods_list = array())
    {
        foreach($goods_list as $key=>$val){
            $goods_list[$key]['picture'] = $val['picture']?SITE_URL.$val['picture']:'';
        }
        return $goods_list;
    }
}
