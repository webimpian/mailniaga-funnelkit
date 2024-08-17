<?php
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Exception\RequestException;

class MailNiaga_SMTP_Sender {
    private $client;
    private $api_key;
    private $api_url;

    public function __construct() {
        $this->api_key = get_option('mailniaga_smtp_api_key');
        $this->api_url = 'https://api.mailniaga.mx/api/v0/messages';
        $pool_size = get_option('mailniaga_smtp_connection_pool_size', 25);

        $this->client = new Client([
            'timeout' => 30,
            'connect_timeout' => 5,
            'pool_size' => $pool_size,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Api-Key' => $this->api_key
            ]
        ]);
    }

    public function send_emails($emails) {
        $overall_start = microtime(true);
        $promises = [];
        $timings = [];
    
        foreach ($emails as $index => $email) {
            $timings[$index] = ['start' => microtime(true)];
            $prepared_data = $this->prepare_email_data($email);
            $promises[$index] = $this->client->postAsync($this->api_url, [
                'json' => $prepared_data
            ])->then(
                function ($response) use ($index, &$timings, $prepared_data) {
                    $timings[$index]['end'] = microtime(true);
                    return ['index' => $index, 'response' => $response, 'data' => $prepared_data];
                },
                function ($exception) use ($index, &$timings, $prepared_data) {
                    $timings[$index]['end'] = microtime(true);
                    return ['index' => $index, 'exception' => $exception, 'data' => $prepared_data];
                }
            );
        }
    
        $results = Promise\Utils::settle($promises)->wait();
    
        $successful = 0;
        $failed = 0;
    
        foreach ($results as $i => $result) {
            $email = $emails[$i];
            $timing = $timings[$i];
            $processing_time = round($timing['end'] - $timing['start'], 4);
    
            try {
                if ($result['state'] === 'fulfilled') {
                    if (isset($result['value']['response'])) {
                        $response = $result['value']['response'];
                        $prepared_data = $result['value']['data'];
                        $response_code = $response->getStatusCode();
                        $response_body = json_decode($response->getBody(), true);
    
                        if ($response_code === 200 && isset($response_body['data']['total']) && $response_body['data']['total'] > 0) {
                            $successful++;
                            $this->log_success($prepared_data, $response_code, $processing_time);
                        } else {
                            $failed++;
                            $this->log_error($prepared_data, $response_code, $processing_time, $response_body['error'] ?? 'Unknown error');
                        }
                    } else {
                        $failed++;
                        $this->log_error($email, 0, $processing_time, 'Response not found in result');
                    }
                } else {
                    $failed++;
                    $exception = $result['reason'] ?? new Exception('Unknown error');
                    $this->log_error($email, 0, $processing_time, $exception->getMessage());
                }
            } catch (Exception $e) {
                $failed++;
                $this->log_error($email, 0, $processing_time, 'Exception: ' . $e->getMessage());
            }
        }
    
        $overall_time = round(microtime(true) - $overall_start, 4);
        $this->log_batch_summary($successful, $failed, $overall_time);
    
        return [
            'successful' => $successful,
            'failed' => $failed,
            'total_time' => $overall_time
        ];
    }

    private function prepare_email_data($email) {
        $email_data = [
            'to' => is_array($email['to']) ? $email['to'] : [$email['to']],
            'subject' => $email['subject'],
            'content' => $email['message'],
            'as_html' => 1,
        ];

        // Handle the 'from' field
        $from_email = $email['from'];
        $from_name = $email['from_name'] || get_bloginfo('name'); // Default sender name

        // Check if 'From' header is set
        if (isset($email['headers']) && is_array($email['headers'])) {
            foreach ($email['headers'] as $header) {
                if (strpos(strtolower($header), 'from:') === 0) {
                    $from_parts = explode(':', $header, 2);
                    $from = trim($from_parts[1]);
                    // Extract name and email if both are present
                    if (preg_match('/(.*)<(.+)>/', $from, $matches)) {
                        $from_name = trim($matches[1]);
                        $from_email = trim($matches[2]);
                    } else {
                        $from_email = trim($from);
                    }
                    break;
                }
            }
        }

        $email_data['from'] = $from_name ? "$from_name <$from_email>" : $from_email;

        // Parse other headers
        $headers = $email['headers'] ?? [];
        if (!is_array($headers)) {
            $headers = explode("\n", str_replace("\r\n", "\n", $headers));
        }

        foreach ($headers as $header) {
            if (strpos($header, ':') === false) {
                continue;
            }
            list($name, $content) = explode(':', trim($header), 2);
            $name = trim($name);
            $content = trim($content);

            switch (strtolower($name)) {
                case 'cc':
                    $email_data['cc'] = explode(',', $content);
                    break;
                case 'bcc':
                    $email_data['bcc'] = explode(',', $content);
                    break;
            }
        }
        //error_log("email data in mail sender is: " . print_r($email_data, true));
        return $email_data;
    }

    private function log_success($email_data, $response_code, $processing_time) {
        $to_addresses = implode(', ', $email_data['to']);
        mailniaga_smtp_log_success(
            "Email sent via API to: $to_addresses, Subject: {$email_data['subject']}\n" .
            "Response Code: $response_code, Processing Time: {$processing_time}s",
            $processing_time
        );
    }

    private function log_error($email_data, $response_code, $processing_time, $error_message) {
        $to_addresses = implode(', ', $email_data['to']);
        mailniaga_smtp_log_error(
            "Failed to send email to: $to_addresses, Subject: {$email_data['subject']}",
            $response_code,
            "Error: $error_message, Processing Time: {$processing_time}s"
        );
    }

    private function log_batch_summary($successful, $failed, $overall_time) {
        $total = $successful + $failed;
        $message = "Batch Summary: Sent $successful/$total emails successfully in {$overall_time}s";
        if ($failed > 0) {
            $message .= ", Failed: $failed";
        }
        mailniaga_smtp_log_success($message, $overall_time);
    }
}