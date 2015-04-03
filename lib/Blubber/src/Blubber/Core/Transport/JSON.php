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

namespace Blubber\Core\Transport;

use Blubber\Exceptions\HTTPException;

/**
 * JSON class
 *
 * Used for extending native json handling
 *
 * @author      Andrew Heebner <andrew.heebner@gmail.com>
 * @copyright   (c)2015, Andrew Heebner
 * @license     MIT
 * @package     Blubber
 */
class JSON
{
    protected static $_contentType = 'application/json';
    protected static $_data        = '';

    public static function encode(array $message = [])
    {
        if (!empty($message)) {
            $message = json_encode($message);
            $error = self::_handleError();

            if (is_string($error)) {
                throw new HTTPException($error, 500);
            }
        }

        return $message;
    }

    public static function decode($message, $decodeAsArray = true)
    {
        $data = json_decode($message, $decodeAsArray);
        $error = self::_handleError();

        if (is_string($error)) {
            throw new HTTPException($error, 400);
        }

        return $data;
    }

    private static function _handleError()
    {
        $errors = [
            JSON_ERROR_NONE => true,
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
            JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
            JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
            JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded'
        ];

        $error = json_last_error();

        if (!in_array($error, $errors)) {
            return 'Unknown Error';
        }

        return $errors[$error];
    }

    public static function getContentType()
    {
        return self::$_contentType;
    }

    public static function setContentType($contentType)
    {
        self::$_contentType = $contentType;
    }

}