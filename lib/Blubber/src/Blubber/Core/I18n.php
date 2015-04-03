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

/**
 * I18n class
 *
 * Main internationalization (I18n) class.  Used for generating language packs.
 *
 * @author      Andrew Heebner <andrew.heebner@gmail.com>
 * @copyright   (c)2015, Andrew Heebner
 * @license     MIT
 * @package     Blubber
 */
class I18n
{

    private static $fallback = 'en';
    private static $lang = null;

    private static $langDir = '';
    private static $langData = [];

    /**
     * Initialize the I18n class
     *
     * @param null|string $forceLang Language to force rather than accept from client (null if none)
     * @return void
     */
    public static function init($forceLang = null)
    {
        self::$langDir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Lang' . DIRECTORY_SEPARATOR;
        self::_loadLang();

        // make sure we have a lang, or else we can fallback
        if (is_null(self::$lang)) {
            self::$lang = self::$fallback;
        }

        // if $forceLang is enabled, make it a priority here
        if (is_string($forceLang) && self::_langExists($forceLang)) {
            self::$lang = $forceLang;
        }
    }

    /**
     * Load a user-supplied language file into our default language data
     *
     * @param string $userData JSON-encoded string of user's language data
     */
    public static function loadLangData($userData)
    {
        /**
         * Example: I18n::loadLangData(file_get_contents('my_lang_file-' . I18n::getLang() . '.json'));
         */
        if (!empty($userData)) {
            $userData = json_decode($userData, true);
            self::$langData = array_merge(self::$langData, $userData);
        }
    }

    /**
     * Load language data into memory
     *
     * @return void
     */
    private static function _loadLang()
    {
        // find the acceptable lang and load it up
        $langs = self::_getUserLangs();

        foreach ($langs as $k => $l) {
            if (self::_langExists($l)) {
                self::$lang = strtolower($l);
                self::$langData = json_decode(file_get_contents(self::$langDir . self::_langFileName($l)), true);
                break;
            }
        }
    }

    /**
     * Get a specified string from language data
     *
     * @param string $key
     * @return null|string
     */
    public static function get($key)
    {
        return isset(self::$langData[$key]) ? self::$langData[$key]: null;
    }

    /**
     * Get the currently used language
     *
     * @return null
     */
    public static function getLang()
    {
        return self::$lang;
    }

    /**
     * Generate the language filename
     *
     * @param string $lang
     * @return string
     */
    private static function _langFileName($lang)
    {
        return 'lang_' . strtolower($lang) . '.json';
    }

    /**
     * Check to see if a language file exists
     *
     * @param string $lang
     * @return bool
     */
    private static function _langExists($lang)
    {
        if (file_exists(self::$langDir . self::_langFileName($lang))) {
            return true;
        }

        return false;
    }

    /**
     * Check Accept-Language header, and set user languages
     *
     * @return array
     */
    private static function _getUserLangs()
    {
        $langs = Request::getAcceptLanguage();
        $userLangs = [];

        foreach ($langs as $lang => $preference) {
            $userLangs[] = $lang;
        }

        return $userLangs;
    }

}