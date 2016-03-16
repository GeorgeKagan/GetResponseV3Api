<?php

namespace Timenomad;

class GetResponseV3Api {
    const CONSENT_URL = 'https://app.getresponse.com/oauth2_authorize.html?response_type=code&client_id={{clientId}}&state={{state}}';
    const API_URL     = 'https://api.getresponse.com/v3';

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
        $response = $this->doHttpRequest(self::API_URL . '/token', true, [
            "grant_type" => "authorization_code",
            "code"       => $_GET['code']
        ]);

        return $response;
    }

    public function setAccessToken($accessToken) {
        if (empty($accessToken)) {
            trigger_error('No Access Token.', E_USER_ERROR);
        }
        $this->accessToken = $accessToken;
    }

    //
    // Data
    //

    public function getAccountInfo() {
        $response = $this->doHttpRequest(self::API_URL . '/accounts');

        return $response;
    }

    public function getCampaigns() {
        $response = $this->doHttpRequest(self::API_URL . '/campaigns');

        return $response;
    }

    /**
     * @param string $campaignId
     * @param string $metric One of: list-size, locations, origins, removals, subscriptions, balance, summary
     * @param string $groupBy One of: hour, day, month, total
     * @param string $fromTime Date or unix timestamp
     * @param string $toTime Date or unix timestamp
     * @param string $fields
     * @return mixed
     */
    public function getMessageStatistics($campaignId, $metric, $groupBy = 'day', $fromTime = null, $toTime = null, $fields = null) {
        if (empty($campaignId)) {
            trigger_error('Campaign ID is missing.', E_USER_ERROR);
        }
        $payload = [
            'query[campaignId]' => $campaignId,
            'query[groupBy]' => $groupBy,
        ];
        if ($fromTime && $toTime) {
            $payload['query[createdOn][from]'] = strftime('%Y-%m-%d', strtotime($fromTime) ? strtotime($fromTime) : $fromTime);
            $payload['query[createdOn][to]'] = strftime('%Y-%m-%d', strtotime($toTime) ? strtotime($toTime) : $toTime);
        }

        if (!empty($fields)) {
            $payload['fields'] = $fields;
        }
        $response = $this->doHttpRequest(self::API_URL . '/campaigns/statistics/' . $metric, false, $payload);

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

        // if GET and got payload - send as query params
        if (!$isPost && !empty($payload)) {
            $url .= '?' . http_build_query($payload);
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

        // If POST and got payload - send as POST data
        if ($isPost && !empty($payload)) {
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