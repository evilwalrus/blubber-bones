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
    $red = new RedisAdapter('127.0.0.1', 6379);
    $rl = new RateLimiter($red);

    /**
     * TODO: Setup different limit profiles:
     *   - Non-auth users would have less or no rate-limit
     *   - Basic auth would have increased privileges
     *   - OAuth, HMAC, and custom schemes would take the cake
     */

    /**
     * These are all default values, but just showing for semantics
     */
    $rl->setCost($cost);
    $rl->setLimit(100);
    $rl->setReset(3600);

    /**
     * Realistically, we'd probably use the users API key and/or hash of some type
     * in order to identify them and produce a key for storage.
     *
     * setKey() is super important here, as it's the only means we have to identify the user
     */
    $rl->setKey(md5($app->getRealRemoteAddr()));

    /*
     * if checkLimits() is not true, then it throws an HTTPException
     */
    $rl->checkLimits();

    /**
     * return the headers so that we can append them to our headers list after this
     * hook is completed.
     */
    return array_merge($rl->getLimitHeaders(), ['X-RateLimit-Key' => $rl->getKey()]);
});