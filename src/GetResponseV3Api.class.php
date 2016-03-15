<?php

namespace Timenomad;

class GetResponseV3Api {
    const CONSENT_URL      = 'https://app.getresponse.com/oauth2_authorize.html?response_type=code&client_id={{clientId}}&state={{state}}';
    const ACCESS_TOKEN_URL = 'https://api.getresponse.com/v3/token';

    protected $clientId;
    protected $clientSecret;
    protected $state;
    protected $accessToken;

    public function __construct($clientId, $clientSecret, $state) {
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
        $this->state        = $state;
    }

    //
    // Auth
    //

    public function getOAuthConsentUrl() {
        $url = str_replace(['{{clientId}}', '{{state}}'], [$this->clientId, $this->state], self::CONSENT_URL);

        return $url;
    }

    public function getAccessToken() {
        if (empty($_GET['code'])) {
            trigger_error('No "code" GET parameter.', E_USER_ERROR);
        }
        if (empty($_GET['state']) || $_GET['state'] !== $this->state) {
            trigger_error('No "state" GET parameter or returned value doesn\'t match the stored one.', E_USER_ERROR);
        }
        $response = $this->doHttpRequest(self::ACCESS_TOKEN_URL, true, [
            "grant_type" => "authorization_code",
            "code"       => $_GET['code']
        ]);

        return $response;
    }

    public function setAccessToken($accessToken) {
        $this->accessToken = $accessToken;
    }

    //
    // Data
    //

    public function getAccountInfo() {
        if (empty($this->accessToken)) {
            trigger_error('No Access Token.', E_USER_ERROR);
        }
        $response = $this->doHttpRequest('https://api.getresponse.com/v3/accounts');

        return $response;
    }

    //
    // Utils
    //

    private function doHttpRequest($url, $isPost = false, $payload = []) {
        if ($this->accessToken) {
            $authHeader = "Authorization: Bearer {$this->accessToken}";
        } else {
            $authHeader = "Authorization: Basic " . base64_encode("{$this->clientId}:{$this->clientSecret}");
        }
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => [
                $authHeader,
                "Content-Type: application/json"
            ],
            CURLOPT_POST           => $isPost,
            CURLOPT_RETURNTRANSFER => true
        ]);
        if (!empty($payload)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
        $response = json_decode(curl_exec($ch), true);

        // GetResponse returned an error
        if (isset($response['code']) && isset($response['message'])) {
            echo '<pre>';
            var_dump($response);
            exit;
        }

        return $response;
    }
}