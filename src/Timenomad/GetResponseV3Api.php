<?php

namespace Timenomad;

/**
 * This is a read-only wrapper for the V3 API of GetResponse.
 * It providers wrappers for getting most of the resources.
 * Supports Basic & OAuth2 authentication.
 * Check out the GitHub repo for a full guide: https://github.com/timenomad/GetResponseV3Api
 *
 * Class GetResponseV3Api
 * @author George Kagan <george.kagan@gmail.com>
 * @package Timenomad
 */
class GetResponseV3Api {
    use OAuth2, Http;

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
     * @var string Token received after user's consent, used to renew an expired Access Token.
     */
    protected $refreshToken;

    /**
     * @var \Closure Anonymous function that gets called when an Access Token is renewed, this is where you save the new token to the database.
     */
    protected $accessTokenExpiryCallback;

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
}