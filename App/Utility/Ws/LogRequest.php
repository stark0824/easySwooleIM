<?php
# +----------------------------------------------------------------------
# | Author:Stark
# +----------------------------------------------------------------------
# | Date:2020-11-12
# +----------------------------------------------------------------------
# | Desc: LogRequest 自定义Log类
# +----------------------------------------------------------------------

namespace App\Utility\Ws;

use \EasySwoole\EasySwoole\Logger;

class LogRequest
{

    protected $logName;
    protected $clientType;

    public function __construct(string $msgName, int $clientType)
    {
        $this->logName = $msgName;
        $this->clientType = $clientType;
    }

    public function request(array $body = [])
    {
        $body = array_filter($body);
        $logContents = '接收请求参数_';
        foreach ($body as $key => $value) {
            $logContents .= $key . ':' . $value . ',';
        }
        $logContents = rtrim($logContents, ',');
        Logger::getInstance()->log($logContents, Logger::LOG_LEVEL_INFO, Category::$logPrefix[$this->clientType] . $this->logName);
    }

    public function trackErrorLog(array $result)
    {
        $logContents = '请求返回_' . json_encode($result);
        Logger::getInstance()->log($logContents, Logger::LOG_LEVEL_INFO, Category::$logPrefix[$this->clientType] . $this->logName);
    }
}
