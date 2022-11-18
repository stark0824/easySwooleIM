<?php
/**
 * Created by PhpStorm.
 * User: stark
 * Date: 2020-11-12
 * Time: 09:28
 */

namespace App\Utility\Ws;

use \EasySwoole\EasySwoole\Logger;

class Params
{
    public static function LoginParams($client_type, $token, $syncstamp)
    {
        Logger::getInstance()->log('接收请求参数token:' . $token . ',syncstamp:' . $syncstamp, Logger::LOG_LEVEL_INFO, Category::$logPrefix[$client_type] . 'login');

        $token = isset($token) && !empty($token) ? $token : '';
        $syncstamp = isset($syncstamp) && !empty($syncstamp) ? $syncstamp : '';
        return [$token, $syncstamp];
    }


    public static function LogoutParams($client_type, $to_uid, $syncstamp)
    {
        self::UidAndSyncstamp($client_type, $to_uid, $syncstamp, 'logout');
        return [$to_uid, $syncstamp];
    }


    public static function PullParams($client_type, $to_uid, $syncstamp)
    {
        self::UidAndSyncstamp($client_type, $to_uid, $syncstamp, 'pull');
        return [$to_uid, $syncstamp];
    }


    public static function ReadParams($client_type, $to_uid, $syncstamp)
    {
        self::UidAndSyncstamp($client_type, $to_uid, $syncstamp, 'read');
        return [$to_uid, $syncstamp];
    }

    public static function ReceivedParams($client_type, $to_uid, $ack, $syncstamp)
    {
        Logger::getInstance()->log('接收请求参数to_uid:' . $to_uid . ', syncstamp:' . $syncstamp . 'ack:' . $ack, Logger::LOG_LEVEL_INFO, Category::$logPrefix[$client_type] . 'received');

        $to_uid = isset($to_uid) && !empty($to_uid) ? $to_uid : 0;
        $syncstamp = isset($syncstamp) && !empty($syncstamp) ? $syncstamp : '';
        $ack = isset($ack) && !empty($ack) ? $ack : '';
        return [$to_uid, $ack, $syncstamp];
    }

    public static function UnReadAllParams($client_type, $to_uid, $syncstamp)
    {
        self::UidAndSyncstamp($client_type, $to_uid, $syncstamp, 'unread_all');
        return [$to_uid, $syncstamp];
    }

    public static function ReadFromCommentsParams($client_type, $to_uid, $syncstamp)
    {
        self::UidAndSyncstamp($client_type, $to_uid, $syncstamp, 'read_from_comments');
        return [$to_uid, $syncstamp];
    }

    public static function ReadToCommentsParams($client_type, $to_uid, $syncstamp)
    {
        self::UidAndSyncstamp($client_type, $to_uid, $syncstamp, 'read_to_comments');
        return [$to_uid, $syncstamp];
    }

    public static function ReadMessageParams($client_type, $to_uid, $syncstamp)
    {
        self::UidAndSyncstamp($client_type, $to_uid, $syncstamp, 'read_message');
        return [$to_uid, $syncstamp];
    }

    public static function AutoRobotParams($client_type, $to_uid, $q_number, $syncstamp)
    {
        Logger::getInstance()->log('接收请求参数to_uid:' . $to_uid . ', syncstamp:' . $syncstamp . ',q_number:' . $q_number, Logger::LOG_LEVEL_INFO, Category::$logPrefix[$client_type] . 'auto_robot');

        $to_uid = isset($to_uid) && !empty($to_uid) ? $to_uid : 0;
        $syncstamp = isset($syncstamp) && !empty($syncstamp) ? $syncstamp : '';
        $q_number = isset($q_number) && !empty($q_number) ? $q_number : '';
        return [$to_uid, $q_number, $syncstamp];
    }

    public static function AutoCustomerParams($client_type, $to_uid, $syncstamp)
    {
        self::UidAndSyncstamp($client_type, $to_uid, $syncstamp, 'auto_customer');
        return [$to_uid, $syncstamp];
    }


    public static function openCustomerParams($client_type, $to_uid, $token, $syncstamp)
    {
        self::UidAndSyncstamp($client_type, $to_uid, $syncstamp, 'open_customer');

        return [$to_uid, $token, $syncstamp];
    }

    public static function pushCustomerParams($client_type, $to_uid, $user_nickname, $user_head, $contents, $syncstamp)
    {

        Logger::getInstance()->log('接收请求参数to_uid:' . $to_uid . 'user_nickname:' . $user_nickname . ', user_head:' . $user_head . ', syncstamp:' . $syncstamp . ',contents:' . $contents, Logger::LOG_LEVEL_INFO, Category::$logPrefix[$client_type] . 'push_customer');
        $to_uid = isset($to_uid) && !empty($to_uid) ? $to_uid : 0;
        $syncstamp = isset($syncstamp) && !empty($syncstamp) ? $syncstamp : '';
        $contents = isset($contents) && !empty($contents) ? $contents : '';
        $user_nickname = isset($user_nickname) && !empty($user_nickname) ? $user_nickname : '';
        $user_head = isset($user_head) && !empty($user_head) ? $user_head : '';
        return [$to_uid, $user_nickname, $user_head, $contents, $syncstamp];
    }

    public static function pushUserParams($client_type, $to_uid, $virtual_uid, $contents, $syncstamp)
    {
        Logger::getInstance()->log('接收请求参数to_uid:' . $to_uid . 'virtual_uid:' . $virtual_uid . ', syncstamp:' . $syncstamp . ',contents:' . $contents, Logger::LOG_LEVEL_INFO, Category::$logPrefix[$client_type] . 'push_user');

        $to_uid = isset($to_uid) && !empty($to_uid) ? $to_uid : 0;
        $syncstamp = isset($syncstamp) && !empty($syncstamp) ? $syncstamp : '';
        $contents = isset($contents) && !empty($contents) ? $contents : '';
        $virtual_uid = isset($virtual_uid) && !empty($virtual_uid) ? $virtual_uid : '';
        return [$to_uid, $virtual_uid, $contents, $syncstamp];
    }

    public static function pullChatRecordParams($client_type, $to_uid, $syncstamp)
    {
        self::UidAndSyncstamp($client_type, $to_uid, $syncstamp, 'pull_chat_record');
        return [$to_uid, $syncstamp];
    }

    public static function readCustomerParams($client_type, $to_uid, $syncstamp)
    {
        self::UidAndSyncstamp($client_type, $to_uid, $syncstamp, 'read_customer');
        return [$to_uid, $syncstamp];
    }


    public static function UidAndSyncstamp($client_type, $to_uid, $syncstamp, $longName)
    {
        Logger::getInstance()->log('接收请求参数to_uid:' . $to_uid . ', syncstamp:' . $syncstamp, Logger::LOG_LEVEL_INFO, Category::$logPrefix[$client_type] . $longName);

        $to_uid = isset($to_uid) && !empty($to_uid) ? $to_uid : 0;
        $syncstamp = isset($syncstamp) && !empty($syncstamp) ? $syncstamp : '';
        return [$to_uid, $syncstamp];
    }

    public static function closeCustomerParams($client_type, $to_uid, $syncstamp)
    {
        self::UidAndSyncstamp($client_type, $to_uid, $syncstamp, 'close_customer');
        return [$to_uid, $syncstamp];
    }

    public static function autoReplyRobotParams($client_type, $to_uid, $syncstamp)
    {
        self::UidAndSyncstamp($client_type, $to_uid, $syncstamp, 'auto_reply_robot');
        return [$to_uid, $syncstamp];
    }

    public static function pushOffLineParams($client_type, $to_uid, $contents, $user_head, $user_nickname, $syncstamp)
    {

        Logger::getInstance()->log('接收请求参数to_uid:' . $to_uid . ', syncstamp:' . $syncstamp . ',contents:' . $contents, Logger::LOG_LEVEL_INFO, Category::$logPrefix[$client_type] . 'push_customer');
        $to_uid = isset($to_uid) && !empty($to_uid) ? $to_uid : 0;
        $syncstamp = isset($syncstamp) && !empty($syncstamp) ? $syncstamp : '';
        $contents = isset($contents) && !empty($contents) ? $contents : '';
        $user_nickname = isset($user_nickname) && !empty($user_nickname) ? $user_nickname : '';
        $user_head = isset($user_head) && !empty($user_head) ? $user_head : '';
        return [$to_uid, $contents, $user_nickname, $user_head, $syncstamp];
    }

    public static function ReadNumberParams($client_type, $to_uid, $syncstamp)
    {
        self::UidAndSyncstamp($client_type, $to_uid, $syncstamp, 'read_number');
        return [$to_uid, $syncstamp];
    }

    public static function ReadOfflineParams($client_type, $to_uid, $chat_id, $vuid, $syncstamp)
    {
        Logger::getInstance()->log('接收请求参数 to_uid:' . $to_uid . ', syncstamp:' . $syncstamp . ',im_rid:' . $chat_id . ',vuid:' . $vuid, Logger::LOG_LEVEL_INFO, Category::$logPrefix[$client_type] . 'read_offline');
        $to_uid = isset($to_uid) && !empty($to_uid) ? $to_uid : 0;
        $syncstamp = isset($syncstamp) && !empty($syncstamp) ? $syncstamp : '';
        $chat_id = isset($chat_id) && !empty($chat_id) ? $chat_id : 0;
        $vuid = isset($vuid) && !empty($vuid) ? $vuid : 0;

        return [$to_uid, $chat_id, $vuid, $syncstamp];
    }

    public static function ReadOnlineParams($client_type, $to_uid, $chat_id, $vuid, $syncstamp)
    {
        Logger::getInstance()->log('接收请求参数 to_uid:' . $to_uid . ', syncstamp:' . $syncstamp . ',im_rid:' . $chat_id . ',vuid:' . $vuid, Logger::LOG_LEVEL_INFO, Category::$logPrefix[$client_type] . 'read_online');
        $to_uid = isset($to_uid) && !empty($to_uid) ? $to_uid : 0;
        $syncstamp = isset($syncstamp) && !empty($syncstamp) ? $syncstamp : '';
        $chat_id = isset($chat_id) && !empty($chat_id) ? $chat_id : 0;
        $vuid = isset($vuid) && !empty($vuid) ? $vuid : 0;

        return [$to_uid, $chat_id, $vuid, $syncstamp];
    }

    public static function pullOfflineMsgParams($client_type, $syncstamp)
    {
        Logger::getInstance()->log('接收请求参数 syncstamp:' . $syncstamp, Logger::LOG_LEVEL_INFO, Category::$logPrefix[$client_type] . 'pull_offline');

        $syncstamp = isset($syncstamp) && !empty($syncstamp) ? $syncstamp : '';
        return [$syncstamp];
    }


}
