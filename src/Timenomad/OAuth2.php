<?php

namespace Timenomad;

/**
 * Methods for implementing the OAuth2 flow specific to GetResponse.
 *
 * Class OAuth2
 * @package Timenomad
 */
trait OAuth2 {
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
            'grant_type' => 'authorization_code',
            'code'       => $_GET['code']
        ]);

        return $response;
    }

    /**
     * Use passed in Refresh Token to generate a new Access Token. (along with its related data.)
     * Called automatically when an API request fails due to expired token.
     * An Access Token is valid for 1 day.
     *
     * @return array The new token data
     */
    protected function renewAccessToken() {
        $this->accessToken = null;
        $response = $this->doHttpRequest(self::API_URL . '/token', true, [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->refreshToken
        ]);
        $this->setAccessToken($response['access_token']);

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

    /**
     * @param string $refreshToken The Refresh Token that you received in your previous call to get an Access Token
     * @param \Closure $function Will be called when a new token is received, this is where you save the new token in the database.
     */
    public function setAccessTokenExpiryCallback($refreshToken, \Closure $function) {
        $this->refreshToken = $refreshToken;
        $this->accessTokenExpiryCallback = $function;
    }
}