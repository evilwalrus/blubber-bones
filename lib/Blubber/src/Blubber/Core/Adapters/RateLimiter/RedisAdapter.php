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

namespace Blubber\Core\Adapters\RateLimiter;


use Blubber\Core\Interfaces\RateLimiterInterface;
use Blubber\Core\RateLimiter;

class RedisAdapter extends RateLimiter implements RateLimiterInterface
{

    private $redis;

    protected $host = '127.0.0.1';
    protected $port = 6379;


    public function __construct($host, $port)
    {
        $this->host = $host;
        $this->port = $port;

        $this->redis = new \Redis();
        $this->redis->connect($this->host, $this->port);
    }

    // getUserLimit is an all-in-one function.  It will set the proper increments
    // and return the current rate limit.
    public function getUserLimit()
    {
        $currentLimit = $this->cost;

        if ($this->redis->exists($this->key)) {
            $currentLimit = $this->redis->get($this->key);

            if ($currentLimit < $this->limit) {
                // increment when user performs an action at cost
                $this->redis->incrBy($this->key, $this->cost);

                return ($currentLimit + $this->cost);
            } else {
                // rate-limited; return the max limit
                return $this->limit;
            }
        } else {
            // set the key with a TTL
            $this->redis->setex($this->key, $this->reset, $this->cost);
        }

        return $currentLimit;
    }

    public function getUserReset()
    {
        return $this->redis->ttl($this->key);
    }

    protected function setAdapterData($data)
    {
        foreach ($data as $k => $v) {
            $this->{$k} = $v;
        }
    }

}