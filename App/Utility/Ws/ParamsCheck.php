<?php
/**
 * Created by PhpStorm.
 * User: stark
 * Date: 2020-11-12
 * Time: 09:28
 */
namespace App\Utility\Ws;

use App\Utility\Http\Response;

class ParamsCheck {

    public static function checkTokenAndSyncstamp($token,$syncstamp){
        $msgErrorRet = [];
        if( empty($token) || empty($syncstamp)){
            $msgErrorRet = Response::codeNotSatisfiable();
            $msgErrorRet['msg'] = 'Token Not Found';
        }
        return $msgErrorRet;
    }


    public static function checkUid($uid){
        $msgErrorRet = [];
        if($uid == false){
            $msgErrorRet = Response::codeServerError();
            $msgErrorRet['msg'] = 'Uid Not Found';
        }
        return  $msgErrorRet;
    }


    public static function checkTouidAndSyncstamp($to_uid,$syncstamp){
        $msgErrorRet = [];
        if(empty($to_uid) || empty($syncstamp)){
            $msgErrorRet = Response::codeNotSatisfiable();
            $msgErrorRet['msg'] = 'Uid Not Found';
        }

        if(!is_numeric( $to_uid )){
            $msgErrorRet = Response::codeNotSatisfiable();
            $msgErrorRet['msg'] = 'Uid Not Found';
        }
        return $msgErrorRet;
    }


    public static function checkFid($f_uid){
        $msgErrorRet = [];
        if(empty($f_uid)){
            $msgErrorRet = Response::codeForbiddenError();
            $msgErrorRet['msg'] = 'Please login first';
        }
        return $msgErrorRet;
    }


    public static function checkFidAndToUid($f_uid,$to_uid){
        $msgErrorRet = [];
        if( $f_uid != $to_uid ){
            $msgErrorRet = Response::codeAuthent();
            $msgErrorRet['msg'] = 'fUid Error';
        }
        return $msgErrorRet;
    }

    public static function checkAck(){
        $msgErrorRet = [];
        if(!empty($msgErrorRet)){
            $msgErrorRet = Response::codeNotSatisfiable();
            $msgErrorRet['msg'] = 'Ack Not Found';
        }
        return $msgErrorRet;
    }

    public static function checkQnumber($q_number){
        $msgErrorRet = [];
        if( empty($q_number)){
            $msgErrorRet = Response::codeNotSatisfiable();
            $msgErrorRet['msg'] = 'q_number Not Found';
        }
        return $msgErrorRet;
    }

    public static function checkImUser($data = []){
        $msgErrorRet = [];
        if( empty($data)){
            $msgErrorRet = Response::codeNotSatisfiable();
            $msgErrorRet['msg'] = 'im User Not Found';
        }
        return $msgErrorRet;
    }

    public static function checkContents(string $contents){
        $msgErrorRet = [];
        if( empty($contents) || mb_strlen($contents) > 200 ){
            $msgErrorRet = Response::codeNotSatisfiable();
            $msgErrorRet['msg'] = 'contents Not Found';
        }
        return $msgErrorRet;
    }

    public static function checkNickNameAndHead($user_nickname,$user_head){
        $msgErrorRet = [];
        if( empty($user_nickname) || empty($user_head) ){
            $msgErrorRet = Response::codeNotSatisfiable();
            $msgErrorRet['msg'] = 'user_nickname Not Found';
        }
        return $msgErrorRet;
    }

    public static function checkVirtualUid($vuid){
        $msgErrorRet = [];
        if( empty($vuid) ){
            $msgErrorRet = Response::codeNotSatisfiable();
            $msgErrorRet['msg'] = 'vuid Not Found';
        }
        return $msgErrorRet;
    }

    public static function checkDiffManger($vuid,$mid){
        $msgErrorRet = [];
        if( $vuid != $mid ){
            $msgErrorRet = Response::codeAuthent();
            $msgErrorRet['msg'] = 'vuid diff';
        }
        return $msgErrorRet;
    }

    public static function checkChatid($chatid){
        $msgErrorRet = [];
        if( empty($chatid) ){
            $msgErrorRet = Response::codeNotSatisfiable();
            $msgErrorRet['msg'] = 'chatid Not Found';
        }
        return $msgErrorRet;
    }
}
