<?php
/**
 * -----------------------------------------------------------------------------
 * Snippet: AjaxSearch
 * -----------------------------------------------------------------------------
 * @package  AjaxSearchUtil
 *
 * @author       Coroico - www.evo.wangba.fr
 * @version      1.12.1
 * @date         27/10/2018
 *
 * Purpose:
 *    The AjaxSearchUtil class contains some util methods
 *
 */

define('AS_DBGDIR', AS_PATH . 'debug');
define('AS_DBGFILE', 'ajaxSearch_log.txt');

class AjaxSearchUtil
{
    public $level = 0;  // debug level
    public $tstart;     // start time
    public $dbg;        // first level of debuging
    public $dbgTpl;     // debuging of templates
    public $dbgRes;     // debuging of results

    private $dbgFd;
    private $current_pcre_backtrack;

    /**
     * @param int|string $level
     * @param string $version
     * @param int|string $tstart
     * @param string $msgErr
     */
    public function __construct($level, $version, $tstart, &$msgErr)
    {
        global $modx;
        $level = (int)$level;
        $this->level = (abs($level) > 0 && abs($level) < 4) ? $level : 0;
        $this->dbg = ($this->level > 0);
        $this->dbgRes = ($this->level > 1);
        $this->dbgTpl = ($this->level > 2);
        $this->tstart = $tstart;

        $msgErr = '';
        $header = implode(' - ', array(
            'AjaxSearch ' . $version .
            'Php' . phpversion() .
            'MySql ' . (method_exists($modx->db, 'getVersion') ? $modx->db->getVersion() : mysql_get_server_info())
        ));
        if ($this->level > 0 && $level < 4) { // debug trace in a file
            $isWriteable = is_writeable(AS_DBGDIR);
            if ($isWriteable) {
                $dbgFile = AS_DBGDIR . '/' . AS_DBGFILE;
                $this->dbgFd = fopen($dbgFile, 'w+');
                $this->dbgRecord($header);
                fclose($this->dbgFd);
                $this->dbgFd = fopen($dbgFile, 'a+');
            } else {
                $msgErr = '<br />' .
                    '<h3>' .
                        'AjaxSearch error: to use the debug mode, ' . AS_DBGDIR . ' should be a writable directory.' .
                        'Change the permissions of this directory.' .
                    '</h3>' .
                    '<br />';
            }
        }
    }

    /**
    *  Set Debug log record
     * @return void
    */
    public function dbgRecord()
    {
        $args = func_get_args();
        if ($this->level > 0) {
            // write trace in a file
            $when = date('[j-M-y h:i:s] ');
            $etime = $this->getElapsedTime();
            $memory = sprintf("%.2fMb", memory_get_usage()/(1024*1024)) . ' > ';
            $nba = count($args);
            $result = implode(' ', array($when, $etime, $memory));
            if ($nba > 1) {
                $result.= $args[1] . ' : ';
            }
            $result .= (is_array($args[0]) ? print_r($args[0], true) : $args[0]) . "\n";

            fwrite($this->dbgFd, $result);
        }
    }

    /**
    * Returns the elapsed time between the current time and tstart
    *
    * @param int $start starting time
    * @return string Returns the elapsed time
    */
    public function getElapsedTime($start = 0)
    {
        list($usec, $sec)= explode(' ', microtime());
        $tend= (float) $usec + (float) $sec;
        return sprintf('%.4fs', $start ? ($tend - $start) : ($tend - $this->tstart));
    }

    /**
    * Change the current PCRE Backtrack limit
    *
    * @param int $backtrackLimit PCRE backtrack limit
    */
    public function setBacktrackLimit($backtrackLimit)
    {
        $this->current_pcre_backtrack = ini_get('pcre.backtrack_limit');
        if ($this->dbg) {
            $this->dbgRecord($this->current_pcre_backtrack, "AjaxSearch - pcre.backtrack_limit");
        }
        ini_set('pcre.backtrack_limit', $backtrackLimit);
    }

    /**
     * Restore the initial PCRE Backtrack limit
     * @return void
     */
    public function restoreBacktrackLimit()
    {
        ini_set('pcre.backtrack_limit', $this->current_pcre_backtrack);
    }
}
