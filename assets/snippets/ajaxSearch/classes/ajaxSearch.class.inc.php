<?php
/**
 * -----------------------------------------------------------------------------
 * Snippet: AjaxSearch
 * -----------------------------------------------------------------------------
 * @package  AjaxSearch
 *
 * @author       Coroico - www.evo.wangba.fr
 * @version      1.12.1
 * @date         27/10/2018
 *
 * Purpose:
 *    The AjaxSearch class contains all functions and data used to manage AjaxSearch
 *
 */

define('MIN_CHARS', 2); // minimum number of characters
define('MAX_CHARS', 30); // maximum number of characters
define('MIN_WORDS', 1); // minimum number of words
define('MAX_WORDS', 10); // maximum number of words

define('EXTRACT_MIN', 50); // minimum length of extract
define('EXTRACT_MAX', 800); // maximum length of extract

define('MIXED', 'mixed');
define('UNMIXED', 'unmixed');

define('DEFAULT_SITE', 'defsite');
define('DEFAULT_SUBSITE', 'site_wide');
define('MIXED_SITES', 'all_sites');
define('UNCATEGORIZED', 'uncategorized');
define('UNTAGGED', 'untagged');

define('SITE_CONFIG', 'siteConfig');
define('SUBSITE_CONFIG', 'subsiteConfig');
define('CATEG_CONFIG', 'categConfig');
define('FILTER_CONFIG', 'filterConfig');

define('PCRE_BACKTRACK_LIMIT', 1600000);

// advanced search parameter values
define('ONEWORD', 'oneword');
define('ALLWORDS', 'allwords');
define('EXACTPHRASE', 'exactphrase');
define('NOWORDS', 'nowords');

class AjaxSearch {
    /**
     * @param int $tstart start time
     * @param array $dcfg default configuration
     * @param null|array $cfg current configuration
     * @return string  the ajaxSearch output
     */
    public function run($tstart, $dcfg, $cfg = null)
    {
        $this->loadClasses();

        if ($this->checkClasses()) {
            $msgErr = null;
            $asCfg = new AjaxSearchConfig($dcfg, $cfg);
            if (!$asCfg->initConfig($msgErr)) {
                return $msgErr;
            }

            $asUtil = new AjaxSearchUtil($asCfg->cfg['debug'], $asCfg->cfg['version'], $tstart, $msgErr);
            if ($msgErr) {
                return $msgErr;
            }

            $dbg = $asUtil->dbg; // first level of debug log
            @set_time_limit($asCfg->cfg['timeLimit']);

            if ($asCfg->cfg['asLog']) {
                $asLog = new AjaxSearchLog($asCfg->cfg['asLog']);
            }
            if ($dbg) {
                $asCfg->displayConfig($asUtil);
            }

            $asCtrl = new AjaxSearchCtrl();
            $asInput = new AjaxSearchInput();
            $asResults = new AjaxSearchResults();
            $asOutput = new AjaxSearchOutput();

            $asCtrl->init($asCfg, $asInput, $asResults, $asOutput, $asUtil, $asLog);

            $asUtil->setBacktrackLimit(PCRE_BACKTRACK_LIMIT);

            $output = $asCtrl->run();

            $asUtil->restoreBacktrackLimit();

            if ($dbg) {
                $asUtil->dbgRecord($asUtil->getElapsedTime(), 'AjaxSearch - Elapsed Time');
            }
        } else {
            $output = '<h3>error: AjaxSearch classes not found</h3>';
        }

        return $output;
    }

    /**
     * @return void
     */
    protected function loadClasses()
    {
        include_once AS_PATH . 'classes/ajaxSearchConfig.class.inc.php';
        include_once AS_PATH . 'classes/ajaxSearchUtil.class.inc.php';
        include_once AS_PATH . 'classes/ajaxSearchLog.class.inc.php';
        include_once AS_PATH . 'classes/ajaxSearchCtrl.class.inc.php';
        include_once AS_PATH . 'classes/ajaxSearchInput.class.inc.php';
        include_once AS_PATH . 'classes/ajaxSearchResults.class.inc.php';
        include_once AS_PATH . 'classes/ajaxSearchOutput.class.inc.php';
    }

    /**
     * @return bool
     */
    protected function checkClasses()
    {
        return class_exists('AjaxSearchCtrl') &&
            class_exists('AjaxSearchInput') &&
            class_exists('AjaxSearchResults') &&
            class_exists('AjaxSearchOutput');
    }
}

/**
 * Below functions could be used in end-user fonctions
 */

if (!function_exists('stripTags')) {
    /**
     * Remove modx sensitive tags
     *
     * @param $text
     * @return null|string|string[]
     */
    function stripTags($text)
    {
        $modRegExArray[] = '~\[\[(.*?)\]\]~s';
        $modRegExArray[] = '~\[\!(.*?)\!\]~s';
        $modRegExArray[] = '#\[\~(.*?)\~\]#s';
        $modRegExArray[] = '~\[\((.*?)\)\]~s';
        $modRegExArray[] = '~{{(.*?)}}~s';
        $modRegExArray[] = '~\[\*(.*?)\*\]~s';
        $modRegExArray[] = '~\[\+(.*?)\+\]~s';

        foreach ($modRegExArray as $mReg) {
            $text = preg_replace($mReg, '', $text);
        }

        return $text;
    }
}

if (!function_exists('stripHtml')) {
    /**
     * Remove HTML sensitive tags
     * @deprecated
     * @param $text
     * @return string
     */
    function stripHtml($text)
    {
        return strip_tags($text);
    }
}

if (!function_exists('stripHtmlExceptImage')) {
    /**
     * Remove HTML sensitive tags except image tag
     *
     * @param $text
     * @return string
     */
    function stripHtmlExceptImage($text)
    {
        $text = strip_tags($text, '<img>');
        return $text;
    }
}

if (!function_exists('stripJscripts')) {
    /**
     * Remove jscript
     *
     * @param $text
     * @return null|string|string[]
     */
    function stripJscripts($text)
    {
        $text = preg_replace("'<script[^>]*>.*?</script>'si", "", $text);
        $text = preg_replace('/{.+?}/', '', $text);
        return $text;
    }
}

if (!function_exists('stripLineBreaking')) {
    /**
     * replace line breaking tags with whitespace
     *
     * @param $text
     * @return null|string|string[]
     */
    function stripLineBreaking($text)
    {
        $text = preg_replace("'<(br[^/>]*?/|hr[^/>]*?/|/(div|h[1-6]|li|p|td))>'si", ' ', $text);
        return $text;
    }
}
