<?php
# +----------------------------------------------------------------------
# | Author:Stark
# +----------------------------------------------------------------------
# | Date:2020-11-12
# +----------------------------------------------------------------------
# | Desc: CheckRequest 请求参数统一检查类
# +----------------------------------------------------------------------

namespace App\Utility\Ws;

use App\Utility\Http\Response;

class CheckRequest
{

    /**
     * 检查请求中必填参数
     * @param array $index
     * @param array $body
     * @return array
     */
    public static function requestData(array $index = [], array $body = []) : array
    {
        $keys = array_keys($body);
        $msgErrorRet = [];
        foreach ($index as $v) {
            if (!in_array($v, $keys)) {
                $msgErrorRet = Response::codeNotSatisfiable();
                $msgErrorRet['msg'] = "{$v} not found";
                break;
            }
        }
        return $msgErrorRet;
    }


    public static function checkValue($keyName, $value)
    {
        $msgErrorRet = [];
        if ($value == false) {
            $msgErrorRet = Response::codeServerError();
            $msgErrorRet['msg'] = $keyName . ' Not Found';
        }
        return $msgErrorRet;
    }


    public static function checkEmpty($keyName, $value)
    {
        $msgErrorRet = [];
        if (empty($value)) {
            $msgErrorRet = Response::codeForbiddenError();
            $msgErrorRet['msg'] = $keyName . ' Empty';
        }
        return $msgErrorRet;
    }


    public static function checkEq($keyName, $valueLeft, $valueRight)
    {
        $msgErrorRet = [];
        if ($valueLeft != $valueRight) {
            $msgErrorRet = Response::codeAuthent();
            $msgErrorRet['msg'] = $keyName . ' Not Eq';
        }
        return $msgErrorRet;
    }

}
