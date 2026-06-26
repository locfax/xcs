<?php

namespace Xcs\Helper;

class Curl
{

    /**
     * @param string $url
     * @param mixed $data
     * @param array $httpHead
     * @param bool $retSession
     * @param bool $debug
     * @return array
     */
    public static function send(string $url, mixed $data = '', array $httpHead = [], bool $retSession = false, bool $debug = false): array
    {
        $ch = curl_init();

        if (!$ch) {
            return ['body' => '', 'http_code' => 0, 'http_info' => '缺少curl模块或未启用'];
        }

        if (false !== stripos($url, "https://")) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_COOKIESESSION, $retSession);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_URL, $url);

        if (isset($httpHead['proxy']) && $httpHead['proxy']) {
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            curl_setopt($ch, CURLOPT_PROXY, $httpHead['proxy']);
            unset($httpHead['proxy']);
        }

        /* 设置请求头部 */
        if (!empty($data)) {
            if (is_array($data)) {
                $postStr = http_build_query($data);
            } else {
                $postStr = trim($data);
            }
            if (isset($httpHead['Method'])) {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpHead['Method']);
                unset($httpHead['Method']);
            } else {
                curl_setopt($ch, CURLOPT_POST, true);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postStr);
        } else {
            if (isset($httpHead['Method'])) {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpHead['Method']);
                unset($httpHead['Method']);
            } else {
                curl_setopt($ch, CURLOPT_HTTPGET, true);
            }
        }

        if (isset($httpHead['User-Agent'])) {
            curl_setopt($ch, CURLOPT_USERAGENT, $httpHead['User-Agent']);
            unset($httpHead['User-Agent']);
        } else {
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.101 Safari/537.36');
        }

        if (!empty($httpHead)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHead);
        }

        /* 执行CURL */
        $body = curl_exec($ch);

        /* 获取请求返回的http code */
        $http_info = curl_getinfo($ch);

        /* 关闭资源 */
        curl_close($ch);

        if (!empty($body) && $http_info['content-encoding'] == 'gzip') {
            $body = self::gzip_decode($body);
        }

        return ['body' => $body, 'http_code' => $http_info['http_code'], 'http_info' => $http_info];
    }

    /**
     * @param $data
     * @return bool|string
     */
    private static function gzip_decode($data): bool|string
    {
        if (!function_exists('gzinflate')) {
            return $data;
        }

        $unpacked = gzinflate($data);
        if (false === $unpacked) {
            $unpacked = $data;
        }

        return $unpacked;
    }

}
