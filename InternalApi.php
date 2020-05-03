<?php
/**
 * 服务内部api调用工具
 * User: ryan
 * Date: 2020/5/3
 * Time: 10:50 上午
 */
namespace cloudMall\internalApi;

/**
 * 该类中定义了一个公共的产品线内部API调用
 * Class InternalApi
 * @package cloudMall\internalApi
 */
class InternalApi
{

    public $productLineName = "offline";

    public $secret = "offline_secret";

    public $host = "http://api.cloudmall.com/";

    public $hostReadOnly = "http://api.cloudmall.com/";

    /**
     * 如果需要更换产品线，继承这个类并重载构造函数
     */
    public function __construct()
    {
    }


    public function getProductLine()
    {
        return array("name" => $this->productLineName, "secret" => $this->secret);
    }


    public function setProductLine($name, $secret)
    {
        $this->productLineName = $name;
        $this->secret = $secret;
    }


    public function getHost()
    {
        return $this->host;
    }


    public function setHost($host)
    {
        $this->host = $host;
    }


    /**
     * 发送一个post请求
     * @param $url
     * @param array $params
     * @param int $timeout
     * @return mixed
     * @throws \Exception
     */
    public function post($url, $params = array(), $timeout = 5)
    {
        $params["tpl"] = $this->productLineName;
        $query = "";
        if (YII_DEBUG) {
            $params["x_offline_debug"] = 1;
            $query = "?x_offline_debug=1";
        }
        $params = $this->signAndTimeStamp($params);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->host . $url . $query);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        $postString = json_encode($params);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        // pretend request as ajax to avoid debug log
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-Requested-With: XMLHttpRequest",
            "Content-Type: application/json", 'Content-Length: ' . strlen($postString)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);

        $this->log('POST', $this->host . $url . $query, $params);

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (!$this->isRightCode($code)) {
            $msg = $this->getResultMessage($result);
            throw new \Exception('http code:' . $code . ' ' . $msg);
        }

        return json_decode($result, true);
    }


    /**
     * 直接获取get结果，不decode
     * @param $url
     * @param $params
     * @param int $timeout
     * @return bool|string
     * @throws \Exception
     */
    public function rawGet($url, $params, $timeout = 5) {
        $params["tpl"] = $this->productLineName;
        if (YII_DEBUG) {
            $params["x_offline_debug"] = 1;
        }
        $params = $this->signAndTimeStamp($params);

        $query = '?' . http_build_query($params);

        $ch = curl_init();
        $host = $this->hostReadOnly;
        if (!$host) {
            $host = $this->host;
        }
        curl_setopt($ch, CURLOPT_URL, $host . $url . $query);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        // pretend request as ajax to avoid debug log
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-Requested-With: XMLHttpRequest"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        $this->log('GET', $this->host . $url . $query, $params);

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (!$this->isRightCode($code)) {
            $msg = $this->getResultMessage($result);
            throw new \Exception('http code:' . $code . ' ' . $msg);
        }

        return $result;
    }


    public function get($url, $params = array(), $timeout = 5)
    {
        return json_decode($this->rawGet($url, $params, $timeout), true);
    }


    /**
     * 发送put请求
     * @param $url
     * @param array $params
     * @param int $timeout
     * @return mixed
     * @throws \Exception
     */
    public function put($url, $params = array(), $timeout = 5)
    {
        $params["tpl"] = $this->productLineName;
        $query = "";
        if (YII_DEBUG) {
            $params["x_offline_debug"] = 1;
            $query = "?x_offline_debug=1";
        }
        $params = $this->signAndTimeStamp($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->host . $url . $query);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        $postString = json_encode($params);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST,"PUT"); //设置请求方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        // pretend request as ajax to avoid debug log
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-Requested-With: XMLHttpRequest",
            "Content-Type: application/json", 'Content-Length: ' . strlen($postString)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);

        $this->log('PUT', $this->host . $url . $query, $params);

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (!$this->isRightCode($code)) {

            $msg = $this->getResultMessage($result);
            throw new \Exception('http code:' . $code . ' ' . $msg);
        }

        return json_decode($result, true);
    }


    /**
     * 时间戳与签名验证： (推荐)
     */
    protected function signAndTimeStamp($params)
    {
        $params["timestamp"] = time();
        ksort($params);
        $sign = md5($this->secret . preg_replace('/"/', "", json_encode($params)));
        $params["sign"] = $sign;
        return $params;
    }


    /**
     * 明文密钥验证
     */
    protected function secret($params)
    {
        $params["secret"] = $this->secret;
        return $params;
    }


    /**
     * yii2.0 log
     * @param $action
     * @param $url
     * @param $param
     */
    protected function log($action, $url, $param) {
        \Yii::info([
            'action' => $action,
            'url' => $url,
            'param' => $param
        ], 'api');
    }


    /**
     * 是否是正常http请求
     * @param $code
     * @return bool
     */
    protected function isRightCode($code) {
        return $code >= 200 && $code < 300;
    }


    /**
     * 尽量获取错误详情
     * @param $result
     * @return mixed|string
     */
    protected function getResultMessage($result) {
        $resultArray = json_decode($result, true);
        if (is_array($resultArray)) {
            return $resultArray['message'];
        }
        return '';
    }

}

