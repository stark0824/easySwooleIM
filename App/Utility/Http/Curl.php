<?php
# +----------------------------------------------------------------------
# | Author:Stark
# +----------------------------------------------------------------------
# | Date:2020-11-11
# +----------------------------------------------------------------------
# | Desc: Curl 类库
# +----------------------------------------------------------------------


namespace App\Utility\Http;

class Curl
{

    public static function getUrl(string $url)
    {
        $headerArray = array("Content-type:application/json;", "Accept:application/json");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        $output = curl_exec($ch);
        $output = trim($output, '"');
        curl_close($ch);
        $output = json_decode((string)$output, true);
        return $output;
    }
}
