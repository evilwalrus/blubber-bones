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

namespace Blubber\Core;


use Blubber\Core\Interfaces\RateLimiterInterface;
use Blubber\Exceptions\HTTPException;

class RateLimiter
{

    protected $adapter;

    protected $userLimit = null;
    protected $userReset = null;

    protected $key = 'rl:';

    protected $limit = 100;  // 100 requests in below unit
    protected $reset = 3600; // one hour
    protected $cost  = 1;    // each request costs one credit


    public function __construct(RateLimiterInterface $adapter, $limit = 100, $reset = 3600, $cost = 1)
    {
        $this->adapter = $adapter;

        $this->limit = $limit;
        $this->reset = $reset;
        $this->cost  = $cost;
    }

    //
    // Use as $rateLimit->checkLimits();
    //
    //  If we return true, go on with the processing of their request, if not
    //  the we issue a 429 Too Many Requests error and make them wait.
    //
    public function checkLimits()
    {
        if (self::isLimited()) {
            throw new HTTPException(\Blubber\t('too.many.requests'), 429, self::getLimitHeaders());
        }

        return true;
    }

    public function setKey($key)
    {
        $this->key .= $key;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function setLimit($limit = 100)
    {
        $this->limit = $limit;
    }

    public function getLimit()
    {
        return $this->limit;
    }

    public function setReset($reset = 3600)
    {
        $this->reset = $reset;
    }

    public function getReset()
    {
        return $this->reset;
    }

    public function setCost($cost = 1)
    {
        $this->cost = $cost;
    }

    public function getCost()
    {
        return $this->cost;
    }

    public function isLimited()
    {
        self::_setLimits();

        return ($this->userLimit == $this->adapter->getLimit()) ? true : false;
    }

    public function getLimitHeaders()
    {
        return [
            'X-RateLimit-Limit' => $this->limit,
            'X-RateLimit-Remaining' => $this->limit - $this->userLimit,
            'X-RateLimit-Reset' => time() + $this->userReset
        ];
    }

    private function _setLimits()
    {
        if (is_null($this->userLimit) && is_null($this->userReset)) {
            $this->userLimit = $this->adapter->getUserLimit();
            $this->userReset = $this->adapter->getUserReset();
        }
    }

}