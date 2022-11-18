<?php
# +----------------------------------------------------------------------
# | Author:Stark
# +----------------------------------------------------------------------
# | Date:2022/11/03
# +----------------------------------------------------------------------
# | Desc: EasySwooleEvent 核心对象
# +----------------------------------------------------------------------

namespace EasySwoole\EasySwoole;
use App\Parser\WebSocketParser;
use App\WebSocketEvent;
use EasySwoole\Command\CommandManager;
use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\Socket\Bean\Response;
use EasySwoole\Socket\Client\WebSocket;
use EasySwoole\Socket\Dispatcher;
use EasySwoole\ORM\Db\Config as DBConfig;
use EasySwoole\ORM\Db\Connection;
use EasySwoole\ORM\DbManager;
use EasySwoole\EasySwoole\Config as GlobalConfig;
use EasySwoole\Utility\File;
use App\Utility\EventNotify;
use Swoole\Coroutine\Scheduler;
use EasySwoole\EasySwoole\Crontab\Crontab;

class EasySwooleEvent implements Event
{
    const PUSH_MSG_USER_LOGIN = 'PUSH_MSG_USER_LOGIN';
    const PUSH_MSG_SOCKET_FD = 'PUSH_MSG_SOCKET_FD';

    public static function initialize()
    {
        date_default_timezone_set('Asia/Shanghai');

        //载入配置文件
        // 载入项目 Conf 文件夹中所有的配置文件
        self::loadConf();
        $dbConf = GlobalConfig::getInstance()->getConf('db');

        //消息库
        $config_msg = new DBConfig();
        $config_msg->setDatabase($dbConf['mysql-msg']['database']);
        $config_msg->setUser($dbConf['mysql-msg']['username']);
        $config_msg->setPassword($dbConf['mysql-msg']['password']);
        $config_msg->setHost($dbConf['mysql-msg']['host']);
        $config_msg->setPort($dbConf['mysql-msg']['port']);
        $config_msg->setCharset($dbConf['mysql-msg']['charset']);
        $config_msg->setReturnCollection(true);
        //Mysql连接池
        $config_msg->setGetObjectTimeout($dbConf['conn_pool']['timeOut']); //设置获取连接池对象超时时间
        $config_msg->setIntervalCheckTime($dbConf['conn_pool']['checkOut']); //设置检测连接存活执行回收和创建的周期
        $config_msg->setMaxIdleTime($dbConf['conn_pool']['maxidleTime']); //连接池对象最大闲置时间(秒)
        $config_msg->setMaxObjectNum($dbConf['conn_pool']['maxObjectNumber']); //设置最大连接池存在连接对象数量
        $config_msg->setMinObjectNum($dbConf['conn_pool']['minObjectNumber']); //设置最小连接池存在连接对象数量
        $config_msg->setAutoPing($dbConf['conn_pool']['autoPing']); //设置自动ping客户端链接的间隔
        DbManager::getInstance()->addConnection(new Connection($config_msg),'mysql-msg');

        //自定义日志
        \EasySwoole\EasySwoole\Logger::getInstance(new \App\Log\LogHandel());

    }

    /**
     * 加载自定义配置文件
     */
    public static function loadConf()
    {
        if(0 == PUSHENV){
            $ConfPath = EASYSWOOLE_ROOT . '/App/Conf/Dev';
        }else if(1 == PUSHENV){
            $ConfPath = EASYSWOOLE_ROOT . '/App/Conf/Test';
        }else if(2 == PUSHENV){
            $ConfPath = EASYSWOOLE_ROOT . '/App/Conf/Online';
        }
        $Conf  = Config::getInstance();
        $files = File::scanDirectory($ConfPath);
        if (!is_array($files['files'])) {
            return;
        }
        foreach ($files['files'] as $file) {
            $data = require_once $file;
            $Conf->setConf(strtolower(basename($file, '.php')), (array)$data);
        }
    }

    public static function mainServerCreate(EventRegister $register)
    {
        // websocket
        if (CommandManager::getInstance()->getOpt('mode') === 'websocket') {
            $config = new \EasySwoole\Socket\Config();
            $config->setType($config::WEB_SOCKET);
            $config->setParser(WebSocketParser::class);
            $dispatcher = new Dispatcher($config);
            $config->setOnExceptionHandler(function (\Swoole\Server $server, \Throwable $throwable, string $raw, WebSocket $client, Response $response) {
                $message  = $throwable->getMessage();
                $file = $throwable->getFile();
                $line = $throwable->getLine();
                $message = $message.',文件位置:'.$file.'第'.$line.'行'.PHP_EOL;
                \EasySwoole\EasySwoole\Logger::getInstance()->log($message ,\EasySwoole\EasySwoole\Logger::LOG_LEVEL_ERROR,'Error');
                $response->setMessage(json_encode(['code' => 504, 'msg'  => 'Server Error' ]));
                $response->setStatus($response::STATUS_RESPONSE_AND_CLOSE);
            });

            // 自定义握手
            $websocketEvent = new WebSocketEvent();
            /* $register->set(EventRegister::onHandShake, function (\Swoole\Http\Request $request, \Swoole\Http\Response $response) use ($websocketEvent) {
                $websocketEvent->onHandShake($request, $response);
            });*/

            $register->set($register::onMessage, function (\Swoole\Websocket\Server $server, \Swoole\Websocket\Frame $frame) use ($dispatcher) {
                $dispatcher->dispatch($server, $frame->data, $frame);
            });

            //自定义关闭事件
            $register->set(EventRegister::onClose, function (\swoole_server $server, int $fd, int $reactorId) use ($websocketEvent) {
                $websocketEvent->onClose($server, $fd, $reactorId);
            });

            //自定义链接事件
            $register->set(EventRegister::onOpen, function (\swoole_server $server, \swoole_http_request $request ) use ($websocketEvent) {
                $websocketEvent->onOpen($server, $request);
            });

            //自定义退出进程事件
            $register->set(EventRegister::onWorkerExit, function (\swoole_server $server, $worker_id ) use ($websocketEvent) {
                //...
            });

            $register->set(EventRegister::onWorkerStop,function (\swoole_server $server, $worker_id ) use ($websocketEvent) {
                     // echo   'onWorkerStop:worker_id:'.$worker_id.PHP_EOL;
            });

            $register->set(EventRegister::onWorkerStart,function (\swoole_server $server, $worker_id ) use ($websocketEvent) {
                      //echo   'onWorkerStart:worker_id:'.$worker_id.PHP_EOL;
            });

            $register->set(EventRegister::onShutdown,function (\swoole_server $server ) use ($websocketEvent) {
                $websocketEvent->onShutdown($server);
            });

            //注册 redis连接池
            \EasySwoole\RedisPool\RedisPool::getInstance()->register(new \EasySwoole\Redis\Config\RedisConfig( GlobalConfig::getInstance()->getConf('redis') ),'redis');


            /******************* Crontab任务计划 ***********************/
            //用户通知队列
            Crontab::getInstance()->addTask(\App\Crontab\PushUserNoticeMsg::class);
            //用户通知延时队列
            Crontab::getInstance()->addTask(\App\Crontab\PushUserNoticeDelayMsg::class);
            //评论队列
            Crontab::getInstance()->addTask(\App\Crontab\PushCommentMsg::class);
            //评论等待队列
            Crontab::getInstance()->addTask(\App\Crontab\PushCommentDelayMsg::class);
            //全局通知队列
            Crontab::getInstance()->addTask(\App\Crontab\PushSystemNoticeMsg::class);
            //推送未读消息数
            Crontab::getInstance()->addTask(\App\Crontab\PushUnreadMsg::class);
            //全员作者消息推送
            Crontab::getInstance()->addTask(\App\Crontab\PushAllAuthorNoticeMsg::class);
            //签约作者消息推送
            Crontab::getInstance()->addTask(\App\Crontab\PushSignAuthorNoticeMsg::class);

        }
        // 配置同上别忘了添加要检视的目录
        $hotReloadOptions = new \EasySwoole\HotReload\HotReloadOptions;
        $hotReload = new \EasySwoole\HotReload\HotReload($hotReloadOptions);
        $hotReloadOptions->setMonitorFolder([EASYSWOOLE_ROOT . '/App']);
        $server = ServerManager::getInstance()->getSwooleServer();
        $hotReload->attachToServer($server);
    }
}
