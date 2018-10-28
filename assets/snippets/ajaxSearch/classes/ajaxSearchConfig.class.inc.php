<?php
/**
 * -----------------------------------------------------------------------------
 * Snippet: AjaxSearch
 * -----------------------------------------------------------------------------
 * @package  AjaxSearchConfig
 *
 * @author       Coroico - www.evo.wangba.fr
 * @version      1.12.1
 * @date         27/10/2018
 *
 * Purpose:
 *    The AjaxSearchConfig class contains all functions and data used to manage configuration context
 *
 */

class AjaxSearchConfig
{
    /**
     * @var null|string
     */
    public $pgCharset;
    /**
     * @var string|null
     */
    public $dbCharset;
    /**
     * @var bool
     */
    public $isAjax;
    public $cfg = array();
    public $dcfg = array();
    /**
     * @var array
     */
    public $ucfg;
    public $bcfg = array();
    public $scfg = array();
    /**
     * @var array
     */
    public $lang;
    public $pcreModifier = 'i';

    /**
     * Conversion code name between html page character encoding and Mysql character encoding
     * Some others conversions should be added if needed. Otherwise Page charset = Database charset
     */
    private $pageCharset = array(
        'utf8' => 'UTF-8',
        'latin1' => 'ISO-8859-1',
        'latin2' => 'ISO-8859-2',
        'cp1251' => 'windows-1251'
    );

    protected $allowed = array(
        'timeLimit',
        'language',
        'display',
        'maxWords',
        'minChars',
        'showInputForm',
        'showIntro',
        'extractLength',
        'extractSeparator',
        'highlightResult',
        'pagingType',
        'showPagingAlways',
        'pageLinkSeparator',
        'addJscript'
    );

    /**
     * AjaxSearchConfig constructor.
     * @param array $dcfg
     * @param null|array $cfg
     */
    public function __construct($dcfg, $cfg)
    {
        global $modx;
        $this->dbCharset = $modx->db->config['charset'];
        if ($this->dbCharset === 'utf8mb4') {
            $this->dbCharset = 'utf8';
        }
        $this->pcreModifier = $this->dbCharset === 'utf8' ? 'iu' : 'i';
        $this->dcfg = $dcfg;
        $this->cfg = $cfg;
    }

    /**
     * Init the configuration
     *
     * @param $msgErr
     * @return bool
     */
    public function initConfig(&$msgErr)
    {
        $msgErr = '';
        $this->isAjax = isset($_POST['ucfg']);
        if ($this->isAjax) {
            $config = is_scalar($_POST['ucfg']) ? strip_tags($_POST['ucfg']) : '';
            $this->ucfg = $this->parseUserConfig($config);
            $this->bcfg = array_merge($this->dcfg, $this->ucfg);
            $this->cfg = $this->bcfg;
        } else {
            $this->ucfg = $this->getUserConfig();
            $this->bcfg = array_merge($this->dcfg, $this->ucfg);
        }

        $this->scfg[DEFAULT_SITE][DEFAULT_SUBSITE] = array();

        $this->_loadLang();

        return $this->_setCharset($msgErr);
    }

    /**
     * Load the language file
     */
    public function _loadLang()
    {
        $_lang = array();

        $language = 'english';
        include AS_PATH . "lang/{$language}.inc.php";

        if (($this->cfg['language'] != '') && ($this->cfg['language'] != $language)) {
            if (file_exists(AS_PATH . "lang/{$this->cfg['language']}.inc.php")) {
                include AS_PATH . "lang/" . $this->cfg['language'] . ".inc.php";
            }
        }
        $this->lang = $_lang;
    }

    /**
     * Display config arrays
     *
     * @param $asUtil
     */
    public function displayConfig(& $asUtil)
    {
        if ($asUtil->dbg) {
            if ($this->cfg['config']) {
                $asUtil->dbgRecord(
                    $this->readConfigFile($this->cfg['config']),
                    __FUNCTION__ . ' - ' . $this->cfg['config']
                );
            }
            $asUtil->dbgRecord($this->cfg, __FUNCTION__ . ' - Config before parameter checking');
        }
    }

    /**
     * Set the Page charset
     *
     * @param $msgErr
     * @return bool
     */
    public function _setCharset(&$msgErr)
    {
        $valid = false;
        $msgErr = '';

        $this->pgCharset = array_key_exists($this->dbCharset, $this->pageCharset) ?
            $this->pageCharset[$this->dbCharset] : $this->dbCharset;

        if (!isset($this->dbCharset)) {
            $msgErr = 'AjaxSearch error: database_connection_charset not set. Check your MODX config file';
        } else {
            if (isset($this->pageCharset[$this->dbCharset])) {
                if ($this->dbCharset === 'utf8' && !extension_loaded('mbstring')) {
                    $msgErr = 'AjaxSearch error: php_mbstring extension required';
                } else {
                    if ($this->dbCharset === 'utf8' && $this->cfg['mbstring']) {
                        mb_internal_encoding("UTF-8");
                    }
                    $this->pgCharset = $this->pageCharset[$this->dbCharset];
                    $valid = true;
                }
            } else {
                if (strlen($this->dbCharset)) {
                    // if you get this message, simply update the $pageCharset array in search.class.inc.php file
                    // with the appropriate mapping between Mysql Charset and Html charset
                    // eg: 'latin2' => 'ISO-8859-2'
                    $msgErr = 'AjaxSearch error: unknown database_connection_charset = ' . $this->dbCharset .
                        '<br />' .
                        'Add the appropriate Html charset mapping in the ajaxSearchConfig.class.inc.php file';
                } else {
                    $msgErr = 'AjaxSearch error: database_connection_charset is null. Check your MODX config file';
                }
            }
        }
        return $valid;
    }

    /**
     * Save the current configuration
     *
     * @param $site
     * @param $subsite
     */
    public function saveConfig($site, $subsite)
    {
        if (!isset($this->scfg[$site][$subsite])) {
            $this->scfg[$site][$subsite] = array();
        }
        foreach ($this->cfg as $key => $value) {
            if (!isset($this->bcfg[$key]) || ($this->bcfg[$key] != $value)) {
                $this->scfg[$site][$subsite][$key] = $value;
            }
        }
    }

    /**
     * Restore a named configuration
     *
     * @param string $site
     * @param string $subsite
     * @return void
     */
    public function restoreConfig($site, $subsite)
    {
        if (isset($this->scfg[$site][$subsite])) {
            $this->cfg = array_merge($this->bcfg, $this->scfg[$site][$subsite]);
        } else {
            $this->cfg = array_merge($this->bcfg, $this->scfg[DEFAULT_SITE][DEFAULT_SUBSITE]);
        }
    }

    /**
     * Choose the appropriate configuration for displaying results
     *
     * @param string $site
     * @param string $subsite
     * @param string $display
     */
    public function chooseConfig($site, $subsite, $display)
    {
        $this->restoreConfig(
            ($display !== MIXED) ? $site : DEFAULT_SITE,
            ($display !== MIXED) ? $subsite : DEFAULT_SUBSITE
        );
    }

    /**
     * Create a config by merging site and category config
     *
     * @param string $site
     * @param string $categ
     * @param array|null $ctg
     * @return void
     */
    public function addConfigFromCateg($site, $categ, $ctg)
    {
        if ($site && $categ && !isset($this->scfg[$site][$categ])) {
            $tmp = isset($this->scfg[$site][DEFAULT_SUBSITE]) ? (array)$this->scfg[$site][DEFAULT_SUBSITE] : array();
            $this->scfg[$site][$categ] = array_merge($tmp, (array)$ctg);
        }
    }

    /**
     * Get the non default configuration (advSearch and subSearch excepted)
     * @return array
     */
    public function getUserConfig()
    {
        $ucfg = array();
        foreach ($this->cfg as $key => $value) {
            if ($key !== 'subSearch' && isset($this->dcfg[$key]) && $value != $this->dcfg[$key]) {
                $ucfg[$key] = $this->cfg[$key];
            }
        }
        return $ucfg;
    }

    /**
     * Parse the non default configuration from string
     *
     * @param string $strUcfg
     * @return array
     */
    public function parseUserConfig($strUcfg)
    {
        $ucfg = array();
        $pattern = '/&([^=]*)=`([^`]*)`/';
        preg_match_all($pattern, $strUcfg, $out);
        foreach ($out[1] as $key => $values) {
            unset($values);
            // remove any @BINDINGS in posted user config for security reasons
            $count = 0;
            $index = $out[1][$key];
            if (!$this->isAllowed($index)) {
                continue;
            }
            $str = $out[2][$key];
            do {
                $str = preg_replace(
                    '/@(#|FILE|DIRECTORY|DOCUMENT|CHUNK|INHERIT|SELECT|EVAL)[:\s]\s*/i',
                    '',
                    $str,
                    -1,
                    $count
                );
            } while ($count > 0);
            $ucfg[$index] = $str;
        }
        return $ucfg;
    }

    /**
     * @param $key
     * @return bool
     */
    protected function isAllowed($key)
    {
        return \is_scalar($key) && \in_array($key, $this->allowed, true);
    }

    /**
     * Set the AjaxSearch snippet call
     *
     * @param array $ucfg
     * @return string
     */
    public function setAsCall($ucfg)
    {
        $tpl = '&%s=`%s` ';
        $asCall = '';
        foreach ($ucfg as $key => $value) {
            $asCall .= sprintf($tpl, $key, $value);
        }
        return $asCall;
    }

    /**
     * Read config file
     *
     * @param string $config
     * @return string
     */
    public function readConfigFile($config)
    {
        global $modx;
        $configFile = strpos($config, '@FILE:') === 0 ?
            $modx->getConfig('base_path') . trim(substr($config, 6)) :
            AS_PATH . "configs/$config.config.php";

        $hFile = fopen($configFile, 'r');
        $output = fread($hFile, filesize($configFile));
        fclose($hFile);

        return "\n" . $output;
    }
}
