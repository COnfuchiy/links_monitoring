<?php

declare(strict_types=1);


/**
 * Class RequestComponent
 */
class RequestComponent
{

    /**
     * @param string $url
     * @param bool $getOnlyStatus
     * @return array
     */
    public static function request(string $url, bool $getOnlyStatus = false): array
    {
        $curlHandler = curl_init($url);
        if ($getOnlyStatus) {
            curl_setopt($curlHandler, CURLOPT_NOBODY, true);
        }
        curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlHandler, CURLOPT_TIMEOUT, 1);
        curl_setopt($curlHandler, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curlHandler, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curlHandler, CURLOPT_FOLLOWLOCATION, false);
        $result = [];
        $curlOutput = curl_exec($curlHandler);
        $result['code'] = curl_getinfo($curlHandler, CURLINFO_HTTP_CODE);
        $result['status'] = $result['code'] < 400 && $result['code'] >= 200;
        $result['data'] = $curlOutput !== false ? $curlOutput : '';
        $result['url'] = $url;
        curl_close($curlHandler);
        return $result;
    }

}