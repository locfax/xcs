<?php

namespace Xcs\Helper;

class Curl
{

    /**
     * @param $url
     * @param string $data
     * @param array $httpHead
     * @param bool $retGzip
     * @param string $retCharset
     * @param bool $retHead
     * @param bool $retSession
     * @return array
     */
    public static function send($url, $data = '', $httpHead = [], $retGzip = false, $retCharset = 'UTF-8', $retHead = false, $retSession = false)
    {
        $ch = curl_init();
        if (!$ch) {
            return ['header' => '', 'body' => '', 'http_code' => 0, 'http_info' => '缺少curl模块或未启用'];
        }
        if (false !== stripos($url, "https://")) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        /* 结果中是否包含头部信息 */
        curl_setopt($ch, CURLOPT_HEADER, $retHead);
        /* 把结果返回，而非直接输出 */
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        /* 返回SESSION COOKIE */
        curl_setopt($ch, CURLOPT_COOKIESESSION, $retSession);
        /* http 定向级别 */
        curl_setopt($ch, CURLOPT_MAXREDIRS, 7);
        /* 使用0层自动跳转 */
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        /* 超时时间30 */
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        /* 请求地址 */
        curl_setopt($ch, CURLOPT_URL, $url);
        /* HTTP Basic Authentication */
        //curl_setopt($ch,CURLOPT_USERPWD,"username:password");

        $reqHead = $httpHead;

        $fOpen = null;

        /* 设置请求头部 */
        if (!empty($data)) {
            if (is_array($data)) {
                if (isset($data['__formfile'])) {
                    $data[$data['__formfile']] = class_exists('\CURLFile', false) ? new \CURLFile($data[$data['__formfile']]) : '@' . $data[$data['__formfile']];
                    unset($data['__formfile']);
                    $postStr = $data;
                } else {
                    $postStr = http_build_query($data);
                }
            } else {
                $postStr = trim($data);
            }
            if (isset($httpHead['Method'])) {
                if ($httpHead['Method'] == 'PUT') {
                    curl_setopt($ch, CURLOPT_PUT, true);
                    $fOpen = fopen($data[$data['__putfile']], 'r');
                    curl_setopt($ch, CURLOPT_INFILE, $fOpen);//设置上传文件的FILE指针
                    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($data[$data['__putfile']]));//设置上传文件的大小
                } else {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpHead['Method']);
                    unset($httpHead['Method']);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postStr);
                }
            } else {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postStr);
            }
        } else {
            if (isset($httpHead['Method'])) {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpHead['Method']);
                unset($httpHead['Method']);
            } else {
                curl_setopt($ch, CURLOPT_HTTPGET, true);
            }
        }

        if (!isset($httpHead['Host'])) {
            $url_parts = self::raw_url($url);
            $httpHead['Host'] = $url_parts['host'];
        }
        if (isset($httpHead['Set-Cookie'])) {
            curl_setopt($ch, CURLOPT_COOKIE, $httpHead['Set-Cookie']);
            unset($httpHead['Set-Cookie']);
        }
        if (isset($httpHead['Referer'])) {
            curl_setopt($ch, CURLOPT_REFERER, $httpHead['Referer']);
            unset($httpHead['Referer']);
        }
        if (isset($httpHead['User-Agent'])) {
            curl_setopt($ch, CURLOPT_USERAGENT, $httpHead['User-Agent']);
            unset($httpHead['User-Agent']);
        } else {
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        }

        /* 构造头部 */
        $_httpHead = [];
        foreach ($httpHead as $k => $v) {
            $_httpHead[] = $k . ': ' . $v;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $_httpHead);

        /* 执行CURL */
        $http_response = curl_exec($ch);
        //print_r($http_response);

        /* 是否有错误 */
        if (0 != curl_errno($ch)) {
            return ['http_code' => 0, 'http_error' => curl_error($ch)];
        }

        /* 获取请求返回的http code */
        $http_info = curl_getinfo($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        /* 结果头部分析 */
        $http_header = [];
        if ($retHead) {
            $separator = '/\r\n\r\n|\n\n|\r\r/';
            list($_http_header, $http_body) = preg_split($separator, $http_response, 2);
            $http_headers = explode("\n", $_http_header);
            foreach ($http_headers as $header) {
                $spits = explode(':', $header);
                if (count($spits) > 1) {
                    $key = trim($spits[0]);
                    if ('Location' == $key) {
                        $http_header['Location'] = trim(str_replace('Location:', '', $header));
                    } elseif ('Set-Cookie' == $key) {
                        $val = explode(';', $spits[1]);
                        $http_header['Set-Cookie'][] = trim(array_shift($val));
                    } else {
                        $http_header[$key] = trim($spits[1]);
                    }
                }
            }
            if (isset($http_header['Set-Cookie'])) {
                $http_header['Set-Cookie'] = implode("&", $http_header['Set-Cookie']);
            }
        } else {
            $http_body = $http_response;
        }

        $fOpen && fclose($fOpen);

        /* 关闭资源 */
        curl_close($ch);

        if (!empty($http_body)) {
            if ($retGzip) {
                $http_body = self::gzip_decode($http_body, $retGzip);
            }
            if ('UTF-8' != $retCharset) {
                $http_body = self::convert_encode(strtoupper($retCharset), 'UTF-8', $http_body);
            }
        }
        return ['http_head' => $http_header, 'body' => $http_body, 'http_code' => $http_code, 'http_info' => $http_info, 'req_head' => $reqHead];
    }

    /**
     * @param $in
     * @param $out
     * @param $string
     * @return mixed|string
     */
    private static function convert_encode($in, $out, $string)
    { // string change charset return string
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($string, $out, $in);
            //return mb_convert_encoding($string, $out, $in);
        } elseif (function_exists('iconv')) {
            return iconv($in, $out . '//IGNORE', $string);
        } else {
            return $string;
        }
    }

    /**
     * @param $_raw_url
     * @return mixed
     */
    private static function raw_url($_raw_url)
    {
        $raw_url = (string)$_raw_url;
        if (strpos($raw_url, '://') === false) {
            $raw_url = 'http://' . $raw_url;
        }
        $retval = parse_url($raw_url);
        if (!isset($retval['path'])) {
            $retval['path'] = '/';
        }
        if (!isset($retval['port'])) {
            $retval['port'] = '80';
        }
        return $retval;
    }

    /**
     * @param $data
     * @param string $gzip
     * @return bool|string
     */
    private static function gzip_decode($data, $gzip = 'gzip')
    {
        $unpacked = false;
        if ('gzip' == $gzip && function_exists('gzinflate')) {
            $flags = ord(substr($data, 3, 1));
            $headerlen = 10;
            if ($flags & 4) {
                $extralen = unpack('v', substr($data, 10, 2));
                $extralen = (int)$extralen[1];
                $headerlen += 2 + $extralen;
            }
            if ($flags & 8) { // Filename
                $headerlen = @strpos($data, chr(0), $headerlen) + 1;
            }
            if ($flags & 16) { // Comment
                $headerlen = @strpos($data, chr(0), $headerlen) + 1;
            }
            if ($flags & 2) { // CRC at end of file
                $headerlen += 2;
            }
            $unpacked = @gzinflate(substr($data, $headerlen));
        } elseif ('deflate' == $gzip && function_exists('gzuncompress')) {
            $unpacked = @gzuncompress($data);
        }
        if (false === $unpacked) {
            $unpacked = $data;
        }
        return $unpacked;
    }

}
