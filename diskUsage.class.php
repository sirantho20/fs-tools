<?php
/**
 * User: tony
 * Date: 2/18/15
 * Time: 12:14 PM
 */

class diskUsage {
    public $total;
    public $used;
    public $available;
    public $mysql;
    public $mail;
    public $log;
    public $application;
    public $date;

    public $mysql_path = '/var/lib/mysql';
    public $mail_path = '/var/vmail';
    public $log_path = '/var/log';
    public $application_path = '/usr/share';
    public $config;
    public $header = ['date','total', 'used', 'available', 'mysql', 'email', 'log', 'application'];

    public function __construct()
    {
        $this->config = require_once 'config.php';
    }
    public function update()
    {
        $output = shell_exec('df /');
        $array  =   explode("\n", $output);
        array_pop($array);
        array_shift($array);
        $val = preg_split('/\s+/',$array[0]);
        array_pop($val);
        array_pop($val);
        array_shift($val);
        array_push($val, date('Y-m-d H:i:s'));

        $this->total = $val[0];
        $this->used = $val[1];
        $this->available = $val[2];
        $this->date = $val[3];
        $this->mysql = $this->getPathSize($this->mysql_path);
        $this->mail = $this->getPathSize($this->mail_path);
        $this->log = $this->getPathSize($this->log_path);
        $this->application = $this->getPathSize($this->application_path);
        $this->csvDump();
    }
    public function getPathSize($path)
    {
        $loc = 'du -s %s';
        $output = shell_exec(sprintf($loc, $path));
        $val = preg_split('/\s+/',$output);
        array_pop($val); array_pop($val);
        return $val[0];

    }

    public function csvDump()
    {
        $file = fopen($this->config['file_location'].'dusage.csv','a');
        fputcsv($file, [$this->date, $this->total, $this->used, $this->available, $this->mysql, $this->mail, $this->log, $this->application]);
        fclose($file);
    }

    public function addCSvHeader()
    {
        $file = fopen($this->config['file_location'].'dusage.csv','r+');
        fputcsv($file, $this->header);
        fclose($file);
    }

    public function mail()
    {
        $this->update();
        $this->addCSvHeader();
        require 'vendor/autoload.php';

        //Create a new PHPMailer instance
        $mail = new PHPMailer;

        //Tell PHPMailer to use SMTP
        $mail->isSMTP();

        //Enable SMTP debugging
        // 0 = off (for production use)
        // 1 = client messages
        // 2 = client and server messages
        $mail->SMTPDebug = 0;

        //Ask for HTML-friendly debug output
        $mail->Debugoutput = 'html';

        //Set the hostname of the mail server
        $mail->Host = $this->config['smtp_host'];

        //Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
        $mail->Port = $this->config['smtp_port'];

        //Set the encryption system to use - ssl (deprecated) or tls
        $mail->SMTPSecure = 'tls';

        //Whether to use SMTP authentication
        $mail->SMTPAuth = true;

        //Username to use for SMTP authentication - use full email address for gmail
        $mail->Username = $this->config['smtp_user'];

        //Password to use for SMTP authentication
        $mail->Password = $this->config['smtp_password'];

        //Set who the message is to be sent from
        $mail->setFrom($this->config['smtp_user']);

        //Set an alternative reply-to address
        //$mail->addReplyTo('replyto@example.com', 'First Last');

        //Set who the message is to be sent to
        //$mail->addCC($this->config['email_to']);
        $mail->addAddress($this->config['email_to']);

        //Set the subject line
        $mail->Subject = 'Disk Usage Update - '.gethostname();

        // HTML email body
        $mail->msgHTML('Hello,<p>Please find disk usage update attached.</p>');

        //Replace the plain text body with one created manually
        //$mail->AltBody = 'This is a plain-text message body';

        //Attach a file
        $mail->addAttachment($this->gzCompressFile($this->config['file_location'].'dusage.csv'));

        //send the message, check for errors
        if (!$mail->send()) {
           // echo "Mailer Error: " . $mail->ErrorInfo;

        } else {
            //echo "Message sent!";
            unlink('dusage.csv.gz');
            //unlink('dusage.csv');
        }
    }

    function gzCompressFile($source, $level = 9)
    {
        $dest = $source . '.gz';
        $mode = 'wb' . $level;
        $error = false;
        if ($fp_out = gzopen($dest, $mode)) {
            if ($fp_in = fopen($source,'rb')) {
                while (!feof($fp_in))
                    gzwrite($fp_out, fread($fp_in, 1024 * 512));
                fclose($fp_in);
            } else {
                $error = true;
            }
            gzclose($fp_out);
        } else {
            $error = true;
        }
        if ($error)
            return false;
        else
            return $dest;
    }

    public function test()
    {
        print_r($this->config['email_to']);
    }


}