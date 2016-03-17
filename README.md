# GetResponseV3Api
A read-only PHP wrapper for the GetResponse V3 API, supporting OAuth2 authorization flow.

# Create GetResponse App
* You need to create an app here: https://app.getresponse.com/manage_api.html (Third party applications)
* Note the Client ID & Client Secret, you will need them.
* Set Redirect URL to your web app's route responsible for fetching the access token, after user approves to grant your app access.

# Usage
* Install via composer: `composer require timenomad/getresponse-v3-api`
* Init API object: `$getResponse = new \Timenomad\GetResponseV3Api("*Client ID*", "*Client Secret*", "*State*");`.  
  State is just a random combination of characters, to validate callback's origin. It's a static value.  
  An example would be: fdsfjhs3SDGg23refsd2u09@$
* Get OAuth2 user consent URL: `$getResponse->getOAuthConsentUrl()`
* When user approves and your callback URL is called: `$accessToken = $getResponse->getAccessToken();`
* Store the token in your storage engine
* Having the token, we can now configure the authentication and begin calling the API:  
  `$getResponse->setAccessToken($token['access_token']);`  
  `$getResponse->setAccessTokenExpiryCallback($token['refresh_token'], function($newTokenData) {`  
      `// Access Token expired. This function will be called after a new token has been generated.`  
      `// Here you can save the new token data to the database, or any other storage means.`  
  `});`  

