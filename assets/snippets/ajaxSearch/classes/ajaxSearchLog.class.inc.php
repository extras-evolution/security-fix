<?php
/**
 * -----------------------------------------------------------------------------
 * Snippet: AjaxSearch
 * -----------------------------------------------------------------------------
 * @package  AjaxSearchLog
 *
 * @author       Coroico - www.evo.wangba.fr
 * @version      1.12.1
 * @date         27/10/2018
 *
 * Purpose:
 *    The AjaxSearchLog class contains all functions used to Log AjaxSearch requests
 *
 */

define('CMT_MAX_LENGTH', 100);
define('CMT_MAX_LINKS', 3);
define('LOG_TABLE_NAME', 'ajaxsearch_log');
define('PURGE', 200);
define('COMMENT_JSDIR', 'js/comment');

class AjaxSearchLog
{
    public $log = '0:0';
    public $logcmt;

    private $tbName;
    private $purge;

    /**
     *  Constructs the ajaxSearchLog object
     *
     *  @access public
     *  @param string $log log parameter
     */
    public function __construct($log = '0:0')
    {
        global $modx;
        $this->tbName = $modx->getFullTableName(LOG_TABLE_NAME);
        $asLog_array = explode(':', $log);
        $this->log = (int)$asLog_array[0];
        if ($this->log > 0 && $this->log < 3) {
            $this->purge = isset($asLog_array[2]) ? (int)$asLog_array[2] : PURGE;
            if ($this->purge < 0) {
                $this->purge = PURGE;
            }
            $this->_initLogTable();

            $this->logcmt = isset($asLog_array[1]) ? (int)$asLog_array[1] : 0;
            if ($this->logcmt) {
                $jsInclude = AS_SPATH . COMMENT_JSDIR . '/ajaxSearchCmt.js';
                $modx->regClientStartupScript($jsInclude);
            }
        } else {
            $this->log = 0;
        }
    }

    /**
     *  Create the ajaxSearch log table if needed
     */
    public function _initLogTable()
    {
        global $modx;
        $db = $modx->db->config['dbase'];
        $tbn = $modx->db->config['table_prefix'] . LOG_TABLE_NAME;
        if (!$this->_existLogTable($db, $tbn)) {
            $SQL_CREATE_TABLE = "CREATE TABLE " . $this->tbName . " (
          `id` smallint(5) NOT NULL auto_increment,
          `searchstring` varchar(128) NOT NULL,
          `nb_results` smallint(5) NOT NULL,
          `results` mediumtext,
          `comment` mediumtext,
          `as_call` mediumtext,
          `as_select` mediumtext,
          `date` timestamp(12) NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
          `ip` varchar(255) NOT NULL,
          PRIMARY KEY  (`id`)
          ) ENGINE=MyISAM;";
            if (!$modx->db->query($SQL_CREATE_TABLE)) {
                return false;
            }
            return true;
        }
    }

    /**
     *  Check if the table exists or not
     */
    public function _existLogTable($db, $tbName)
    {
        global $modx;
        $SHOW_TABLES = "SHOW TABLES FROM $db LIKE '$tbName';";
        $exec = $modx->db->query($SHOW_TABLES);
        return $modx->db->getRecordCount($exec);
    }

    /**
     *  Write a log record in database
     *
     *  @access public
     *  @param array $rs record set
     *  return the id of the record logged
     */
    public function setLogRecord($rs)
    {
        global $modx;
        if ($this->purge) {
            $this->purgeLogs();
        }
        $lastid = $modx->db->insert(
            array(
                'searchstring' => $modx->db->escape($rs['searchString']),
                'nb_results' => $rs['nbResults'],
                'results' => trim($rs['results']),
                'comment' => '',
                'as_call' => $rs['asCall'],
                'as_select' => $rs['asSelect'],
                'ip' => $_SERVER['REMOTE_ADDR'],
            ),
            $this->tbName
        );
        return $lastid;
    }

    /**
     *  Purge the log table
     */
    public function _purgeLogs()
    {
        global $modx;
        $rs = $modx->db->select('count(*) AS count', $this->tbName);
        $nbLogs = $modx->db->getValue($rs);

        if ($nbLogs + 1 > $this->purge) {
            $modx->db->delete($this->tbName);
        }
    }

    /**
     * Update a comment of a search record in database
     *
     * @access public
     * @param int $logid id of the log
     * @param string $ascmt comment
     */
    public function updateComment($logid, $ascmt)
    {
        global $modx;
        $fields['comment'] = $modx->db->escape($ascmt);
        $where = "id='" . $logid . "'";
        $modx->db->update($fields, $this->tbName, $where);
        return true;
    }
}
/**
 * The code below handles comment sent if the $_POST variables are set.
 * Used when the user post comment from the ajaxSearch results window
 */
if (!empty($_POST['logid']) && !empty($_POST['ascmt'])) {
    $ascmt = strip_tags($_POST['ascmt']);
    $logid = (int)$_POST['logid'];
    $safeCmt = (strlen($ascmt) < CMT_MAX_LENGTH) && (substr_count($ascmt, 'http') < CMT_MAX_LINKS);
    if ($ascmt !== '' && $logid > 0 && $safeCmt) {
        define('MODX_API_MODE', true);
        include_once(__DIR__ . '/../../../../index.php');
        $modx->db->connect();
        if (empty($modx->config)) {
            $modx->getSettings();
        }
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
            (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') ||
            strpos($_SERVER['HTTP_REFERER'], $modx->getConfig('site_url')) !== 0
        ) {
            $modx->sendErrorPage();
        }

        $asLog = new AjaxSearchLog();
        $asLog->updateComment($logid, $ascmt);
        echo 'comment about record ' . $logid . ' registered';
    } else {
        echo 'ERROR: comment rejected';
    }
}
