<?php

namespace Timenomad;

/**
 *
 *
 * Class GetResponseV3Api
 * @author George Kagan <george.kagan@gmail.com>
 * @package Timenomad
 */
class GetResponseV3Api {
    const CONSENT_URL = 'https://app.getresponse.com/oauth2_authorize.html?response_type=code&client_id={{clientId}}&state={{state}}';
    const API_URL     = 'https://api.getresponse.com/v3';

    /**
     * @var string Your application's Client ID
     */
    protected $clientId;
    /**
     * @var string Your application's Client Secret
     */
    protected $clientSecret;
    /**
     * @var string Random characters to verify callback is coming from GetResponse, should be generated once.
     */
    protected $state;
    /**
     * @var string Token received after user's consent, used to authorize API calls for the user.
     */
    protected $accessToken;

    /**
     * GetResponseV3Api constructor.
     * @param $clientId
     * @param $clientSecret
     * @param $state
     */
    public function __construct($clientId, $clientSecret, $state) {
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
        $this->state        = $state;
    }

    //
    // OAuth2 methods
    //

    /**
     * Return the url the authenticating user needs to be redirected to (or presented via a popup) in order to
     * obtain an Access Token, which in turn will be used to grant access to his data.
     *
     * @return mixed
     */
    public function getOAuthConsentUrl() {
        $url = str_replace(['{{clientId}}', '{{state}}'], [$this->clientId, $this->state], self::CONSENT_URL);

        return $url;
    }

    /**
     * Return OAuth2 Access Token, Refresh Token and related data as received from GetResponse.
     * Expects `code` and `state` params to be in the $_GET global array.
     * This method needs to be called when the Redirect URL is called. (Defined via GetResponse's admin panel.)
     *
     * @return mixed
     */
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

    /**
     * Set Access Token as received from GetResponse or fetched from a local database.
     * Without calling this function after initializing the object, you won't have access to that user's data.
     *
     * @param $accessToken
     */
    public function setAccessToken($accessToken) {
        if (empty($accessToken)) {
            trigger_error('No Access Token.', E_USER_ERROR);
        }
        $this->accessToken = $accessToken;
    }

    //
    // GetResponse resources
    //

    /**
     * Returns authenticated user's account info
     *
     * @return mixed
     */
    public function getAccountInfo() {
        $response = $this->doHttpRequest(self::API_URL . '/accounts');

        return $response;
    }

    /**
     * Returns authenticated user's campaigns
     *
     * @return mixed
     */
    public function getCampaigns() {
        $response = $this->doHttpRequest(self::API_URL . '/campaigns');

        return $response;
    }

    /**
     * Returns authenticated user's campaign statistics by date, such as subscribers, locations, balance, ...
     * Minimal method signature is: getCampaignStatistics($campaignId, $metric)
     *
     * @param string $campaignId
     * @param string $metric One of: list-size, locations, origins, removals, subscriptions, balance, summary
     * @param string $groupBy One of: hour, day, month, total
     * @param string $fromTime Date or unix timestamp
     * @param string $toTime Date or unix timestamp
     * @param string $fields
     * @return mixed
     */
    public function getCampaignStatistics($campaignId, $metric, $groupBy = 'day', $fromTime = null, $toTime = null, $fields = null) {
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

    /**
     * Generic method to send HTTP requests to GetResponse's V3 API.
     *
     * @param string $url The full url to a GetResponse endpoint
     * @param bool $isPost Whether this is a POST or a GET request
     * @param array $payload The data to be sent as url params (GET) or http headers (POST)
     * @return mixed
     */
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