<?php

namespace Xcs\Helper;

class Mail {

    public $debug = true;

    public $mail = array(
        'version' => '1.0',
        'maildelimiter' => 1,
        'sitename' => 'uvtodo.com',
        'from' => 'uvtodo.com<admin@uvtodo.com>',
        'server' => 'ssl://smtp.qq.com',
        'port' => 465,
        'mailsend' => 2,
        'auth' => true,
        'auth_username' => 'admin@uvtodo.com',
        'auth_password' => '12345678',
    );

    function send($email_to, $subject, $message) {
        $mail = $this->mail;
        $charset = 'UTF-8';
        $timeoffset = 8;
        $message = <<<EOT
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=$charset">
<title>$subject</title>
</head>
<body>
$message
</body>
</html>
EOT;

        $maildelimiter = 1 == $mail['maildelimiter'] ? "\r\n" : (2 == $mail['maildelimiter'] ? "\r" : "\n");
        $mail['port'] = $mail['port'] ? $mail['port'] : 25;
        $mail['mailsend'] = $mail['mailsend'] ? $mail['mailsend'] : 1;
        $email_from = $mail['from'];

        $email_subject = '=?' . $charset . '?B?' . base64_encode(preg_replace("/[\r|\n]/", '', '[' . $mail['sitename'] . '] ' . $subject)) . '?=';
        $email_message = chunk_split(base64_encode(str_replace("\n", "\r\n", str_replace("\r", "\n", str_replace("\r\n", "\n", str_replace("\n\r", "\r", $message))))));
        $host = $_SERVER['HTTP_HOST'];
        $version = $mail['version'];
        $headers = "From: $email_from{$maildelimiter}X-Priority: 3{$maildelimiter}X-Mailer: $host $version {$maildelimiter}MIME-Version: 1.0{$maildelimiter}Content-type: text/html; charset=" . $charset . "{$maildelimiter}Content-Transfer-Encoding: base64{$maildelimiter}";
        if ($mail['mailsend'] == 1) {
            if (function_exists('mail') && mail($email_to, $email_subject, $email_message, $headers)) {
                return true;
            }
            return false;

        } elseif ($mail['mailsend'] == 2) {

            if (!$fp = $this->fsocketopen($mail['server'], $mail['port'], $errno, $errstr, 30)) {
                $this->runlog('SMTP', "({$mail['server']}:{$mail['port']}) CONNECT - Unable to connect to the SMTP server", 0);
                return false;
            }
            stream_set_blocking($fp, true);

            $lastmessage = fgets($fp, 512);
            if (substr($lastmessage, 0, 3) != '220') {
                $this->runlog('SMTP', "{$mail['server']}:{$mail['port']} CONNECT - $lastmessage", 0);
                return false;
            }

            fputs($fp, ($mail['auth'] ? 'EHLO' : 'HELO') . " uchome\r\n");
            $lastmessage = fgets($fp, 512);
            if (substr($lastmessage, 0, 3) != 220 && substr($lastmessage, 0, 3) != 250) {
                $this->runlog('SMTP', "({$mail['server']}:{$mail['port']}) HELO/EHLO - $lastmessage", 0);
                return false;
            }

            while (1) {
                if (substr($lastmessage, 3, 1) != '-' || empty($lastmessage)) {
                    break;
                }
                $lastmessage = fgets($fp, 512);
            }

            if ($mail['auth']) {
                fputs($fp, "AUTH LOGIN\r\n");
                $lastmessage = fgets($fp, 512);
                if (substr($lastmessage, 0, 3) != 334) {
                    $this->runlog('SMTP', "({$mail['server']}:{$mail['port']}) AUTH LOGIN - $lastmessage", 0);
                    return false;
                }

                fputs($fp, base64_encode($mail['auth_username']) . "\r\n");
                $lastmessage = fgets($fp, 512);
                if (substr($lastmessage, 0, 3) != 334) {
                    $this->runlog('SMTP', "({$mail['server']}:{$mail['port']}) USERNAME - $lastmessage", 0);
                    return false;
                }

                fputs($fp, base64_encode($mail['auth_password']) . "\r\n");
                $lastmessage = fgets($fp, 512);
                if (substr($lastmessage, 0, 3) != 235) {
                    $this->runlog('SMTP', "({$mail['server']}:{$mail['port']}) PASSWORD - $lastmessage", 0);
                    return false;
                }

                $email_from = $mail['from'];
            }

            fputs($fp, "MAIL FROM: <" . preg_replace("/.*\<(.+?)\>.*/", "\\1", $email_from) . ">\r\n");
            $lastmessage = fgets($fp, 512);
            if (substr($lastmessage, 0, 3) != 250) {
                fputs($fp, "MAIL FROM: <" . preg_replace("/.*\<(.+?)\>.*/", "\\1", $email_from) . ">\r\n");
                $lastmessage = fgets($fp, 512);
                if (substr($lastmessage, 0, 3) != 250) {
                    $this->runlog('SMTP', "({$mail['server']}:{$mail['port']}) MAIL FROM - $lastmessage", 0);
                    return false;
                }
            }

            fputs($fp, "RCPT TO: <" . preg_replace("/.*\<(.+?)\>.*/", "\\1", $email_to) . ">\r\n");
            $lastmessage = fgets($fp, 512);
            if (substr($lastmessage, 0, 3) != 250) {
                fputs($fp, "RCPT TO: <" . preg_replace("/.*\<(.+?)\>.*/", "\\1", $email_to) . ">\r\n");
                $lastmessage = fgets($fp, 512);
                $this->runlog('SMTP', "({$mail['server']}:{$mail['port']}) RCPT TO - $lastmessage", 0);
                return false;
            }

            fputs($fp, "DATA\r\n");
            $lastmessage = fgets($fp, 512);
            if (substr($lastmessage, 0, 3) != 354) {
                $this->runlog('SMTP', "({$mail['server']}:{$mail['port']}) DATA - $lastmessage", 0);
                return false;
            }

            if (function_exists('date_default_timezone_set')) {
                @date_default_timezone_set('Etc/GMT' . ($timeoffset > 0 ? '-' : '+') . (abs($timeoffset)));
            }

            $headers .= 'Message-ID: <' . date('YmdHs') . '.' . substr(md5($email_message . microtime()), 0, 6) . rand(100000, 999999) . '@' . $_SERVER['HTTP_HOST'] . ">{$maildelimiter}";
            fputs($fp, "Date: " . date('r') . "\r\n");
            fputs($fp, "To: " . $email_to . "\r\n");
            fputs($fp, "Subject: " . $email_subject . "\r\n");
            fputs($fp, $headers . "\r\n");
            fputs($fp, "\r\n\r\n");
            fputs($fp, "$email_message\r\n.\r\n");
            $lastmessage = fgets($fp, 512);
            if (substr($lastmessage, 0, 3) != 250) {
                $this->runlog('SMTP', "({$mail['server']}:{$mail['port']}) END - $lastmessage", 0);
            }
            fputs($fp, "QUIT\r\n");

            return true;

        } elseif ($mail['mailsend'] == 3) {

            ini_set('SMTP', $mail['server']);
            ini_set('smtp_port', $mail['port']);
            ini_set('sendmail_from', $email_from);

            if (function_exists('mail') && mail($email_to, $email_subject, $email_message, $headers)) {
                return true;
            }
            return false;
        }
    }

    function fsocketopen($hostname, $port = 80, &$errno, &$errstr, $timeout = 15) {
        $fp = '';
        if (function_exists('fsockopen')) {
            $fp = fsockopen($hostname, $port, $errno, $errstr, $timeout);
        } elseif (function_exists('pfsockopen')) {
            $fp = pfsockopen($hostname, $port, $errno, $errstr, $timeout);
        } elseif (function_exists('stream_socket_client')) {
            $fp = stream_socket_client($hostname . ':' . $port, $errno, $errstr, $timeout);
        }
        return $fp;
    }

    function runlog($k1, $k2, $k3) {
        if ($this->debug) {
            echo $k1 . " ------" . $k2 . "-------" . $k3 . '<br>';
        }
    }

}
