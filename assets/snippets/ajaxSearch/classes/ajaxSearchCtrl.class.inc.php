<?php
/**
 * -----------------------------------------------------------------------------
 * Snippet: AjaxSearch
 * -----------------------------------------------------------------------------
 * @package  AjaxSearchCtrl
 *
 * @author       Coroico - www.evo.wangba.fr
 * @version      1.12.1
 * @date         27/10/2018
 *
 * Purpose:
 *    The AjaxSearchCtrl class contains the logic and synchronisation between model and views
 *
 */

class AjaxSearchCtrl
{
    /** @var AjaxSearchConfig */
    public $asCfg;
    /** @var AjaxSearchInput */
    public $asInput;
    /** @var AjaxSearchResults */
    public $asResults;
    /** @var AjaxSearchUtil */
    public $asUtil;
    /** @var AjaxSearchLog */
    public $asLog;
    /** @var AjaxSearchOutput */
    public $asOutput;
    public $dbg = false;
    public $dbgTpl = false;
    public $dbgRes = false;
    public $log = false;

    public $forThisAs;
    public $searchString;
    public $advSearch;
    public $subSearch;
    public $pagination;
    public $offset;
    public $asf;
    public $output;
    public $fClause;
    public $fParams = array();

    public function init(&$asCfg, &$asInput, &$asResults, &$asOutput, &$asUtil, &$asLog)
    {
        $this->asCfg =& $asCfg;
        $this->asInput =& $asInput;
        $this->asResults =& $asResults;
        $this->asOutput =& $asOutput;
        $this->asUtil =& $asUtil;
        $this->dbg = $asUtil->dbg;
        $this->dbgTpl = $asUtil->dbgTpl;
        $this->dbgRes = $asUtil->dbgRes;
        $this->asLog =& $asLog;
        $asLog_array = explode(':', $asCfg->cfg['asLog']);
        $this->log = (bool)$asLog_array[0];

        $this->asInput->init($asCfg, $this, $asUtil);
        $this->asResults->init($asCfg, $this, $asOutput, $asUtil);
        $this->asOutput->init($asCfg, $this, $asInput, $asResults, $asUtil, $asLog, $this->log);
    }

    /**
     * run the search
     */
    public function run()
    {
        $this->setforThisAs();
        $this->getEvents();     // get $_POST and _GET variables
        $msg = '';
        $valid = $this->asInput->display($msg);
        if ($valid) {
            $valid2 = $this->asResults->getSearchResults($msg);
            if (!$valid2) {
                return $msg;
            }
        }
        $this->asOutput->setAjaxSearchHeader();

        return $this->pagination ?
            $this->asOutput->paginate($valid, $msg) :
            $this->asOutput->display($valid, $msg);
    }

    /**
     * Check if this instance is concerned
     */
    public function setforThisAs()
    {
        if ($this->asCfg->isAjax) {
            $this->forThisAs = true;
        } else {
            $id = '';
            if ((isset($_POST['asid']) && is_scalar($_POST['asid'])) ||
                (isset($_GET['asid']) && is_scalar($_GET['asid']))
            ) {
                $id = isset($_POST['asid']) ? strip_tags($_POST['asid']) : strip_tags(urldecode($_GET['asid']));
            }
            $this->forThisAs = ($this->asCfg->cfg['asId'] === $id);
        }
    }

    public function getEvents()
    {
        $this->getSearchString();
        $this->subSearch = $this->asCfg->cfg['subSearch'];
        if (isset($_POST['subsearch']) || isset($_GET['subsearch'])) {
            if (isset($_POST['subsearch'])) {
                $ssc = isset($_POST['ssc']) ? ':' : ',';
                if (is_array($_POST['subsearch'])) {
                    foreach ($_POST['subsearch'] as $key => $value) {
                        $_POST['subsearch'][$key] = strip_tags($value);
                    }
                    $this->subSearch = implode($ssc,$_POST['subsearch']);
                } else {
                    $this->subSearch = strip_tags($_POST['subsearch']);
                }
            } else {
                $ssc = isset($_GET['ssc']) ? ':' : ',';
                if (is_array($_GET['subsearch'])) {
                    foreach ($_GET['subsearch'] as $key => $value) {
                        $_GET['subsearch'][$key] = strip_tags($value);
                    }
                    $this->subSearch = implode($ssc,$_GET['subsearch']);
                } else {
                    $this->subSearch = strip_tags($_GET['subsearch']);
                }
            }
        }
        if ($this->dbg) {
            $this->asUtil->dbgRecord($this->subSearch, "getEvents - subsearch");
        }

        $asfConfig = 'asfConfig';
        if ((isset($_POST['asf']) || isset($_GET['asf'])) &&  function_exists($asfConfig)) {
            $this->asf = isset($_POST['asf']) ? strip_tags($_POST['asf']) : strip_tags(urldecode($_GET['asf']));
            $this->fClause = $asfConfig($this->asf, $this->fParams);
            if ($this->dbg) {
                $this->asUtil->dbgRecord($this->fParams, 'getEvents - fParams');
                $this->asUtil->dbgRecord($this->fClause, 'getEvents - fClause');
            }
        } else {
            $this->asf  = '';
        }
        if ($this->dbg) {
            $this->asUtil->dbgRecord($this->asf, 'getEvents - asf');
        }

        $this->offset = isset($_GET['aso']) ? strip_tags(urldecode($_GET['aso'])) : '0,0';
        if ($this->dbg) {
            $this->asUtil->dbgRecord($this->offset, 'getEvents - offset');
        }

        $this->pagination = isset($_POST['pgn']) ? strip_tags($_POST['pgn']) : '';
        if ($this->dbg) {
            $this->asUtil->dbgRecord($this->pagination, 'getEvents - pgn');
        }
    }

    /**
     * @return void
     */
    public function getSearchString()
    {
        $this->searchString = '';
        $this->advSearch = $this->asCfg->cfg['advSearch'];

        if ($this->forThisAs) {
            if (!$this->asCfg->isAjax) {
                if (isset($_POST['search']) || (isset($_GET['search']) && (!$this->asCfg->cfg['ajaxSearch']))) {
                    if (isset($_POST['search'])) {
                        if (is_array($_POST['search'])) {
                            foreach ($_POST['search'] as $key => $value) {
                                $_POST['search'][$key] = strip_tags($value);
                            }
                            $this->searchString = implode(' ', $_POST['search']);
                        } else {
                            $this->searchString = strip_tags($_POST['search']);
                        }
                    } else {
                        $this->searchString = strip_tags(urldecode($_GET['search']));
                    }
                    if (isset($_POST['advsearch'])) {
                        $this->advSearch = strip_tags($_POST['advsearch']);
                    } elseif (isset($_GET['advsearch'])) {
                        $this->advSearch = strip_tags(urldecode($_GET['advsearch']));
                    }
                }
            } else {
                if (isset($_POST['search'])) {
                    if (is_array($_POST['search'])) {
                        foreach ($_POST['search'] as $key => $value) {
                            $_POST['search'][$key] = strip_tags($value);
                        }
                        $this->searchString = implode(' ', $_POST['search']);
                    } else {
                        $this->searchString = strip_tags($_POST['search']);
                    }

                    if ($this->asCfg->pgCharset != 'UTF-8' &&
                        (ini_get('mbstring.encoding_translation') === '' || strtolower(ini_get('mbstring.http_input')) === 'pass')
                    ) {
                        $this->searchString = mb_convert_encoding($this->searchString, $this->asCfg->pgCharset, 'UTF-8');
                        $this->asOutput->setNeedsConvert(true);
                    } else {
                        $this->asOutput->setNeedsConvert(false);
                    }
                    if (isset($_POST['advsearch'])) {
                        $this->advSearch = strip_tags($_POST['advsearch']);
                    }
                }
            }
        }

        if ($this->dbg) {
            $this->asUtil->dbgRecord($this->searchString, 'getSearchString - searchString');
            $this->asUtil->dbgRecord($this->advSearch, 'getSearchString - advSearch');
        }
    }

    /**
     * depending advSearch, get the search words
     *
     * @param $search
     * @param $advSearch
     * @return array
     */
    public function getSearchWords($search, $advSearch)
    {
        $searchList = array();
        if ($advSearch === NOWORDS || !$search) {
            return $searchList;
        }
        if ($advSearch === EXACTPHRASE) {
            $searchList[] = $search;
        } else {
            $searchList = explode(' ', $search);
        }
        return $searchList;
    }

    /**
     * @param $searchString
     */
    public function setSearchString($searchString)
    {
        $this->searchString = $searchString;
    }

    /**
     * @param $advSearch
     */
    public function setAdvSearch($advSearch)
    {
        $this->advSearch = $advSearch;
    }

    /**
     * @param $subSearch
     */
    public function setSubSearch($subSearch)
    {
        $this->subSearch = $subSearch;
    }
}
