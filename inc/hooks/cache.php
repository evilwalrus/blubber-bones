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


//
// For our uses, we'll use Redis
//
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$app->on('cache.get', function($cache_key) use ($app, $redis) {
    return $redis->get($cache_key);  // returns false if it doesn't exist
});

$app->on('cache.set', function($cache_key, $cache_data) use ($app, $redis) {
    $ttl = $app->dispatch('cache.options')['ttl'];

    $redis->setex($cache_key, $ttl, $cache_data);  // true if successful
});

$app->on('cache.exists', function($cache_key) use ($app, $redis) {
    return $redis->exists($cache_key); // true/false
});

/**
 * This is used as a settings fetcher essentially, just used to return our TTL
 *
 * This will override default options.  Blubber can then call this hook and get the needed
 * options for making conditional response.
 */
$app->on('cache.options', function() use ($app) {
    return [
        'ttl' => 300
    ];
});
