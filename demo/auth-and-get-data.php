<?php

require('..\\src\\Timenomad\\Http.php');
require('..\\src\\Timenomad\\OAuth2.php');
require('..\\src\\Timenomad\\GetResponseV3Api.php');

function dd($var) {
    echo '<pre>';
    var_dump($var);
    exit;
}

// Init API object
$getResponse = new \Timenomad\GetResponseV3Api(
    'your client id',
    'your client secret',
    'SDgh43r098udfsdF#$%2' // or any other combination of characters
);

// Save token to a file (will probably be a database in real world use)
$token = file_get_contents('token.txt');

// OAuth2 flow
if (empty($token)) {
    // User approved, Redirect Url is called from GetResponse's servers
    if (isset($_GET['code'])) {
        // Exchange code for an Access Token (along with Refresh Token and more)
        $accessToken = $getResponse->getAccessToken();
        file_put_contents('token.txt', json_encode($accessToken));
        header('Location: /');
    } else {
        header('Location: ' . $getResponse->getOAuthConsentUrl());
    }
    exit;
}

// Use API to get data
$token = json_decode($token, true);

// Important to set the Access Token before issuing any API calls
$getResponse->setAccessToken($token['access_token']);
$getResponse->setAccessTokenExpiryCallback($token['refresh_token'], function($newTokenData) {
    // Access Token expired. This function will be called after a new token has been generated.
    // Here you can save the new token data to the database, or any other storage means.
    file_put_contents('token.txt', json_encode($newTokenData));
});

// API methods

// User
$userInfo = $getResponse->getAccountInfo();

// Campaigns
$campaigns = $getResponse->getCampaigns();
$campaign = $getResponse->getCampaign($campaigns[0]['campaignId']);
$cpnContacts = $getResponse->getCampaignContacts($campaigns[0]['campaignId']);
$cpnBlacklists = $getResponse->getCampaignBlacklists($campaigns[0]['campaignId']);
// 2nd param one of: list-size, locations, origins, removals, subscriptions, balance, summary
$statistics = $getResponse->getCampaignStatistics($campaigns[0]['campaignId'], 'list-size');

// Newsletters
$newsletters = $getResponse->getNewsletters($campaigns[0]['campaignId']);
$newsletter = $getResponse->getNewsletter($newsletters[0]['newsletterId']);

//todo: continue with newsletters statistics

// Echo away...
dd($statistics);
