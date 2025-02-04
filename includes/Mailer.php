<?php
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';
require_once 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mail;
    private $config;
    
    public function __construct($config) {
        // 验证必要的配置项
        $required_fields = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from'];
        foreach ($required_fields as $field) {
            if (empty($config[$field])) {
                throw new Exception("SMTP配置错误：{$field} 不能为空");
            }
        }

        // 验证邮箱格式
        if (!filter_var($config['smtp_from'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("SMTP配置错误：发件人邮箱格式无效");
        }

        $this->config = $config;
        $this->mail = new PHPMailer(true);
        
        // 服务器配置
        $this->mail->isSMTP();
        $this->mail->Host = $config['smtp_host'];
        $this->mail->SMTPAuth = true;
        $this->mail->Username = $config['smtp_user'];
        $this->mail->Password = $config['smtp_pass'];
        
        // 根据端口自动选择加密方式
        if ($config['smtp_port'] == 465) {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($config['smtp_port'] == 587) {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $this->mail->SMTPSecure = '';
        }
        
        $this->mail->Port = $config['smtp_port'];
        $this->mail->CharSet = 'UTF-8';
        
        // 调试模式
        if (!empty($config['debug'])) {
            $this->mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $this->mail->Debugoutput = function($str, $level) {
                error_log("SMTP Debug: $str");
            };
        }
        
        // 设置默认发件人
        $from_name = !empty($config['smtp_from_name']) ? $config['smtp_from_name'] : '';
        $this->mail->setFrom($config['smtp_from'], $from_name);
    }
    
    /**
     * 发送邮件
     * @param string|array $to 收件人邮箱，可以是字符串或数组
     * @param string $subject 邮件主题
     * @param string $body 邮件内容
     * @param array $options 其他选项
     * @return array ['success' => bool, 'message' => string]
     */
    public function send($to, $subject, $body, $options = []) {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            
            // 添加收件人
            if (is_array($to)) {
                foreach ($to as $address) {
                    $this->mail->addAddress($address);
                }
            } else {
                $this->mail->addAddress($to);
            }
            
            // 设置邮件内容
            $this->mail->isHTML($options['isHTML'] ?? true);
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            
            // 如果有纯文本版本
            if (isset($options['altBody'])) {
                $this->mail->AltBody = $options['altBody'];
            }
            
            // 添加附件
            if (isset($options['attachments'])) {
                foreach ($options['attachments'] as $attachment) {
                    $this->mail->addAttachment(
                        $attachment['path'],
                        $attachment['name'] ?? ''
                    );
                }
            }
            
            // 发送邮件
            $this->mail->send();
            return ['success' => true, 'message' => '邮件发送成功'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $this->mail->ErrorInfo];
        }
    }
    
    /**
     * 发送测试邮件
     * @param string $to 测试邮箱地址
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendTest($to) {
        // 检查配置是否完整
        if (empty($this->config['smtp_host']) || empty($this->config['smtp_from'])) {
            return ['success' => false, 'message' => '邮箱配置不完整，请先完成配置'];
        }

        $subject = '邮箱配置测试';
        $body = <<<HTML
        <div style="padding: 20px; background-color: #f8f9fa; border-radius: 5px;">
            <h2 style="color: #4338ca;">邮箱配置测试</h2>
            <p>这是一封测试邮件，如果您收到这封邮件，说明邮箱配置正确。</p>
            <p>发送时间：{$this->getFormattedTime()}</p>
            <hr style="border: 1px solid #e5e7eb;">
            <p style="color: #6b7280; font-size: 14px;">
                服务器信息：<br>
                SMTP服务器：{$this->config['smtp_host']}<br>
                端口：{$this->config['smtp_port']}<br>
                发件人：{$this->config['smtp_from']}<br>
                发件人名称：{$this->config['smtp_from_name']}
            </p>
        </div>
        HTML;
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * 获取格式化的时间
     * @return string
     */
    private function getFormattedTime() {
        return date('Y年m月d日 H:i:s');
    }
}

if(isset($_POST['test_smtp'])) {
    $smtp_config = $conn->query("SELECT * FROM smtp_config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    error_log("SMTP Config: " . print_r($smtp_config, true));
    // ... 其他代码
}