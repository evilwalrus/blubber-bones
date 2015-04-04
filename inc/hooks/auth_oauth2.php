<?php
/**
 * Copyright (c)2015 Andrew Heebner
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

$app->on('oauth2.authorize', function($client_id, $client_secret) {
    /**
     * Issue an authorization code
     */
});

$app->on('oauth2.access.token', function($auth_code) {
    /**
     * Issue a new access token using the supplied authorization code
     */
});

$app->on('oauth2.refresh.token', function($access_token, $refresh_token) {
    /**
     * Issue a new access token using the supplied refresh token
     *
     * if $access_token equals old access token (looking up using refresh token), then
     * we issue (and store) a new access token (and refresh token) and send it to the client.
     */
});

// used as event handler to check if we have the Bearer token
$app->on('auth.oauth2', function() use ($app) {
    $auth = $app->getAuthorization();

    if (is_array($auth) && $auth['auth_scheme'] == 'Bearer') {
        $token = $auth['auth_data'];
        /**
         * Do your custom token lookup here, and return true or false accordingly
         */
    }
});