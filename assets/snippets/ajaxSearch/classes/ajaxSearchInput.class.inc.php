<?php
/**
 * -----------------------------------------------------------------------------
 * Snippet: AjaxSearch
 * -----------------------------------------------------------------------------
 * @package  AjaxSearchInput
 *
 * @author       Coroico - www.evo.wangba.fr
 * @version      1.12.1
 * @date         27/10/2018
 *
 * Purpose:
 *    The AjaxSearchInput class contains all functions and data used to manage Input form
 *
 */

class AjaxSearchInput
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
     * @var AjaxSearchUtil
     */
    public $asUtil;
    public $dbg = false;
    public $dbgTpl = false;

    public $inputForm = '';
    public $asfForm = '';

    private $advSearchType = array(ONEWORD, ALLWORDS, EXACTPHRASE, NOWORDS);

    /**
     * Initializes the class into the proper context
     *
     * @param AjaxSearchConfig &$asCfg
     * @param AjaxSearchCtrl &$asCtrl
     * @param AjaxSearchUtil &$asUtil
     */
    public function init(&$asCfg, &$asCtrl, &$asUtil)
    {
        $this->asCfg =& $asCfg;
        $this->asCtrl =& $asCtrl;
        $this->asUtil =& $asUtil;
        $this->dbg = $asUtil->dbg;
        $this->dbgTpl = $asUtil->dbgTpl;
    }

    /**
     * Set up the input form
     *
     * @param string &$msgErr message error
     * @return bool
     */
    public function display(&$msgErr)
    {
        $msgErr = '';
        $this->_checkParams();
        $valid = $this->_validSearchString($msgErr);
        $this->_displayInputForm();
        $this->_setClearDefaultHeader();

        return $valid;
    }

    /**
     * Check input field params
     */
    public function _checkParams()
    {
        if ($this->asCtrl->forThisAs) {
            if (isset($this->asCfg->cfg['maxWords'])) {
                if ($this->asCfg->cfg['maxWords'] < MIN_WORDS) {
                    $this->asCfg->cfg['maxWords'] = MIN_WORDS;
                }
                if ($this->asCfg->cfg['maxWords'] > MAX_WORDS) {
                    $this->asCfg->cfg['maxWords'] = MAX_WORDS;
                }
            }
            if (isset($this->asCfg->cfg['minChars'])) {
                if ($this->asCfg->cfg['minChars'] < MIN_CHARS) {
                    $this->asCfg->cfg['minChars'] = MIN_CHARS;
                }
                if ($this->asCfg->cfg['minChars'] > MAX_CHARS) {
                    $this->asCfg->cfg['minChars'] = MAX_CHARS;
                }
            }
        }
    }

    /**
     * Valid the input search term and the advSearch parameter
     *
     * @param $msgErr
     * @return bool
     */
    public function _validSearchString(&$msgErr)
    {
        global $modx;
        $msgErr = '';
        $searchString = $this->asCtrl->searchString;
        $advSearch = $this->asCtrl->advSearch;

        $advSearch = in_array($advSearch, $this->advSearchType) ? $advSearch : $this->advSearchType[0];

        $valid = $this->_checkSearchString($searchString, $advSearch, $msgErr);
        if (!$valid) {
            return false;
        }

        $valid = $this->_stripSearchString($searchString, $this->asCfg->cfg['stripInput'], $advSearch, $msgErr);
        if (!$valid) {
            return false;
        }

        $modx->setPlaceholder("as.searchString", $searchString);

        $this->asCtrl->setSearchString($searchString);
        $this->asCtrl->setAdvSearch($advSearch);

        return true;
    }

    /**
     * Check search string
     *
     * @param $searchString
     * @param $advSearch
     * @param $msgErr
     * @return bool
     */
    public function _checkSearchString(&$searchString, $advSearch, &$msgErr)
    {
        if (!$this->asCtrl->forThisAs) {
            $msgErr = '';
            return false;
        }
        if ($this->dbg) {
            $this->asUtil->dbgRecord($searchString, "checkSearchString - searchString");
        }

        $searchSubmitted = (isset($_POST['search']) || isset($_GET['search']));
        if ($this->dbg) {
            $this->asUtil->dbgRecord($searchSubmitted, "checkSearchString - searchSubmitted");
        }
        $asfSubmitted = (isset($_POST['asf']) || isset($_GET['asf']));
        if ($this->dbg) {
            $this->asUtil->dbgRecord($asfSubmitted, "checkSearchString - asfSubmitted");
        }
        $checkString = true;

        if ($searchSubmitted) {
            if ($searchString == $this->asCfg->lang['as_boxText']) {   // "search here ..."
                if ($this->asCfg->cfg['init'] === 'all') { // display all
                    $checkString = false;
                    $searchString = '';
                } else {  // check the empty input field => error message (At least 3 characters)
                    $searchString = '';
                    if ($asfSubmitted) {
                        // no check if a filter is submitted
                        $checkString = false;
                    }
                }
            } elseif ($searchString === '' && $this->asCfg->cfg['init'] === 'all') {
                $checkString = false;
            } elseif ($searchString === '' && $this->asCfg->cfg['init'] === 'none' && $asfSubmitted) {
                $checkString = false;
            }
        } else {
            if ($searchString == '') {
                if ($this->asCfg->cfg['init'] === 'none' && (!$asfSubmitted)) {
                    $msgErr = '';
                    return false;
                } else {
                    $checkString = false;
                }
            }
        }

        if ($this->dbg) {
            $this->asUtil->dbgRecord($checkString, "checkSearchString - checkString");
            $this->asUtil->dbgRecord($searchString, "checkSearchString - searchString used");
        }

        if ($checkString) {
            $words_array = explode(' ', preg_replace('/\s\s+/', ' ', trim($searchString)));
            $mbStrlen = $this->asCfg->cfg['mbstring'] ? 'mb_strlen' : 'strlen';
            if ($this->asCfg->dbCharset == 'utf8' && $this->asCfg->cfg['mbstring']) {
                mb_internal_encoding("UTF-8");
            }

            if (count($words_array) > $this->asCfg->cfg['maxWords']) {
                $msgErr = sprintf($this->asCfg->lang['as_maxWords'], $this->asCfg->cfg['maxWords']);
                return false;
            }

            if ($advSearch == EXACTPHRASE) {
                if ($mbStrlen($searchString) < $this->asCfg->cfg['minChars']) {
                    $msgErr = sprintf($this->asCfg->lang['as_minChars'], $this->asCfg->cfg['minChars']);
                    return false;
                }
                if ($mbStrlen($searchString) > MAX_CHARS) {
                    $msgErr = sprintf($this->asCfg->lang['as_maxChars'], MAX_CHARS);
                    return false;
                }
            } else {
                //oneword, allwords or nowords
                foreach ($words_array as $word) {
                    if ($mbStrlen($word) < $this->asCfg->cfg['minChars']) {
                        $msgErr = sprintf($this->asCfg->lang['as_minChars'], $this->asCfg->cfg['minChars']);
                        return false;
                    }
                    if ($mbStrlen($searchString) > MAX_CHARS) {
                        $msgErr = sprintf($this->asCfg->lang['as_maxChars'], MAX_CHARS);
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * Strip the searchString with the user StripInput function
     *
     * @param $searchString
     * @param $stripInput
     * @param $advSearch
     * @param $msgErr
     * @return bool
     */
    public function _stripSearchString(&$searchString, $stripInput, &$advSearch, &$msgErr)
    {
        $msgErr = '';
        if ($searchString !== '') {
            $searchString = preg_replace('/\s\s+/', ' ', trim($searchString));
            if ($stripInput !== 'defaultStripInput') {
                if (function_exists($stripInput)) {
                    $searchString = $stripInput($searchString, $advSearch);
                } else {
                    $msgErr = '<br />'.
                        '<h3>' .
                        'AjaxSearch error: strip input function $stripInput not defined in the configuration file: ' .
                        $this->asCfg->cfg['config'] . '!' .
                        '</h3>' .
                        '<br />';
                    return false;
                }
            } else {
                $searchString = $this->_defaultStripInput($searchString, $this->asCfg->pgCharset);
            }
            return ($searchString !== '' || $this->asCfg->cfg['init'] === 'all');
        }
        return true;
    }

    /**
     * Default user StripInput function
     *
     * @param $searchString
     * @param string $pgCharset
     * @return mixed|null|string|string[]
     */
    public function _defaultStripInput($searchString, $pgCharset = 'UTF-8')
    {
        global $modx;
        if ($searchString !== '') {
            $searchString = $this->_stripJscripts(stripslashes($searchString));
            $searchString = strip_tags($modx->stripTags($searchString));
            $searchString = $this->_htmlspecialchars($searchString, ENT_COMPAT, $pgCharset, false);
            if (function_exists('mb_convert_kana')) {
                $searchString = mb_convert_kana($searchString, 's', $pgCharset);
            }
        }
        return $searchString;
    }

    /**
     * Display the input form
     */
    public function _displayInputForm()
    {
        global $modx;
        $varInputForm = array();
        if ($this->asCfg->cfg['showInputForm']) {
            if (!class_exists('AsPhxParser')) {
                include_once AS_PATH . "classes/asPhxParser.class.inc.php";
            }

            $tplInputForm = $this->asCfg->cfg['tplInput'];
            $chkInputForm = new AsPhxParser($tplInputForm);
            if ($this->dbgTpl) {
                $this->asUtil->dbgRecord(
                    $chkInputForm->getTemplate($tplInputForm),
                    "tplInputForm template" . $tplInputForm
                );
            }

            if (isset($this->asCfg->cfg['landingPage']) && !is_bool($this->asCfg->cfg['landingPage'])) {
                $searchAction = "[~" . $this->asCfg->cfg['landingPage'] . "~]";
            } else {
                $searchAction = "[~" . $modx->documentIdentifier . "~]";
            }
            $varInputForm['showInputForm'] = '1';
            $varInputForm['formId'] = ($this->asCfg->cfg['asId'] != '') ? $this->asCfg->cfg['asId'] . '_ajaxSearch_form' : 'ajaxSearch_form';
            $varInputForm['formAction'] = $searchAction;

            if ($this->dbg) {
                $this->asUtil->dbgRecord($this->asCtrl->searchString, "displayInputForm - searchString");
            }

            $varInputForm['inputId'] = ($this->asCfg->cfg['asId'] != '') ? $this->asCfg->cfg['asId'] . '_ajaxSearch_input' : 'ajaxSearch_input';
            // simple input field - Not used if a multiple input list is used
            $varInputForm['inputValue'] = ($this->asCtrl->searchString == '' && $this->asCfg->lang['as_boxText'] != 'init' ) ? $this->asCfg->lang['as_boxText'] : $this->asCtrl->searchString;
            $varInputForm['inputOptions'] = ($this->asCfg->lang['as_boxText']) ? ' onfocus="this.value=(this.value==\'' . $this->asCfg->lang['as_boxText'] . '\')? \'\' : this.value ;"' : '';
            $varInputForm['asfId'] = ($this->asCfg->cfg['asId'] != '') ? $this->asCfg->cfg['asId'] . '_asf_form' : 'asf_form';

            //if (($this->asCtrl->searchString == 'init')) $this->asCtrl->setSearchString('');
            if ($this->dbg) $this->asUtil->dbgRecord($this->asCtrl->searchString, "displayInputForm - searchString");

            // Submit button
            if ($this->asCfg->cfg['liveSearch']) {
                $varInputForm['liveSearch'] = 1;
            } else {
                $varInputForm['liveSearch'] = 0;
                $varInputForm['submitId'] = ($this->asCfg->cfg['asId'] != '') ? $this->asCfg->cfg['asId'] . '_ajaxSearch_submit' : 'ajaxSearch_submit';
                $varInputForm['submitText'] = $this->asCfg->lang['as_searchButtonText'];
            }


            $varInputForm['advSearch'] = $this->asCtrl->advSearch;

            if ($this->asCfg->cfg['asId']) {
                $varInputForm['radioId'] = $this->asCfg->cfg['asId'] . '_ajaxSearch_radio';
                $varInputForm['onewordId'] = $this->asCfg->cfg['asId'] . '_radio_oneword';
                $varInputForm['allwordsId'] = $this->asCfg->cfg['asId'] . '_radio_allwords';
                $varInputForm['exactphraseId'] = $this->asCfg->cfg['asId'] . '_radio_exactphrase';
                $varInputForm['nowordsId'] = $this->asCfg->cfg['asId'] . '_radio_nowords';
            } else {
                $varInputForm['radioId'] = 'ajaxSearch_radio';
                $varInputForm['onewordId'] = 'radio_oneword';
                $varInputForm['allwordsId'] = 'radio_allwords';
                $varInputForm['exactphraseId'] = 'radio_exactphrase';
                $varInputForm['nowordsId'] = 'radio_nowords';
            }

            $varInputForm['oneword'] = ONEWORD;
            $varInputForm['onewordText'] = $this->asCfg->lang[ONEWORD];
            $varInputForm['onewordChecked'] = ($this->asCtrl->advSearch == ONEWORD ? 'checked ="checked"' : '');

            $varInputForm['allwords'] = ALLWORDS;
            $varInputForm['allwordsText'] = $this->asCfg->lang[ALLWORDS];
            $varInputForm['allwordsChecked'] = ($this->asCtrl->advSearch == ALLWORDS ? 'checked ="checked"' : '');

            $varInputForm['exactphrase'] = EXACTPHRASE;
            $varInputForm['exactphraseText'] = $this->asCfg->lang[EXACTPHRASE];
            $varInputForm['exactphraseChecked'] = ($this->asCtrl->advSearch == EXACTPHRASE ? 'checked ="checked"' : '');

            $varInputForm['nowords'] = NOWORDS;
            $varInputForm['nowordsText'] = $this->asCfg->lang[NOWORDS];
            $varInputForm['nowordsChecked'] = ($this->asCtrl->advSearch == NOWORDS ? 'checked ="checked"' : '');

            if (!$this->asCtrl->searchString) {
                if ($this->asCfg->cfg['showIntro']) {
                    $varInputForm['showIntro'] = 1;
                    $varInputForm['introMessage'] = $this->asCfg->lang['as_introMessage'];
                } else {
                    $varInputForm['showIntro'] = 0;
                }
            }

            if ($this->asCfg->cfg['asId']) {
                $varInputForm['showAsId'] = '1';
                $varInputForm['asName'] = 'asid';
                $varInputForm['asId'] = $this->asCfg->cfg['asId'];
            } else {
                $varInputForm['showAsId'] = '0';
            }

            $chkInputForm->AddVar("as", $varInputForm);
            $this->inputForm = $chkInputForm->Render() . "\n";

            if (isset($this->asCfg->cfg['tplAsf']) && $this->asCfg->cfg['tplAsf']) {
                $tplAsfForm = $this->asCfg->cfg['tplAsf'];
                $chkAsfForm = new AsPhxParser($tplAsfForm);
                if ($this->dbgTpl) {
                    $this->asUtil->dbgRecord(
                        $chkAsfForm->getTemplate($tplAsfForm),
                        'tplAsfForm template' . $tplAsfForm
                    );
                }
                $varAsfForm = array(
                    'asfId' => $this->asCfg->cfg['asId'] != '' ? $this->asCfg->cfg['asId'] . '_asf_form' : 'asf_form'
                );
                $chkAsfForm->AddVar("as", $varAsfForm);
                $this->asfForm = $chkAsfForm->Render() . "\n";
            }
        }
    }

    /**
     * set the clearDefault header
     */
    public function _setClearDefaultHeader()
    {
        global $modx;
        if ($this->asCfg->cfg['showInputForm'] && $this->asCfg->cfg['clearDefault']) {
            $modx->regClientStartupScript($this->asCfg->cfg['jsClearDefault']);
        }
    }

    /**
     * Manage the double_encode parameter added with version 5.2.3
     *
     * @param $string
     * @param int $quote_style
     * @param string $charset
     * @param bool $double_encode
     * @return mixed|string
     */
    public function _htmlspecialchars($string, $quote_style = ENT_COMPAT, $charset = 'UTF-8', $double_encode = true)
    {
        // The double_encode  parameter was added with version 5.2.3
        if (version_compare(PHP_VERSION, '5.2.3', '>=')) {
            $string = htmlspecialchars($string, $quote_style, $charset, $double_encode);
        } else {
            if ($double_encode === true) {
                $string = str_replace('&amp;', '&', $string);
            }
            $tmp = array('&' => '&amp;', '<' => '&lt;', '>' => '&gt;');
            if ($quote_style & ENT_NOQUOTES) {
                $tmp['"'] = '&quot';
            }
            if ($quote_style & ENT_QUOTES) {
                $tmp["'"] = '&#039;';
            }
            $string = str_replace(array_keys($tmp), array_values($tmp), $string);
        }
        return $string;
    }

    /**
     * Remove jscript
     *
     * @param string $text
     * @return string
     */
    public function _stripJscripts($text)
    {
        $text = preg_replace("'<script[^>]*>.*?</script>'si", '', $text);
        $text = preg_replace('/{.+?}/', '', $text);
        return $text;
    }
}
