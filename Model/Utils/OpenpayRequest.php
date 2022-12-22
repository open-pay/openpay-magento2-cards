<?php
namespace Openpay\Cards\Model\Utils;

class OpenpayRequest {
    
    protected $logger;
    /**
     * 
     * @param Context $context
     * @param OpenpayPayment $payment
     * @param  $logger_interface
     */
    public function __construct(
        \Openpay\Cards\Logger\Logger $logger
    ) {
        $this->logger = $logger;
    }
    
    public function make($path, $country, $is_sandbox, $method = 'GET', $data = null, $auth = null) {
        $country = strtolower($country);
        $url =  sprintf('https://api.openpay.%s/v1', $country);
        $sandbox_url = sprintf('https://sandbox-api.openpay.%s/v1', $country) ;
    
        $absUrl = $is_sandbox ? $sandbox_url : $url;
        $absUrl .= $path;
        $ch = curl_init();

        if (!empty($data) && $method == 'GET') {
            $info = http_build_query($data);
            $absUrl = $absUrl."?".$info;
        }

        if(!empty($data) && $method == 'POST'){
            $payload = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        }

        if(!empty($data) && $method == 'PUT'){
            $payload = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        }

        if ($auth != null) {
            curl_setopt($ch, CURLOPT_USERPWD, $auth['sk'].':'.'');
        }


        curl_setopt($ch, CURLOPT_URL, $absUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        $result = curl_exec($ch);
        $response = null;
        if ($result === false) {
            $this->logger->error("Curl error", array("curl_errno" => curl_errno($ch), "curl_error" => curl_error($ch)));
        } else {
            $info = curl_getinfo($ch);
            $response = json_decode($result);
            $response->http_code = $info['http_code'];
            $this->logger->debug("requestOpenpay", array("HTTP code " => $info['http_code'], "on request to" => $info['url']));
        }
    
        curl_close($ch);
        $this->logger->debug('#openpayrequest response', [json_encode($response)]);
        return $response;
    }
}