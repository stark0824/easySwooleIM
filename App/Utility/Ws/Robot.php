<?php
/**
 * 配置常量
 * User: stark
 * Date: 2020-11-11
 * Time: 17:04
 *
 */

namespace App\Utility\Ws;

class Robot
{

    static $robotArray = [
        'Q1' => [
            'category' => 1,
            'title' => 'IOS充值不到账怎么办？',
            'answer' => "您好，充值后不支持退款。由于网络环境原因，苹果商店会出现小概率延迟到账的情况，在48小时内到账都属于正常情况，请您耐心等待。如果您使用苹果系统设备，建议您先尝试退出Apple ID和当前登录的长佩账号，重新登录之后，查看您充值的玉佩是否到账。
如您已经支付成功，支付成功后相关服务都是由苹果商家独立提供的，长佩方无法直接介入，如您对这笔订单有退款或其他等问题，请您联系苹果客服进行处理，苹果客服电话：400-666-8800。"
        ],
    ];

    public static function getRobotArray(string $qNumber): string
    {
        $qNumber = trim($qNumber);
        $answer = '';
        if (empty($qNumber)) {
            $answer = '抱歉，鱼塘里找不到你的答案呢~';
        } else {
            $data = Robot::$robotArray[$qNumber];
            if (!empty($data['answer'])) {
                $answer = $data['answer'];
            }
        }

        return $answer;


    }
}
