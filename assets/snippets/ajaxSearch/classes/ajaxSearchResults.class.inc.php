<?php
/**
 * -----------------------------------------------------------------------------
 * Snippet: AjaxSearch
 * -----------------------------------------------------------------------------
 * @package  AjaxSearchResults
 *
 * @author       Coroico - www.evo.wangba.fr
 * @version      1.12.1
 * @date         27/10/2018
 *
 * Purpose:
 *    The AjaxSearchResults class contains all functions and data used to manage Results
 *
 */

if (! defined('GROUP_CONCAT_LENGTH')) {
    define('GROUP_CONCAT_LENGTH', 4096); // maximum length of the group concat
}

class AjaxSearchResults
{
    /**
     * @var AjaxSearchConfig
     */
    public $asCfg;
    /**
     * @var AjaxSearchCtrl
     */
    public $asCtrl;
    /**
     * @var AjaxSearchOutput
     */
    public $asOutput;
    /**
     * @var AjaxSearchUtil
     */
    public $asUtil;
    /**
     * @var AjaxSearchLog
     */
    public $asLog;

    public $dbg = false;
    public $dbgRes = false;
    public $log;

    public $groupResults = array();
    public $extractNb;
    public $withExtract;
    public $nbGroups;

    public $nbResults;

    private $_siteList;
    private $_subsiteList;

    private $_groupMixedResults = array();
    private $_extractFields = array();
    public $_asRequest;

    private $_array_key;
    private $_filtertype;
    private $_filterValue;

    private $_idType;
    private $_pardoc;
    private $_depth;

    /**
     *  Initializes the class into the proper context
     *
     *  @param AjaxSearchConfig &$asCfg configuration context
     *  @param AjaxSearchCtrl &$asCtrl controler instance
     *  @param AjaxSearchOutput &$asOutput ouput instance
     *  @param AjaxSearchUtil &$asUtil debug instance
     *  @param boolean $dbg debug flag
     *  @param boolean $dbgRes debug flag for results
     */
    public function init(&$asCfg, &$asCtrl, &$asOutput, &$asUtil)
    {
        $this->asCfg =& $asCfg;
        $this->asCtrl =& $asCtrl;
        $this->asOutput =& $asOutput;
        $this->asUtil =& $asUtil;
        $this->dbg = $asUtil->dbg;
        $this->dbgRes = $asUtil->dbgRes;
    }

    /**
     *  Get search results
     *
     *  @param string &$msgErr message error
     *  @return boolean
     */
    public function getSearchResults(&$msgErr)
    {
        global $modx;
        $results = array();
        include_once AS_PATH . "classes/ajaxSearchRequest.class.inc.php";
        if (class_exists('AjaxSearchRequest')) {
            $this->_asRequest = new AjaxSearchRequest($this->asUtil, $this->asCfg->pgCharset);
        }
        if (!$this->_getSiteList()) {
            return false;
        }
        foreach ($this->_siteList as $site) {
            if (!$this->_getSubsiteList()) {
                return false;
            }
            foreach ($this->_subsiteList as $subsite) {
                if (!$this->_getSubsiteParams($site, $subsite,$msgErr)) {
                    return false;
                }
                if (!$this->_checkParams($msgErr)) {
                    return false;
                }
                $this->asCfg->saveConfig($site, $subsite);
                if ($this->asCfg->cfg['showResults']) {
                    $this->asOutput->initClassVariables();
                    $bsf = $this->_doBeforeSearchFilter();
                    $results = $this->_asRequest->doSearch(
                        $this->asCtrl->searchString,
                        $this->asCtrl->advSearch,
                        $this->asCfg->cfg,
                        $bsf,
                        $this->asCtrl->fClause
                    );
                    $results = $this->_doFilter($results, $this->asCtrl->searchString, $this->asCtrl->advSearch);
                    $this->_setSearchResults($site, $subsite, $results);
                }
            }
        }
        $this->asCfg->restoreConfig(DEFAULT_SITE, DEFAULT_SUBSITE);
        $this->_sortMixedResults();
        if ($this->dbgRes) {
            $this->asUtil->dbgRecord($this->asCfg->scfg, "AjaxSearch - scfg");
            $this->asUtil->dbgRecord($this->groupResults, "AjaxSearch - group results");
            $this->asUtil->dbgRecord($this->_groupMixedResults, "AjaxSearch - group mixed results");
        }

        return true;
    }

    /**
     * Get the list of sites from snippet call
     * @return bool
     */
    public function _getSiteList()
    {
        $siteList = array();
        if ($this->asCtrl->forThisAs) {
            if ($this->asCfg->cfg['sites']) {
                $siteList = explode(',', $this->asCfg->cfg['sites']);
            } else {
                $siteList[0] = DEFAULT_SITE;
            }
        }
        if ($this->dbgRes) {
            $this->asUtil->dbgRecord($siteList, "getSiteList - siteList");
        }
        $this->_siteList = $siteList;
        return true;
    }

    /**
     * Get the list of subsites from subsearch parameter
     * @return bool
     */
    public function _getSubsiteList()
    {
        $subsiteList = array();
        if ($this->asCtrl->forThisAs) {
            if ($this->asCtrl->subSearch) {
                $subsiteList = explode(',', $this->asCtrl->subSearch);
            } else {
                $subsiteList[0] = DEFAULT_SUBSITE;
            }
        }
        if ($this->dbgRes) {
            $this->asUtil->dbgRecord($subsiteList, "getSubsiteList - subsiteList");
        }
        $this->_subsiteList = $subsiteList;
        return true;
    }

    /**
     * Get the parameters for each subsite
     *
     * @param $site
     * @param $subsite
     * @param $msgErr
     * @return bool
     */
    public function _getSubsiteParams($site, $subsite, &$msgErr) {
        $msgErr = '';
        $sitecfg = array();
        $subsitecfg = array();

        if ($site != DEFAULT_SITE) {
            $siteConfigFunction = SITE_CONFIG;
            if (!function_exists($siteConfigFunction)) {
                $msgErr = '<br /><h3>AjaxSearch error: search function ' . $siteConfigFunction .  ' not defined in the configuration file: ' . $this->asCfg->cfg['config'] . ' !</h3><br />';
                return false;
            }
            else {
                $sitecfg = $siteConfigFunction($site);
                if (!count($sitecfg)) {
                    $msgErr = '<br /><h3>AjaxSearch error: Site ' .$site .  ' not defined in the configuration file: ' . $this->asCfg->cfg['config'] . ' !</h3><br />';
                    return false;
                }
            }
        }

        if ($subsite != DEFAULT_SUBSITE) {
            $subsiteConfigFunction = SUBSITE_CONFIG;
            if (!function_exists($subsiteConfigFunction)) {
                $msgErr = '<br /><h3>AjaxSearch error: search function ' . $subsiteConfigFunction .  ' not defined in the configuration file: ' . $this->asCfg->cfg['config'] . ' !</h3><br />';
                return false;
            }
            else {
                $subsitecfg = $subsiteConfigFunction($site,$subsite);
                if (!count($subsitecfg)) {
                    $msgErr = '<br /><h3>AjaxSearch error: Subsite ' .$subsite .  ' of ' . $site . 'not defined in the configuration file: ' . $this->asCfg->cfg['config'] . ' !</h3><br />';
                    return false;
                }
            }
        }
        $this->asCfg->cfg = array_merge($this->asCfg->bcfg, (array)$sitecfg, (array)$subsitecfg);
        return true;
    }

    /**
     * Check or not search params
     *
     * @param $msgErr
     * @return bool
     */
    public function _checkParams(&$msgErr) {
        global $modx;

        $msgErr = '';
        if ($this->asCtrl->forThisAs) {
            if (isset($this->asCfg->cfg['extractLength'])) {
                if ($this->asCfg->cfg['extractLength'] == 0) {
                    $this->asCfg->cfg['extract'] = 0;
                }
                if ($this->asCfg->cfg['extractLength'] < EXTRACT_MIN) {
                    $this->asCfg->cfg['extractLength'] = EXTRACT_MIN;
                }
                if ($this->asCfg->cfg['extractLength'] > EXTRACT_MAX) {
                    $this->asCfg->cfg['extractLength'] = EXTRACT_MAX;
                }
            }
            if (isset($this->asCfg->cfg['extract'])) {
                $extr = explode(':', $this->asCfg->cfg['extract'] . ':');
                if ($extr[0] == '' || !is_numeric($extr[0])) {
                    $extr[0] = 0;
                }
                if ($extr[1] == '' || is_numeric($extr[1])) {
                    $extr[1] = 'content';
                }
                $this->asCfg->cfg['extract'] = $extr[0] . ":" . $extr[1];
            }
            if (isset($this->asCfg->cfg['opacity'])) {
                if ($this->asCfg->cfg['opacity'] < 0.) {
                    $this->asCfg->cfg['opacity'] = 0.;
                }
                if ($this->asCfg->cfg['opacity'] > 1.) {
                    $this->asCfg->cfg['opacity'] = 1.;
                }
            }

            // check that the tables where to do the search exist
            if (isset($this->asCfg->cfg['whereSearch'])) {
                $tables_array = explode('|', $this->asCfg->cfg['whereSearch']);
                foreach ($tables_array as $table) {
                    $fields_array = explode(':', $table);
                    $tbcode = $fields_array[0];
                    if ($tbcode != 'content' && $tbcode != 'tv' && $tbcode != 'jot' && $tbcode != 'maxigallery' && !function_exists($tbcode)) {
                        $msgErr = '<br />' .
                            '<h3>' .
                            'AjaxSearch error: table $tbcode not defined in the configuration file: ' . $this->asCfg->cfg['config'] . '!' .
                            '</h3>' .
                            '<br />';
                        return false;
                    }
                }
            }

            // check the list of tvs enabled with "withTvs"
            if (isset($this->asCfg->cfg['withTvs']) && $this->asCfg->cfg['withTvs']) {
                $tv_array = explode(':', $this->asCfg->cfg['withTvs']);
                $tvSign = $tv_array[0];
                if ($tvSign != '+' && $tvSign != '-') {
                    $tvList = $tvSign;
                    $tvSign = '+';
                } else {
                    $tvList = isset($tv_array[1]) ? $tv_array[1] : '';
                }
                if (!$this->_validListTvs($tvList, $msgErr)) {
                    return false;
                }
                $this->asCfg->cfg['withTvs'] = ($tvList) ? $tvSign . ':' . $tvList : $tvSign;
            }

            // check the list of tvs enabled with "phxTvs" - filter the tv already enabled by withTvs
            if (isset($this->asCfg->cfg['withTvs'], $this->asCfg->cfg['phxTvs'])) {
                unset($tv_array);
                $tv_array = explode(':', $this->asCfg->cfg['phxTvs']);
                $tvSign = $tv_array[0];
                if ($tvSign != '+' && $tvSign != '-') {
                    $tvList = $tvSign;
                    $tvSign = '+';
                } else {
                    $tvList = isset($tv_array[1]) ? $tv_array[1] : '';
                }
                if (!$this->_validListTvs($tvList, $msgErr)) {
                    return false;
                }
                $this->asCfg->cfg['phxTvs'] = ($tvList) ? $tvSign . ':' . $tvList : $tvSign;
            }

            if (isset($this->asCfg->cfg['hideMenu'])) {
                $this->asCfg->cfg['hideMenu'] = (($this->asCfg->cfg['hideMenu'] < 0)  || ($this->asCfg->cfg['hideMenu'] > 2)) ?  2 : $this->asCfg->cfg['hideMenu'];
            }

            if (isset($this->asCfg->cfg['hideLink'])) {
                $this->asCfg->cfg['hideLink'] = (($this->asCfg->cfg['hideLink'] < 0)  || ($this->asCfg->cfg['hideLink'] > 1)) ? 1 : $this->asCfg->cfg['hideLink'];
            }

            $this->_idType = ($this->asCfg->cfg['documents'] != "") ? "documents" : "parents";
            $this->_pardoc = ($this->_idType == "parents") ? $this->asCfg->cfg['parents'] : $this->asCfg->cfg['documents'];
            $this->_depth = $this->asCfg->cfg['depth'];

            $this->asCfg->cfg['docgrp'] = '';
            if ($docgrp = $modx->getUserDocGroups()) {
                $this->asCfg->cfg['docgrp'] = implode(",", $docgrp);
            }
        } else {
            $this->asCfg->cfg['showResults'] = false;
        }
        return true;
    }

    /**
     * Set up search results
     *
     * @param $site
     * @param $subsite
     * @param $rs
     * @return bool
     */
    public function _setSearchResults($site, $subsite, $rs) {
        global $modx;
        $nbrs = count($rs);
        if (!$nbrs) {
            return false;
        }
        $categConfigFunction = CATEG_CONFIG;
        $this->_initExtractVariables();
        $display = $this->asCfg->cfg['display'];
        $select = $this->_asRequest->asSelect;
        $this->nbResults = 0;
        $grpresults = array();
        if ($display == MIXED) {
            $this->asCfg->chooseConfig(DEFAULT_SITE, DEFAULT_SUBSITE, $display);
            if (!isset($this->_groupMixedResults['length'])) {
                $this->_groupMixedResults = $this->_setHeaderGroupResults(MIXED_SITES, $subsite, $display, 'N/A', $select, $nbrs);
            } else {
                $this->_groupMixedResults['length']+= $nbrs;
            }

            $order_array = explode(',', $this->asCfg->cfg['order']);
            $order = $order_array[0];
            for ($i=0; $i<$nbrs; $i++){
                $rs[$i]['order'] = $rs[$i][$order];
                $this->_groupMixedResults['results'][] = $rs[$i];
            }
            if ($this->dbgRes) {
                $this->asUtil->dbgRecord($this->_groupMixedResults, "AjaxSearch - group mixed results");
            }
        } else {
            if ($this->asCfg->cfg['category']) {
                $categ = '---';
                $cfunc = function_exists($categConfigFunction);
                $ic = 0;
                for ($i = 0; $i < $nbrs; $i++) {
                    $newCateg = trim($rs[$i]['category']);
                    if ($newCateg != $categ) {
                        $display = UNMIXED;
                        $cfg = NULL;
                        if ($cfunc) {
                            $cfg = $categConfigFunction($site,$newCateg);
                            if (isset($cfg['display'])) {
                                $display = $cfg['display'];
                            }
                        }
                        if ($ic>0) {
                            $ctg[$ic-1]['end'] = $i-1;
                        }
                        $ctg[] = array('categ' => $newCateg, 'start' => $i, 'end' => 0, 'display' => $display, 'cfg' => $cfg);
                        $ic++;
                    }
                    $categ = $newCateg;
                }

                if ($ic>0) {
                    $ctg[$ic-1]['end'] = $i-1;
                }

                $nbc = count($ctg);
                $ig0 = count($this->groupResults);

                for ($i = 0;$i < $nbc;$i++) {
                    $categ = $ctg[$i]['categ'];
                    $categ = ($categ) ? $categ : UNCATEGORIZED;
                    $display = $ctg[$i]['display'];
                    $start = $ctg[$i]['start'];
                    $nbrsg = $ctg[$i]['end'] - $ctg[$i]['start'] + 1;
                    $cfg = $ctg[$i]['cfg'];

                    if ($display == UNMIXED) {
                        $ig = count($this->groupResults);
                        $this->asCfg->addConfigFromCateg($site, $categ, $cfg);
                        $this->asCfg->chooseConfig($site, $categ, $display);
                        $ucfg = $this->asCfg->setAsCall($this->asCfg->getUserConfig());
                        $this->groupResults[$ig] = $this->_setHeaderGroupResults($site, $categ, $display, $ucfg, $select, $nbrsg);
                        $grpresults = array_slice($rs,$start,$nbrsg);
                        $this->groupResults[$ig]['results'] = $this->_sortResultsByRank($this->asCtrl->searchString, $this->asCtrl->advSearch, $grpresults, $nbrsg);
                        $this->nbGroups = $ig + 1;
                        $this->asCfg->restoreConfig($site, DEFAULT_SUBSITE);

                        if ($this->dbgRes) $this->asUtil->dbgRecord($this->groupResults[$ig], "AjaxSearch - group results");

                    } else {
                        if (!isset($this->_groupMixedResults['length'])) {
                            $this->_groupMixedResults = $this->_setHeaderGroupResults(NO_NAME, $subsite, $display, 'N/A', 'N/A', $nbrsg);
                        } else {
                            $this->_groupMixedResults['length']+= $nbrsg;
                        }
                        $order_array = explode(',', $this->asCfg->cfg['order']);
                        $order = $order_array[0];
                        for($j=0; $j<$nbrsg; $j++) {
                            $grpresults[$j]['order'] = $grpresults[$j][$order];
                            $this->_groupMixedResults['results'][] = $rs[$j];
                        }

                        if ($this->dbgRes) {
                            $this->asUtil->dbgRecord($this->groupResults, "AjaxSearch - group results");
                        }
                    }
                }
            } else {
                $ig = count($this->groupResults);
                $ucfg = $this->asCfg->setAsCall($this->asCfg->getUserConfig());
                $this->groupResults[$ig] = $this->_setHeaderGroupResults($site, $subsite, $display, $ucfg, $select, $nbrs);
                if ($this->dbgRes) {
                    $this->asUtil->dbgRecord($rs, "AjaxSearch - rs");
                }
                $rs = $this->_sortResultsByRank($this->asCtrl->searchString, $this->asCtrl->advSearch, $rs, $nbrs);
                $this->groupResults[$ig]['results'] = $rs;
                $this->nbGroups = $ig + 1;
            }
            unset($ctg);
        }
        $this->nbResults += $nbrs;
    }

    /**
     * Initialize the Extract variables
     */
    public function _initExtractVariables() {
        list($nbExtr,$lstFlds) = explode(':', $this->asCfg->cfg['extract']);
        $this->extractNb = $nbExtr;
        $this->_extractFields = explode(',', $lstFlds);
        $this->withExtract+= $this->extractNb;
    }

    /**
     * Set the header of group of results
     *
     * @param $site
     * @param $subsite
     * @param $display
     * @param $ucfg
     * @param $select
     * @param $length
     * @return array
     */
    public function _setHeaderGroupResults($site, $subsite, $display, $ucfg, $select, $length) {
        $headerGroupResults = array();
        $headerGroupResults['site'] = $site;
        $headerGroupResults['subsite'] = $subsite;
        $headerGroupResults['display'] = $display;
        $headerGroupResults['offset'] = 0;
        $headerGroupResults['ucfg'] = $ucfg;
        $headerGroupResults['select'] = $select;
        $headerGroupResults['length'] = $length;
        $headerGroupResults['found'] = '';
        return $headerGroupResults;
    }

    /**
     * Sort results by rank value
     *
     * @param $searchString
     * @param $advSearch
     * @param $results
     * @param $nbrs
     * @return mixed
     */
    public function _sortResultsByRank($searchString, $advSearch, $results, $nbrs) {
        $rkFields = array();
        if ($this->asCfg->cfg['rank']) {
            $searchString = strtolower($searchString);

            $rkParam = explode(',', $this->asCfg->cfg['rank']);
            foreach ($rkParam as $rk) {
                $rankParam = explode(':', $rk);
                $name = $rankParam[0];
                $weight = (isset($rankParam[1]) ? $rankParam[1] : 1);
                $rkFields[] = array('name' => $name, 'weight' => $weight);
            }

            for ($i = 0;$i < $nbrs;$i++) {
                $results[$i]['rank'] = 0;
                foreach ($rkFields as $rf) {
                    $results[$i]['rank']+= $this->_getRank($searchString, $advSearch, $results[$i][$rf['name']], $rf['weight']);
                }
            }
            if ($nbrs >1) {

                $i = 0;
                foreach ($results as $key => $row) {
                    $category[$key] = $row['category'];
                    $rank[$key] = $row['rank'];
                    $ascOrder[$key] = $i++;
                }
                array_multisort($category, SORT_ASC, $rank, SORT_DESC, $ascOrder, SORT_ASC, $results);
            }
        }
        return $results;
    }

    /**
     * Get the rank value
     *
     * @param $searchString
     * @param $advSearch
     * @param $field
     * @param $weight
     * @return float|int
     */
    public function _getRank($searchString, $advSearch, $field, $weight) {
        $search = array();
        $rank = 0;
        if ($searchString && ($advSearch != NOWORDS)) {
            switch ($advSearch) {
                case EXACTPHRASE:
                    $search[0] = $searchString;
                    break;
                case ALLWORDS:
                case ONEWORD:
                    $search = explode(" ", $searchString);
            }
            $field = $this->cleanText($field, $this->asCfg->cfg['stripOutput']);
            if (($this->asCfg->dbCharset == 'utf8') && ($this->asCfg->cfg['mbstring'])) {
                $field = mb_strtolower($field);
                foreach ($search as $srch) $rank+= mb_substr_count($field, $srch);
            } else {
                $field = strtolower($field);
                foreach ($search as $srch) $rank+= substr_count($field, $srch);
            }
            $rank = $rank * $weight;
        }
        return $rank;
    }

    /**
     * Sort noName results by order
     */
    public function _sortMixedResults() {
        if (isset($this->_groupMixedResults['results'])) {
            foreach ($this->_groupMixedResults['results'] as $key => $row) {
                $order[$key] = $row['order'];
            }
            array_multisort($order, SORT_ASC, $this->_groupMixedResults['results']);
            $this->groupResults[] = $this->_groupMixedResults;
            $this->nbGroups++;
            if ($this->dbgRes) $this->asUtil->dbgRecord($this->_groupMixedResults['results'], "AjaxSearch - sorted noName results");
        }
    }

    /**
     * Check the validity of a value separated list of TVs name
     *
     * @param $listTvs
     * @param $msgErr
     * @return bool
     */
    public function _validListTvs($listTvs, &$msgErr) {
        global $modx;
        if ($listTvs) {
            $tvs = explode(',', $listTvs);
            $tblName = $modx->getFullTableName('site_tmplvars');
            foreach ($tvs as $tv) {
                $tplRS = $modx->db->select('count(id)', $tblName, "name='{$tv}'");
                if (!$modx->db->getValue($tplRS)) {
                    $msgErr = "<br /><h3>AjaxSearch error: tv $tv not defined - Check your withTvs parameter !</h3><br />";
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Returns extracts with highlighted searchterms
     *
     * @param $text
     * @param $searchString
     * @param $advSearch
     * @param $highlightClass
     * @param $nbExtr
     * @return string
     */
    public function _getExtract($text, $searchString, $advSearch, $highlightClass, &$nbExtr) {
        $finalExtract = '';
        if (($text !== '') && ($searchString !== '') && ($this->extractNb > 0) && ($advSearch !== NOWORDS)) {
            $extracts = array();
            if (($this->asCfg->dbCharset == 'utf8') && ($this->asCfg->cfg['mbstring'])) {
                $text = $this->_html_entity_decode($text, ENT_QUOTES, 'UTF-8');
                $mbStrpos = 'mb_strpos';
                $mbStrlen = 'mb_strlen';
                $mbStrtolower = 'mb_strtolower';
                $mbSubstr = 'mb_substr';
                $mbStrrpos = 'mb_strrpos';
                mb_internal_encoding('UTF-8');
            } else {

                $text = html_entity_decode($text, ENT_QUOTES);
                $mbStrpos = 'strpos';
                $mbStrlen = 'strlen';
                $mbStrtolower = 'strtolower';
                $mbSubstr = 'substr';
                $mbStrrpos = 'strrpos';
            }
            $rank = 0;
            // $lookAhead = '(?![^<]+>)';
            $pcreModifier = $this->asCfg->pcreModifier;
            $textLength = $mbStrlen($text);
            $extractLength = $this->asCfg->cfg['extractLength'];
            $extractLength2 = $extractLength / 2;
            $searchList = $this->asCtrl->getSearchWords($searchString, $advSearch);
            foreach ($searchList as $searchTerm) {
                $rank++;
                $wordLength = $mbStrlen($searchTerm);
                $wordLength2 = $wordLength / 2;
                // $pattern = '/' . preg_quote($searchTerm, '/') . $lookAhead . '/' . $pcreModifier;
                if ($advSearch == EXACTPHRASE) $pattern = '/(\b|\W)' . preg_quote($searchTerm, '/') . '(\b|\W)/' . $pcreModifier;
                else $pattern = '/' . preg_quote($searchTerm, '/') . '/' . $pcreModifier;
                $matches = array();
                $nbr = preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);

                for($i=0;$i<$nbr && $i<$this->extractNb;$i++) {
                    $wordLeft = $mbStrlen(substr($text,0,$matches[0][$i][1]));
                    $wordRight = $wordLeft + $wordLength - 1;
                    $left = intval($wordLeft - $extractLength2 + $wordLength2);
                    $right = $left + $extractLength - 1;
                    if ($left < 0) $left = 0;
                    if ($right > $textLength) $right = $textLength;
                    $extracts[] = array('word' => $searchTerm,
                        'wordLeft' => $wordLeft,
                        'wordRight' => $wordRight,
                        'rank' => $rank,
                        'left' => $left,
                        'right' => $right,
                        'etcLeft' => $this->asCfg->cfg['extractEllips'],
                        'etcRight' => $this->asCfg->cfg['extractEllips']
                    );
                }
            }

            $nbExtr = count($extracts);
            if ($nbExtr > 1) {
                for ($i = 0;$i < $nbExtr;$i++) {
                    $lft[$i] = $extracts[$i]['left'];
                    $rght[$i] = $extracts[$i]['right'];
                }
                array_multisort($lft, SORT_ASC, $rght, SORT_ASC, $extracts);

                for ($i = 0;$i < $nbExtr;$i++) {
                    $begin = $mbSubstr($text, 0, $extracts[$i]['left']);
                    if ($begin != '') {
                        $extracts[$i]['left'] = (int)$mbStrrpos($begin, ' ');
                    }

                    $end = $mbSubstr($text, $extracts[$i]['right'] + 1, $textLength - $extracts[$i]['right']);
                    if ($end != '') {
                        $dr = (int)$mbStrpos($end, ' ');
                    }
                    if (is_int($dr)) {
                        $extracts[$i]['right']+= $dr + 1;
                    }
                }
                if ($extracts[0]['left'] == 0) {
                    $extracts[0]['etcLeft'] = '';
                }
                for ($i = 1;$i < $nbExtr;$i++) {
                    if ($extracts[$i]['left'] < $extracts[$i - 1]['wordRight']) {
                        $extracts[$i - 1]['right'] = $extracts[$i - 1]['wordRight'];
                        $extracts[$i]['left'] = $extracts[$i - 1]['right'] + 1;
                        $extracts[$i - 1]['etcRight'] = $extracts[$i]['etcLeft'] = '';
                    } else if ($extracts[$i]['left'] < $extracts[$i - 1]['right']) {
                        $extracts[$i - 1]['right'] = $extracts[$i]['left'];
                        $extracts[$i - 1]['etcRight'] = $extracts[$i]['etcLeft'] = '';
                    }
                }
            }

            for ($i = 0;$i < $nbExtr;$i++) {
                $separation = ($extracts[$i]['etcRight'] != '') ? $this->asCfg->cfg['extractSeparator'] : '';
                $extract = $mbSubstr($text, $extracts[$i]['left'], $extracts[$i]['right'] - $extracts[$i]['left'] + 1);
                if ($this->asCfg->cfg['highlightResult']) {
                    $rank = $extracts[$i]['rank'];
                    $searchTerm = $searchList[$rank - 1];
                    if ($advSearch == EXACTPHRASE) {
                        $pattern = '/(\b|\W)' . preg_quote($searchTerm, '/') . '(\b|\W)/' . $pcreModifier;
                    } else {
                        $pattern = '/' . preg_quote($searchTerm, '/') . '/' . $pcreModifier;
                    }
                    $subject = '<span class="' . $highlightClass . ' ' . $highlightClass . $rank . '">\0</span>';
                    $extract = preg_replace($pattern, $subject, $extract);
                }
                $finalExtract.= $extracts[$i]['etcLeft'] . $extract . $extracts[$i]['etcRight'] . $separation;
            }
            $finalExtract = $mbSubstr($finalExtract, 0, $mbStrlen($finalExtract) - $mbStrlen($this->asCfg->cfg['extractSeparator']));
        } else if ((($text !== '') && ($searchString !== '') && ($this->extractNb > 0) && ($advSearch == NOWORDS)) ||
            (($text !== '') && ($searchString == '') && ($this->extractNb > 0))) {

            if (($this->asCfg->dbCharset == 'utf8') && ($this->asCfg->cfg['mbstring'])) {
                $mbSubstr = 'mb_substr';
                $mbStrrpos = 'mb_strrpos';
                mb_internal_encoding('UTF-8');
            } else {
                $mbSubstr = 'substr';
                $mbStrrpos = 'strrpos';
            }
            $introLength = $this->asCfg->cfg['extractLength'];
            $intro = $mbSubstr($text,0,$introLength);

            $right = (int) $mbStrrpos($intro, ' ');
            $intro = $mbSubstr($intro,0,$right);
            if ($intro) $intro .= ' ' . $this->asCfg->cfg['extractEllips'];
            $finalExtract = $intro;
        }

        return $finalExtract;
    }

    /**
     * Get the extract result from each row
     *
     * @param array $row
     * @return null|string|string[]
     */
    public function getExtractRow($row) {
        $text = '';
        $nbExtr = 0;
        if ($this->extractNb) {
            foreach ($this->_extractFields as $f) {
                if ($row[$f]) {
                    $text.= $row[$f] . ' ';
                }
            }

            $text = $this->cleanText($text, $this->asCfg->cfg['stripOutput']);
            $highlightClass = $this->asOutput->getHClass();
            $text = $this->_getExtract($text, $this->asCtrl->searchString, $this->asCtrl->advSearch, $highlightClass, $nbExtr);
        }
        return $text;
    }

    /**
     * Strip function to clean outputted results
     *
     * @param $text
     * @param $stripOutput
     * @return null|string|string[]
     */
    public function cleanText($text, $stripOutput) {
        return ($stripOutput && function_exists($stripOutput)) ? $stripOutput($text) : $this->defaultStripOutput($text);
    }

    /**
     * Return the sign and the list of Ids used for the search (parents & documents)
     *
     * @return array
     */
    public function _doBeforeSearchFilter() {
        $beforeFilter = array();

        list($fsign,$listIds) = explode(':',$this->_pardoc . ':');
        if (($fsign != 'in') && ($fsign != 'not in')) {
            $listIds = $fsign;
            $fsign = 'in';
        }
        $beforeFilter['oper'] = ($fsign == 'in') ? 'in' : 'not in';
        if ($listIds != '') {
            $listIds = $this->_cleanIds($listIds);
        }
        if (strlen($listIds)) {
            switch ($this->_idType) {
                case "parents":
                    $arrayIds = explode(",", $listIds);
                    $listIds = implode(',', $this->_getChildIds($arrayIds, $this->_depth));
                    break;
                case "documents":
                    break;
            }
        }
        $beforeFilter['listIds'] = $listIds;
        return $beforeFilter;
    }

    /**
     * Filter the search results
     *
     * @param $results
     * @param $searchString
     * @param $advSearch
     * @return array
     */
    public function _doFilter($results, $searchString, $advSearch) {
        $globalDelimiter = '|';
        $localDelimiter = ',';

        $results = $this->_doFilterTags($results, $searchString, $advSearch);

        $filter = $this->asCfg->cfg['filter'];
        if ($filter) {
            $searchString_array = array();
            if ($advSearch == EXACTPHRASE) $searchString_array[] = $searchString;
            else $searchString_array = explode(' ', $searchString);
            $nbs = count($searchString_array);
            $filter_array = explode('|', $filter);
            $nbf = count($filter_array);
            for ($i = 0;$i < $nbf;$i++) {
                if (preg_match('/#/', $filter_array[$i])) {
                    $terms_array = explode(',', $filter_array[$i]);
                    if ($searchString == EXACTPHRASE) $filter_array[$i] = preg_replace('/#/i', $searchString, $filter_array[$i]);
                    else {
                        $filter_array[$i] = preg_replace('/#/i', $searchString_array[0], $filter_array[$i]);
                        for ($j = 1;$j < $nbs;$j++) {
                            $filter_array[] = $terms_array[0] . ',' . $searchString_array[$j] . ',' . $terms_array[2];
                        }
                    }
                }
            }
            $filter = implode('|', $filter_array);
            $parsedFilters = array();
            $filters = explode($globalDelimiter, $filter);
            if ($filter && count($filters) > 0) {
                foreach ($filters AS $filter) {
                    if (!empty($filter)) {
                        $filterArray = explode($localDelimiter, $filter);
                        $this->_array_key = $filterArray[0];
                        if (substr($filterArray[1], 0, 5) != "@EVAL") {
                            $this->_filterValue = $filterArray[1];
                        } else {
                            $this->_filterValue = eval(substr($filterArray[1], 6));
                        }
                        $this->_filtertype = (isset($filterArray[2])) ? $filterArray[2] : 1;
                        $results = array_filter($results, array($this, "_basicFilter"));
                    }
                }
            }
            $results = array_values($results);
        }
        return $results;
    }

    /**
     * Do basic comparison filtering
     *
     * @param $value
     * @return int
     */
    public function _basicFilter($value) {
        $unset = 1;
        switch ($this->_filtertype) {
            case "!=":
            case 1:
                if (!isset($value[$this->_array_key]) || $value[$this->_array_key] != $this->_filterValue) $unset = 0;
                break;
            case "==":
            case 2:
                if ($value[$this->_array_key] == $this->_filterValue) $unset = 0;
                break;
            case "<":
            case 3:
                if ($value[$this->_array_key] < $this->_filterValue) $unset = 0;
                break;
            case ">":
            case 4:
                if ($value[$this->_array_key] > $this->_filterValue) $unset = 0;
                break;
            case "<=":
            case 5:
                if (!($value[$this->_array_key] < $this->_filterValue)) $unset = 0;
                break;
            case ">=":
            case 6:
                if (!($value[$this->_array_key] > $this->_filterValue)) $unset = 0;
                break;
            case "not like":
            case 7: // does not contain the text of the criterion (like)
                if (strpos($value[$this->_array_key], $this->_filterValue) === FALSE) $unset = 0;
                break;
            case "like":
            case 8: // does contain the text of the criterion (not like)
                if (strpos($value[$this->_array_key], $this->_filterValue) !== FALSE) $unset = 0;
                break;
            case 9: // case insenstive version of #7 - exclude records that do not contain the text of the criterion
                if (strpos(strtolower($value[$this->_array_key]), strtolower($this->_filterValue)) === FALSE) $unset = 0;
                break;
            case 10: // case insenstive version of #8 - exclude records that do contain the text of the criterion
                if (strpos(strtolower($value[$this->_array_key]), strtolower($this->_filterValue)) !== FALSE) $unset = 0;
                break;
            case "in":
            case 11: // in list
                $filter_list = explode(':',$this->_filterValue);
                if (in_array($value[$this->_array_key] , $filter_list)) $unset = 0;
                break;
            case "not in":
            case 12: // not in list
                $filter_list = explode(':',$this->_filterValue);
                if (!in_array($value[$this->_array_key] , $filter_list)) $unset = 0;
                break;
            case "custom":
            case 13: // custom
                $custom_list = explode(':',$this->_filterValue);
                $custom = array_shift($custom_list);
                if (function_exists($custom)) {
                    if ($custom($value[$this->_array_key], $custom_list)) $unset = 0;
                }
                break;
        }
        return $unset;
    }

    /**
     * Get the Ids ready to be processed
     *
     * @param $Ids
     * @param $depth
     * @return array
     */
    public function _getChildIds($Ids, $depth) {
        global $modx;
        $depth = intval($depth);
        $kids = array();
        $docIds = array();
        if ($depth == 0 && $Ids[0] == 0 && count($Ids) == 1) {
            foreach ($modx->documentMap as $null => $document) {
                foreach ($document as $parent => $id) {
                    $kids[] = $id;
                }
            }
            return $kids;
        } elseif ($depth == 0) {
            $depth = 10000;
        }
        foreach ($modx->documentMap as $null => $document) {
            foreach ($document as $parent => $id) {
                $kids[$parent][] = $id;
            }
        }
        foreach ($Ids AS $seed) {
            if (!empty($kids[intval($seed) ])) {
                $docIds = array_merge($docIds, $kids[intval($seed)]);
                unset($kids[intval($seed)]);
            }
        }
        $depth--;
        while ($depth != 0) {
            $valid = $docIds;
            foreach ($docIds as $child => $id) {
                if (!empty($kids[intval($id) ])) {
                    $docIds = array_merge($docIds, $kids[intval($id)]);
                    unset($kids[intval($id)]);
                }
            }
            $depth--;
            if ($valid == $docIds) $depth = 0;
        }
        return array_unique($docIds);
    }

    /**
     * Clean Ids list of unwanted characters
     *
     * @param $Ids
     * @return null|string|string[]
     */
    public function _cleanIds($Ids) {

        $pattern = array('`(,)+`',
            '`^(,)`',
            '`(,)$`'
        );
        $replace = array(',', '', '');
        $Ids = preg_replace($pattern, $replace, $Ids);
        return $Ids;
    }

    /**
     * Filter the search results when the search terms are found inside HTML or MODX tags
     *
     * @param $results
     * @param $searchString
     * @param $advSearch
     * @return array
     */
    public function _doFilterTags($results, $searchString, $advSearch) {
        $filteredResults = array();
        $nbr = count($results);
        for($i=0;$i<$nbr;$i++) {
            if ($advSearch === NOWORDS) $found = true;
            else {
                $text = implode(' ',$results[$i]);
                $text = $this->defaultStripOutput($text);
                $found = true;
                if ($searchString !== '') {
                    if (($this->asCfg->dbCharset == 'utf8') && ($this->asCfg->cfg['mbstring'])) {
                        $text = $this->_html_entity_decode($text, ENT_QUOTES, 'UTF-8');
                    }
                    else {
                        $text = html_entity_decode($text, ENT_QUOTES);
                    }

                    $searchList = $this->asCtrl->getSearchWords($searchString, $advSearch);
                    $pcreModifier = $this->asCfg->pcreModifier;
                    foreach ($searchList as $searchTerm) {
                        if ($advSearch == EXACTPHRASE) $pattern = '/(\b|\W)' . preg_quote($searchTerm, '/') . '(\b|\W)/' . $pcreModifier;
                        else $pattern = '/' . preg_quote($searchTerm, '/') . '/' . $pcreModifier;
                        $matches = array();
                        $found = preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
                        if ($found) break;
                    }
                }
            }
            if ($found) $filteredResults[] = $results[$i];
        }
        return $filteredResults;
    }

    /**
     * Get the array of categories found
     *
     * @return array
     */
    public function getResultsCateg() {
        $resCategName = array();
        $resCategNb = array();
        for ($i = 0;$i < $this->nbGroups;$i++) {
            $resCategName[$i] = "'" . $this->groupResults[$i]['subsite'] . "'";
            $resCategNb[$i] = $this->groupResults[$i]['length'];
        }
        return array("name" => $resCategName, "nb" => $resCategNb);
    }

    /**
     * Get the array of tags found
     *
     * @return array
     */
    public function getResultsTag() {
        $tags = array();
        $resResTag = array();
        $resTagName = array();
        $resTagNb = array();
        $indTag = array();

        for ($i = 0;$i < $this->nbGroups;$i++) {
            $categ = $this->groupResults[$i]['subsite'];
            $nbr = $this->groupResults[$i]['length'];
            $results = $this->groupResults[$i]['results'];
            for ($j = 0;$j < $nbr; $j++) {
                $tags_array = explode(',',$results[$j]['tags']);
                foreach($tags_array as $tagv) {
                    $tv = ($tagv) ? (string) (trim($tagv)) : UNTAGGED;
                    $tags[$tv][]= $i . ',' . $j;
                    $resResTag[$i][$j][] = $tv;
                }
            }
        }
        $itag = 0;
        foreach($tags as $key => $value) {
            $resTagName[] = "'" . $key . "'";
            $resTagNb[] = count($tags[$key]);
            $indTag[$key] = $itag;
            $itag++;
        }
        for ($i = 0;$i < $this->nbGroups;$i++) {
            $nbr = $this->groupResults[$i]['length'];
            for ($j = 0;$j < $nbr; $j++) {
                $nbt = count($resResTag[$i][$j]);
                for ($t = 0;$t < $nbt; $t++) {
                    $resResTag[$i][$j][$t] = $indTag[$resResTag[$i][$j][$t]];
                }
            }
        }
        return array("name" => $resTagName, "nb" => $resTagNb, "restag" => $resResTag);
    }

    /**
     * Default ouput strip function
     *
     * @param $text
     * @return null|string|string[]
     */
    public function defaultStripOutput($text) {
        global $modx;

        if ($text !== '') {
            // $text = $modx->parseDocumentSource($text); // parse document

            $text = $this->stripLineBreaking($text);

            $text = $modx->stripTags($text);

            $text = $this->stripJscripts($text);

            $text = $this->stripHtml($text);
        }
        return $text;
    }

    /**
     * replace line breaking tags with whitespace
     *
     * @param $text
     * @return null|string|string[]
     */
    public function stripLineBreaking($text) {

        $text = preg_replace("'<(br[^/>]*?/|hr[^/>]*?/|/(div|h[1-6]|li|p|td))>'si", ' ', $text);
        return $text;
    }

    /**
     * Remove MODX sensitive tags
     *
     * @param $text
     * @return null|string|string[]
     */
    public function stripTags($text) {

        $modRegExArray[] = '~\[\[(.*?)\]\]~s';
        $modRegExArray[] = '~\[\!(.*?)\!\]~s';
        $modRegExArray[] = '#\[\~(.*?)\~\]#s';
        $modRegExArray[] = '~\[\((.*?)\)\]~s';
        $modRegExArray[] = '~{{(.*?)}}~s';
        $modRegExArray[] = '~\[\*(.*?)\*\]~s';
        $modRegExArray[] = '~\[\+(.*?)\+\]~s';

        foreach ($modRegExArray as $mReg) $text = preg_replace($mReg, '', $text);
        return $text;
    }

    /**
     * Remove jscript
     *
     * @param $text
     * @return null|string|string[]
     */
    public function stripJscripts($text) {

        $text = preg_replace("'<script[^>]*>.*?</script>'si", "", $text);
        $text = preg_replace('/{.+?}/', '', $text);
        return $text;
    }

    /**
     * Remove HTML sensitive tags
     *
     * @param $text
     * @return string
     */
    public function stripHtml($text) {
        return strip_tags($text);
    }

    /**
     * Remove HTML sensitive tags except image tag
     *
     * @param $text
     * @return string
     */
    public function stripHtmlExceptImage($text) {
        $text = strip_tags($text, '<img>');
        return $text;
    }

    /**
     * @return mixed
     */
    public function getSearchContext() {
        // return the search context
        $searchContext['main'] = $this->_asRequest->scMain;
        $searchContext['joined'] = $this->_asRequest->scJoined;
        $searchContext['tvs'] = $this->_asRequest->scTvs;
        $searchContext['category'] = $this->_asRequest->scCategory;
        $searchContext['tags'] = $this->_asRequest->scTags;

        return $searchContext;
    }

    /**
     * return the withContent boolean value
     *
     * @return mixed
     */
    public function getWithContent() {
        return $this->_asRequest->withContent;
    }

    /**
     * @param $text
     * @param int $quote_style
     * @param $charset
     * @return null|string|string[]
     */
    public function _html_entity_decode($text, $quote_style = ENT_COMPAT, $charset)
    {
        return html_entity_decode($text, $quote_style, $charset);
    }
}
