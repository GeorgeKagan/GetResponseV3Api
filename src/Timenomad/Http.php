<?php

namespace Timenomad;

/**
 * Handles the actual HTTP call to GetResponse's API.
 *
 * Class Http
 * @package Timenomad
 */
trait Http {
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
        $fullUrl = $url;

        // if GET and got payload - send as query params
        if (!$isPost && !empty($payload)) {
            $fullUrl .= '?' . http_build_query($payload);
        }
        $ch = curl_init($fullUrl);

        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => [
                $authHeader,
                'Content-Type: application/json'
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
            // If Access Token expired, use Refresh Token to generate a new one
            if ((int)$response['code'] === 1014) {
                if (!$this->accessTokenExpiryCallback || !$this->refreshToken) {
                    trigger_error('No callback for Access Token expiry and/or Refresh Token defined, cannot renew Access Token.', E_USER_ERROR);
                }
                // Automatically renew token and call user's callback function
                $newToken = $this->renewAccessToken();
                $this->accessTokenExpiryCallback->__invoke($newToken);
                return $this->doHttpRequest($url, $isPost, $payload);
            } else {
                $this->showError($response);
            }
        }

        return $response;
    }

    /**
     * Output a formatted error.
     *
     * @param $error
     */
    private function showError($error) {
        echo '<pre>';
        var_dump($error);
        exit;
    }
}