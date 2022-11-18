<?php
/**
 * 配置常量
 * User: stark
 * Date: 2020-11-11
 * Time: 17:04
 * 服务端主动推送给客户端的消息，只使用类型，不接收参数
 * v1：主要功能推送消息红点
 * v2：统计消息红点数，主动推送给客户端
 * v3：介入客服IM
 * v4：服务端/客户端互发消息(在线/离线)，增加确认机制
 */
namespace App\Utility\Ws;

class Category
{

    //客户端类型区分
    const CLIENT_TYPE_PC = 1;
    const CLIENT_TYPE_H5 = 2;
    const CLIENT_TYPE_IOS = 3;
    const CLIENT_TYPE_ANDROID = 4;
    const CLIENT_TYPE_CPADMIN = 5;

    static $clientType = [
        self:: CLIENT_TYPE_PC,
        self:: CLIENT_TYPE_H5,
        self:: CLIENT_TYPE_IOS,
        self:: CLIENT_TYPE_ANDROID,
        self:: CLIENT_TYPE_CPADMIN
    ];

    static function getClientType(): array
    {
        return self::$clientType;
    }

    //日志后缀
    const LOG_PREFIX_PC = 'pc_';
    const LOG_PREFIX_H5 = 'h5_';
    const LOG_PREFIX_IOS = 'ios_';
    const LOG_PREFIX_ANDROID = 'android_';
    const LOG_PREFIX_CPADMIN = 'cpadmin_';

    static $logPrefix = [
        self::CLIENT_TYPE_PC => self::LOG_PREFIX_PC,
        self::CLIENT_TYPE_H5 => self::LOG_PREFIX_H5,
        self::CLIENT_TYPE_IOS => self::LOG_PREFIX_IOS,
        self::CLIENT_TYPE_ANDROID => self::LOG_PREFIX_ANDROID,
        self::CLIENT_TYPE_CPADMIN => self::LOG_PREFIX_CPADMIN
    ];

    const  HEART = 1;  //心跳检测
    const  LOGIN = 2;  //登陆
    const  LOGOUT = 3;  //退出登陆
    const  COMMENT = 4; //评论推送 (server->client)
    const  PULL = 5;    //获取离线消息
    const  NOTIFICATION = 6; // 站内消息推送 (server->client)
    const  READ = 7;   //消息已读
    const  RECEIVED = 8;  //消息已读确认
    const UNREAD_All = 10;     // 获取总未读消息数
    const UNREAD_FROM_COMMENTS = 11; // 读取收到评论未读消息
    const UNREAD_MESSAGE = 13;       // 读取系统未读消息
    const UNREAD_PUSH_MESSAGE = 14;  // 服务端未读消息数推送 (server->client)
    const CUSTOMER_AUTH = 15;  //vue-admin
    const AUTO_REPLY_ROBOT = 16;
    const OPEN_CUSTOMER = 17;
    const PUSH_CUSTOMER = 18;
    const PUSH_USER = 19;  //vue-admin
    const PULL_CHAT_RECORD = 20;
    const CLOSE_CUSTOMER = 21;
    const READ_CUSTOMER = 22;
    const GET_REPLY_ROBOT = 23;
    const ROBOT_TIMESTAMP = 24;
    const CUSTOMER_HEART = 26;
    const PUSH_OFFLINE_MSG = 27;
    const PULL_OFFLINE_MSG = 28;
    const READ_NUMBER_MSG = 29;
    const READ_OFFLINE_MSG = 30;
    const READ_ONLINE_MSG = 31;
    const CHAT_RECEIVED = 32;
    const IDENTITY_SET_AUTHOR = 60;
    const IDENTITY_LOGOUT_AUTHOR = 61;
    const UNREAD_NOTIFICATION = 63;

    static $msgTypeV1 = [
        self::HEART,
        self::LOGIN,
        self::LOGOUT,
        self::PULL,
        self::READ,
        self::RECEIVED
    ];

    static $msgTypeV2 = [
        self::UNREAD_All,
        self::UNREAD_FROM_COMMENTS,
        self::UNREAD_MESSAGE
    ];

    static $msgTypeV3 = [
        self::CUSTOMER_AUTH,
        self::AUTO_REPLY_ROBOT,
        self::OPEN_CUSTOMER,
        self::PUSH_CUSTOMER,
        self::PUSH_USER,
        self::PULL_CHAT_RECORD,
        self::CLOSE_CUSTOMER,
        self::READ_CUSTOMER,
        self::GET_REPLY_ROBOT,
        self::ROBOT_TIMESTAMP,
        self::CUSTOMER_HEART,
    ];
    static $msgTypeV4 = [
        self::PUSH_OFFLINE_MSG,
        self::PULL_OFFLINE_MSG,
        self::READ_NUMBER_MSG,
        self::READ_OFFLINE_MSG,
        self::READ_ONLINE_MSG,
        self::CHAT_RECEIVED
    ];

    static $msgTypeV5 = [
        self::IDENTITY_SET_AUTHOR,
        self::IDENTITY_LOGOUT_AUTHOR,
        self::UNREAD_NOTIFICATION
    ];

    public static function getMsgTypeDict(): array
    {
        return array_merge(self::$msgTypeV1, self::$msgTypeV2, self::$msgTypeV3, self::$msgTypeV4, self::$msgTypeV5);
    }

    static $unReadFromCommentsKeyName = 'unReadFromComments_';
    static $unReadMessageKeyName = 'unReadMessage_';
    static $imUserRelationName = 'imUserRelation_';
    static $imUserCustomerName = 'imUserCustomer';
    static $openLock = 'openLock_';

    public static function _getUnReadFromCommentsKeyName(int $toUid): string
    {
        return self::$unReadFromCommentsKeyName . $toUid;
    }

    public static function _getUnReadMessageKeyName(int $toUid): string
    {
        return self::$unReadMessageKeyName . $toUid;
    }

    static $headUrl = '';

    public static function getCustomerHeaderUrl(): string
    {
        return self::$headUrl;
    }


    //v4 新增
    static $reply = 'reply';
    static $replyTimestamp = 'replyTimestamp';
    static $unReadServerKeyName = 'unReadServer_';
    static $unReadClientKeyName = 'unReadClient_';

    public static function _getUnReadServerKeyName(int $toUid): string
    {
        return self::$unReadServerKeyName . $toUid;
    }

    public static function _getUnReadClientKeyName($toUid): string
    {
        return self::$unReadClientKeyName . $toUid;
    }


    static $msgTypeNameDict = [
        self:: HEART => 'heart',
        self:: LOGIN => 'login',
        self:: LOGOUT => 'logout',
        self:: PULL => 'pull',
        self:: READ => 'read',
        self:: RECEIVED => 'received',

        self:: UNREAD_All => 'unReadAll',
        self:: UNREAD_FROM_COMMENTS => 'readFromComments',
        self:: UNREAD_MESSAGE => 'readMessage',

        self:: CUSTOMER_AUTH => 'customerAuth',
        self:: AUTO_REPLY_ROBOT => 'autoReplyRobot',
        self:: OPEN_CUSTOMER => 'openCustomer',
        self:: PUSH_CUSTOMER => 'pushCustomer',
        self:: PUSH_USER => 'pushUser',
        self:: PULL_CHAT_RECORD => 'pullChatRecord',
        self:: CLOSE_CUSTOMER => 'closeCustomer',
        self:: READ_CUSTOMER => 'readCustomer',
        self:: GET_REPLY_ROBOT => 'getReplyRobot',
        self:: ROBOT_TIMESTAMP => 'checkRobotTimeStamp',
        self:: CUSTOMER_HEART => 'customerHeart',

        self:: PUSH_OFFLINE_MSG => 'pushOffLineMsg',
        self:: PULL_OFFLINE_MSG => 'pullOfflineMsg',
        self:: READ_NUMBER_MSG => 'readNumberMsg',
        self:: READ_OFFLINE_MSG => 'readOffLineMsg',
        self:: READ_ONLINE_MSG => 'readOnLineMsg',
        self:: CHAT_RECEIVED => 'chatReceived',

        self:: IDENTITY_SET_AUTHOR => 'setAuthor',
        self:: IDENTITY_LOGOUT_AUTHOR => 'logoutAuthor',
        self::UNREAD_NOTIFICATION => 'clearReadNumber'
    ];

    public static function getMsgTypeName($msgType)
    {
        return self::$msgTypeNameDict[$msgType];
    }

    static $clientTypeNameDict = [
        self:: CLIENT_TYPE_PC => 'PcMessage',
        self:: CLIENT_TYPE_H5 => 'H5Message',
        self:: CLIENT_TYPE_IOS => 'IosMessage',
        self:: CLIENT_TYPE_ANDROID => 'AndroidMessage',
        self:: CLIENT_TYPE_CPADMIN => 'AdminMessage'
    ];

    public static function getClientControllerName($clientType)
    {
        return self::$clientTypeNameDict[$clientType];
    }
}
