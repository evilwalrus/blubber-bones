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

use Blubber\Core\RateLimiter;
use Blubber\Core\Adapters\RateLimiter\RedisAdapter;

//
// $cost is the cost of the rate-limiting to the user
//
$app->on('__RATE_LIMIT__', function($cost) use ($app) {
    /**
     * Use the RedisAdapter in our case
     */
    $rl = new RateLimiter(new RedisAdapter('127.0.0.1', 6379));

    $rl->setCost($cost);

    if ($app->isAuthenticated()) {

        // authenticated users get 5000 requests per hour
        $rl->setLimit(5000);

        $auth = $app->getAuthorization(false);

        //
        // Get the appropriate storage key for the user
        //
        switch ($app->authenticatedWith()) {
            case 'auth.oauth2':
                $key = $auth['auth_data']; // gets the Bearer token
                break;

            case 'auth.basic':
                $creds = $app->getBasicAuthCredentials($auth['auth_data']);
                $key = $creds['username'];
                break;

            case 'auth.hmac':
                $key = $app->getHeader('X-Public-Key');
                break;
        }

        $rl->setKey($key);

    } else {
        // unauthed client (60 per hour)
        $rl->setLimit(60);
        $rl->setKey($app->getRealRemoteAddr());
    }

    /**
     * if checkLimits() is not true, then it throws an HTTPException
     */
    $rl->checkLimits();

    /**
     * return the headers so that we can append them to our headers list after this
     * hook is completed.
     */
    return $rl->getLimitHeaders();
});