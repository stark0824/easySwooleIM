<?php# +----------------------------------------------------------------------# | Author:Stark# +----------------------------------------------------------------------# | Date:2020-11-03# +----------------------------------------------------------------------# | Desc: 邮件发送类# +----------------------------------------------------------------------namespace App\Utility\Mall;use PHPMailer\PHPMailer\PHPMailer;use PHPMailer\PHPMailer\SMTP;use EasySwoole\EasySwoole\Config as GlobalConfig;class SendMall {    public static function send ( $msg = '',$toMall ='877098707@qq.com') {        $mallConf = GlobalConfig::getInstance()->getConf('mall');        //Server settings        $mail = new PHPMailer(true);        $mail->CharSet = $mallConf['mall']['charset'];        $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output        $mail->isSMTP();                                            // Send using SMTP        $mail->Host       = $mallConf['mall']['host'];                    // Set the SMTP server to send through        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication        $mail->Username   = $mallConf['mall']['username'];                     // SMTP username        $mail->Password   = $mallConf['mall']['password'];                              // SMTP password        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged        $mail->Port       = $mallConf['mall']['prot'];                                     // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above        //Recipients        $mail->setFrom($mallConf['mall']['setfrom'], $mallConf['mall']['setfromname']);        $mail->addAddress($toMall, 'Developer');     // Add a recipient        $mail->addReplyTo($mallConf['mall']['replyto'], 'CpMsgAdmin');        // Content        $mail->isHTML(true);                                  // Set email format to HTML        $mail->Subject = 'Msg报警邮件-'.date('Y-m-d');        $mail->Body    = date('Y-m-d H:i:s').' - Api接口捕获异常，错误信息：'.PHP_EOL;        $mail->Body    .= '<b><span style="color: darkred;">'.$msg.'</span></b>'.PHP_EOL;        $mail->Body    .= ',请火速处理。';        //$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';        $mail->send();        echo 'Message has been sent';    }}