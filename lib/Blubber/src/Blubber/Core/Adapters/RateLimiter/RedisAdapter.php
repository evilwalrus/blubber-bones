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

class RedisAdapter implements RateLimiterInterface
{

    private $redis;

    protected $host = '127.0.0.1';
    protected $port = 6379;

    protected $limit = 100;  // 100 requests in below unit
    protected $reset = 3600; // one hour

    protected $key = 'rl:';


    public function __construct($host, $port, $limit = 100, $reset = 3600)
    {
        $this->host = $host;
        $this->port = $port;

        $this->limit = $limit;
        $this->reset = $reset;

        $this->redis = new \Redis();
        $this->redis->connect($this->host, $this->port);
    }

    // getUserLimit is an all-in-one function.  It will set the proper increments
    // and return the current rate limit.
    public function getUserLimit()
    {
        $currentLimit = 1;

        if ($this->redis->exists($this->key)) {
            $current_limit = $this->redis->get($this->key);

            if ($current_limit < $this->limit) {
                // increment when user performs an action at cost
                $this->redis->incrBy($this->key, 1);
                return $current_limit;
            } else {
                // rate-limited, return the max limit
                return $this->limit;
            }
        } else {
            $this->redis->setex($this->key, $this->reset, 1);
        }

        return $currentLimit;
    }

    public function getLimit()
    {
        return $this->limit;
    }

    public function getReset()
    {
        return $this->reset;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function getUserReset()
    {
        return $this->redis->ttl($this->key);
    }

    public function setKey($key)
    {
        $this->key = $key;
    }
}