<?php
namespace App\Utility\Ws;

use \EasySwoole\EasySwoole\Logger;

class Result {

    public static function getLoginResult( $client_type,  $uid,  $syncstamp ){

        //返回结果进行处理
        $result = [
            'client_type' => $client_type,
            'msg_type' => 2,
            'uid' => $uid,
            'code' => 200,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp
        ];
        self::Logger($client_type,'login',$result);
        return $result;
    }


    public static function  getLogoutResult ($client_type ,$to_uid, $syncstamp){
        $result = [
            'client_type' => $client_type,
            'msg_type' => 3,
            'uid' => $to_uid,
            'code' => 200,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp
        ];
        self::Logger($client_type,'logout',$result);
        return $result;
    }


    public static function getPullResult($client_type,$to_uid,$unread,$noce_ack,$syncstamp){
        $result = [
            'client_type' => $client_type,
            'msg_type' => 5,
            'uid' => $to_uid,
            'code' => 200,
            'msg'  => 'SUCCESS',
            'body' => [ 'unread' => $unread ],
            'noce_ack' => $noce_ack,
            'syncstamp' => $syncstamp
        ];
        self::Logger($client_type,'pull',$result);
        return $result;
    }


    public static function getReadResult($client_type,$to_uid,$syncstamp){

        $result = [
            'client_type' => $client_type,
            'msg_type' => 7,
            'uid' => $to_uid,
            'code' => 200,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp
        ];
        self::Logger($client_type,'read',$result);
        return $result;
    }


    public static function getReceivedResult($client_type,$noce_ack,$syncstamp){

        $result = [
            'client_type' => $client_type,
            'msg_type' => 8,
            'code' => 200,
            'msg'  => 'SUCCESS',
            'noce_ack' => $noce_ack,
            'syncstamp' => $syncstamp
        ];
        self::Logger($client_type,'received',$result);
        return $result;
    }


    public static function getUnReadAllResult($client_type,$syncstamp,$bodyArray){

        $result = [
            'client_type' => $client_type,
            'msg_type' => 10,
            'code' => 200,
            'msg'  => 'SUCCESS',
            'body' => $bodyArray,
            'syncstamp' => $syncstamp
        ];
        self::Logger($client_type,'unread_all',$result);
        return $result;
    }


    public static function getReadFromCommentsResult($client_type,$syncstamp){
        $result = [
            'client_type' => $client_type,
            'msg_type' => 11,
            'code' => 200,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp
        ];
        self::Logger($client_type,'read_from_comments',$result);
        return $result;
    }

    public static function getReadToCommentsResult($client_type,$syncstamp){
        $result = [
            'client_type' => $client_type,
            'msg_type' => 12,
            'code' => 200,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp
        ];
        self::Logger($client_type,'read_from_comments',$result);
        return $result;
    }

    public static function getReadMessageResult($client_type,$syncstamp){
        $result = [
            'client_type' => $client_type,
            'msg_type' => 13,
            'code' => 200,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp
        ];
        self::Logger($client_type,'read_from_comments',$result);
        return $result;
    }


    public static function Logger($client_type,$logName,$result){
        Logger::getInstance()->log('Result返回结果:'.json_encode($result),Logger::LOG_LEVEL_INFO,Category::$logPrefix[$client_type].$logName );
    }


    public static function getCustomerAuthResult( $client_type,  $uid,  $syncstamp ){

        //返回结果进行处理
        $result = [
            'client_type' => $client_type,
            'msg_type' => 15,
            'uid' => $uid,
            'code' => 200,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp
        ];
        self::Logger($client_type,'auth',$result);
        return $result;
    }

    public static function getRobotReplyResult($client_type,  $answer,  $syncstamp){

        $result = [
            'client_type' => $client_type,
            'msg_type' => 16,
            'code' => 200,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp,
            'body' => ['answer' => $answer ]
        ];
        self::Logger($client_type,'robot',$result);
        return $result;
    }

    public static function getRobotReplyListsResult($client_type,  $data,  $syncstamp){

        $result = [
            'client_type' => $client_type,
            'msg_type' => 23,
            'code' => 200,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp,
            'body' => ['data' => $data ]
        ];
        self::Logger($client_type,'robot_lists',$result);
        return $result;
    }


    public static function getOpenCustomerResult($client_type,$to_uid,$customer_name,$code, $syncstamp,$customer_head = ''){
        $result = [
            'client_type' => $client_type,
            'msg_type' => 17,
            'code' => $code,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp ,
            'body' => [
                'to_uid' => $to_uid,
                'customer_name' =>$customer_name,
                'customer_head' => empty($customer_head) ? Category::$headUrl : $customer_head ,
            ]
        ];
        self::Logger($client_type,'open_customer',$result);
        return $result;
    }

    public static function getPullChatRecordResult($client_type,$chat,$to_uid){
        $data['client_type'] = $client_type;
        $data['to_uid'] = $to_uid;
        $data['msg_type'] = 20;
        $data['code'] = 200;
        $data['msg'] = 'SUCCESS';
        $data['body']['data'] = $chat;
        self::Logger($client_type,'pull_chat_record',$data);
        return $data;
    }

    public static function getReadCustomerResult( $client_type ){
        $data['client_type'] = $client_type;
        $data['msg_type'] = 22;
        $data['code'] = 200;
        $data['msg'] = 'SUCCESS';
        self::Logger($client_type,'read_customer',$data);
        return $data;
    }

    public static function getPushCustomerResult($client_type,$code,$syncstamp){
        $data = [
            'client_type' => $client_type,
            'msg_type' => 18,
            'code' => $code,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp
        ];
        self::Logger($client_type,'push_customer',$data);
        return $data;
    }

    public static function pushUserResult($client_type,$code,$syncstamp){
        $data = [
            'client_type' => $client_type,
            'msg_type' => 19,
            'code' => $code,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp
        ];

        self::Logger($client_type,'push_user',$data);
        return $data;
    }

    public static function closeCustomerResult($client_type){
        $data['client_type'] = $client_type;
        $data['msg_type'] = 21;
        $data['code'] = 200;
        $data['msg'] = 'SUCCESS';
        self::Logger($client_type,'close_customer',$data);
        return $data;
    }


    public static function getOfflineResult($client_type,$code,$syncstamp){
        $data = [
            'client_type' => $client_type,
            'msg_type' => 27,
            'code' => $code,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp
        ];
        self::Logger($client_type,'push_offline',$data);
        return $data;
    }

    public static function getReadNumberMsgResult( $client_type,  $uid,  $syncstamp ){

        //返回结果进行处理
        $result = [
            'client_type' => $client_type,
            'msg_type' => 29,
            'code' => 200,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp
        ];
        self::Logger($client_type,'read_number',$result);
        return $result;
    }

    public static function getReadOfflineMsgResult( $client_type,  $syncstamp ){

        //返回结果进行处理
        $result = [
            'client_type' => $client_type,
            'msg_type' => 30,
            'code' => 200,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp
        ];
        self::Logger($client_type,'read_offline',$result);
        return $result;
    }

    public static function getReadOnlineMsgResult( $client_type,  $syncstamp ){

        //返回结果进行处理
        $result = [
            'client_type' => $client_type,
            'msg_type' => 31,
            'code' => 200,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp
        ];
        self::Logger($client_type,'read_online',$result);
        return $result;
    }


    public static function getChatReceivedResult($client_type,$code,$syncstamp){
        $data = [
            'client_type' => $client_type,
            'msg_type' => 27,
            'code' => $code,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp
        ];
        self::Logger($client_type,'push_offline',$data);
        return $data;
    }

    public static function getReadOffLineMsg($client_type,$code,$syncstamp){
        $data = [
            'client_type' => $client_type,
            'msg_type' => 32,
            'code' => $code,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp
        ];
        self::Logger($client_type,'read_offline',$data);
        return $data;
    }

    public static function pullOfflineMsgResult($client_type,$syncstamp){
        $data = [
            'client_type' => $client_type,
            'msg_type' => 28,
            'code' => 200,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp
        ];
        self::Logger($client_type,'pull_offline_msg',$data);
        return $data;
    }

    public static function getSetAuthorResult( $client_type,  $uid,  $syncstamp ){

        //返回结果进行处理
        $result = [
            'client_type' => $client_type,
            'msg_type' => 60,
            'uid' => $uid,
            'code' => 200,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp
        ];
        self::Logger($client_type,'login',$result);
        return $result;
    }


    public static function getLogoutAuthorResult( $client_type,  $uid,  $syncstamp ){

        //返回结果进行处理
        $result = [
            'client_type' => $client_type,
            'msg_type' => 61,
            'uid' => $uid,
            'code' => 200,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp
        ];
        self::Logger($client_type,'logoutAuthor',$result);
        return $result;
    }

    public static function unReadMessageResult($client_type,$syncstamp){
        $result = [
            'client_type' => $client_type,
            'msg_type' => 63,
            'code' => 200,
            'msg'  => 'SUCCESS',
            'syncstamp' => $syncstamp
        ];
        self::Logger($client_type,'unread_message',$result);
        return $result;
    }
}
