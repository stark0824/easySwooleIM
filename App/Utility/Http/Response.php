<?php
# +----------------------------------------------------------------------
# | Author:Stark
# +----------------------------------------------------------------------
# | Date:2020-11-12
# +----------------------------------------------------------------------
# | Desc: Http Response 响应类
# +----------------------------------------------------------------------

namespace App\Utility\Http;

use EasySwoole\Http\Message\Status;

class Response {

    /**
     * 请求范围不符合要求 code 416
     * @return array
     */
    public static function codeNotSatisfiable(){
        return [
            'code' => Status::CODE_REQUESTED_RANGE_NOT_SATISFIABLE,
            'msg' => Status::getReasonPhrase(Status::CODE_REQUESTED_RANGE_NOT_SATISFIABLE ),
        ];
    }

    /**
     * （服务器内部错误）服务器遇到错误，无法完成请求  code 500
     * @return array
     */
    public static function codeServerError(){
        return [
            'code' => Status::CODE_INTERNAL_SERVER_ERROR,
            'msg' => Status::getReasonPhrase(Status::CODE_INTERNAL_SERVER_ERROR ),
        ];
    }

    /**
     * 服务器拒绝请求 code 403
     * @return array
     */
    public static function codeForbiddenError(){
        return [
            'code' => Status::CODE_FORBIDDEN,
            'msg' => Status::getReasonPhrase(Status::CODE_FORBIDDEN ),
        ];
    }


    public static function codeAuthent(){
        return [
            'code' => Status::CODE_PROXY_AUTHENTICATION_REQUIRED,
            'msg' => Status::getReasonPhrase(Status::CODE_PROXY_AUTHENTICATION_REQUIRED ),
        ];
    }

    public static function codeBadGateway(){
        return [
            'code' => Status::CODE_BAD_GATEWAY,
            'msg' => Status::getReasonPhrase(Status::CODE_BAD_GATEWAY ),
        ];
    }
}
