<?php 
class MailNiaga_Command_Pool {
    private $commands = array();
    private $batch_size;
    private $smtp_sender;

    public function __construct() {
        $this->batch_size = get_option('mailniaga_smtp_batch_size', 25);
        $this->smtp_sender = new MailNiaga_SMTP_Sender();
    }

    public function add_command($email) {
        $this->commands[] = $email;
    }

    public function execute() {
        $final_results = [
            'successful' => 0,
            'failed' => 0,
            'total_time' => 0
        ];
    
        while (!empty($this->commands)) {
            $batch = array_splice($this->commands, 0, $this->batch_size);
            $batch_results = $this->smtp_sender->send_emails($batch);
            
            $final_results['successful'] += $batch_results['successful'];
            $final_results['failed'] += $batch_results['failed'];
            $final_results['total_time'] += $batch_results['total_time'];
        }
    
        return $final_results;
    }
}

function mailniaga_smtp_send_email_by_id($email_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mailniaga_email_queue';
    
    $email = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $email_id));
    
    if ($email) {
        $max_retries = 3;
        $result = false;
        
        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            $result = mailniaga_smtp_send_email($email->to_email, $email->subject, $email->message, unserialize($email->headers), unserialize($email->attachments));
            
            if ($result) {
                break;
            }
            
            sleep(5); // Wait 5 seconds before retrying
        }
        
        if ($result) {
            $wpdb->update($table_name, array('status' => 'sent'), array('id' => $email_id));
        } else {
            $wpdb->update($table_name, array('status' => 'failed'), array('id' => $email_id));
        }
    }
}