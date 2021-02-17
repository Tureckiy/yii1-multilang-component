<?php

Yii::import('wmdl.components.language.ZLanguageInterface', true);

/**
 * ZLanguage class.
 *
 * <pre>
 *  // example
 * 'zlanguage' =>
 *   array (
 *     'languages' =>
 *     array (
 *       'ru' => 'Russian',
 *       'en' => 'English',
 *       'ar' => 'Arabic',
 *     ),
 *      'userTables' =>
 *     array (
 *      'userTableName1' => array('languageDependenceColumnName1','languageDependenceColumnName2')
 *      'userTableName2' => array('languageDependenceColumnName1','languageDependenceColumnName2')
 *     ),
 *     'multilanguage' => true, // false default
 *     'defaultLanguage' => 'en',
 *     'dbConnectionName' => 'db',
 *     'class' => 'wmdl.components.language.ZLanguage',
 *   ),
 *
 * additionally using components
 * 1)
 * 'messages' =>
 *   array (
 *     'class' => 'CDbMessageSource',
 *     'language' => 'en',
 *     'sourceMessageTable' => 'TranslateSourceMessage',
 *     'translatedMessageTable' => 'TranslateMessage',
 *   ),
 * 2)  * // customized all "execute" methods
 *
 * 'db' =>  // will be used ZDbCommand
 *   array (
 *      ...
 *     'class' => 'wmdl.db.ZDbConnection',
 *     'tablePrefix' => '',
 *      ...
 * }
 * 3) * // customized ::getPathInfo() method, returned _pathInfo (need without lang prefix)
 * 'request' =>
 *      array (
 *          'class' => 'wmdl.web.ZHttpRequest',
 *      ),
 * </pre>
 *
 *
 */

/**
 * ZLanguage represents an ...
 *
 ** @author Roman Sokolov

 * @package components
 */
final class ZLanguage extends CComponent implements ZLanguageInterface
{
    /**
     *  system lang user state key
     */
    CONST LANGUAGE_KEY = 'languageSiteState';

    CONST LANGUAGE_ADDITIONAL_KEY = 'languageSiteStateAdditional';

    /**
     * default language
     */
    const LANGAUGE_DEFAULT = 'en';

    /**
     * List of user tables
     * @var array
     */
    public $userTables = array(); // user tables - configure in main.php

    /**
     * languages on the site
     * @var array
     */
    public $languages = array(); //

    /**
     * languages on the site
     * @var string
     */
    public $dbConnectionName = 'db'; //

    /**
     * multisite flag
     * @var boolean
     */
    public $multilanguage = false; //

    /**
     * default language ('en' ) for multilanguage site
     * @var string
     */
    public $defaultLanguage = self::LANGAUGE_DEFAULT;

    /**
     * default language ('en' ) for multilanguage site
     * @var string
     */
    public $defaultCurrency = self::LANGAUGE_DEFAULT;

    /**
     * List of system tabes
     * These tables don't replicate for language
     * @var array
     */
    public $systemTables = array();

    /**
     * Only table with same prefix will be filtered for creating language
     * null - for all tables
     * @var string
     */
    public $langTablePrefix;

    /**
     *
     * @var ZDbCommand object
     */
    private $_command = null; // $parser = new PHPSQLParser($sql); $parser->parsed;

    /**
     *
     * @var array parsed array - set before multi execute and clear after
     */
    private $_parsedQuery = null; // $parser = new PHPSQLParser($sql); $parser->parsed;

    /**
     * ZDbCommand object params
     * @var array
     */
    private $_queryParam = null;

    /**
     *
     * @var array columns for insert or update
     */
    private $_queryColumns = null;

    /**
     * last inserted PK value set after first query on multiquery process
     * @var mixed
     */
    private $_rowPk = null;

    /**
     * @var boolean
     */
    private $_primaryQuery = false;

    /**
     * columns for update independents columns on table
     * @var array
     */
    private $_allowedColumns = null; // null - default value, array - after first invoice and parse query- return array empty or no

    /**
     * merged WebModulite tables with user tables and override WebModulite tables
     * @var array
     */
    private $_tables = array(); // merge tables

    /**
     * List of Tables doesn't syncrhonize while insert record
     * @var array tables
     */
    private $_unsyncTables = array();
    /**
     * @var string
     */
    private $_userStateLanguage;

    /**
     * Init Environment for languages
     */
    private function initEnvironment()
    {
        Yii::import('wmdl.vendors.phpSqlParser.PHPSQLParser', false);
        Yii::import('wmdl.components.language.strategy.*', false);

        if (Yii::app()->request->getIsAjaxRequest()) {
            $this->setSiteStates($this->getUserStateLanguage());
        }

        if (Yii::app() instanceof ZWebApplication) {
            if (Yii::app()->isBackend()) {
                Yii::import('admin.models.AdminLanguageModel');
                AdminLanguageModel::setAdminAreaLanguage($_POST, $this);
            } else {
                $this->processedRequestUri(Yii::app()->getRequest());
            }
        }
    }

    /**
     * Init component
     */
    public function init()
    {
        if ($this->isMultiLanguage()) {
            $this->initEnvironment();
            $this->setTables(array_merge(
                require(Yii::getPathOfAlias('wmdl') . '/components/language/tables.php'),
                $this->userTables
            ));
            $this->setUnsyncTables(
                require(Yii::getPathOfAlias('wmdl') . '/components/language/unsyncTables.php')
            );
        }
    }

    /**
     * Return command
     * @return ZDbCommand
     */
    public function getCommand()
    {
        if (is_null($this->_command)) {
            $this->_command = Yii::app()->getComponent($this->dbConnectionName)->createCommand();
        }
        return $this->_command;
    }

    /**
     * Return default language
     * @return string
     */
    public function getDefaultLanguage()
    {
        return $this->defaultLanguage;
    }

    private function clearParsedQuery()
    {
        $this->_parsedQuery = null;
    }

    private function clearCommand()
    {
        $this->_command = null;
    }

    private function clearQueryParam()
    {
        $this->_queryParam = null;
    }

    private function clearAllowedColumns()
    {
        $this->_allowedColumns = null;
    }

    /**
     * @param boolean $data
     */
    public function setPrimaryQuery($data = true)
    {
        $this->_primaryQuery = $data;
    }

    /**
     * @return boolean value
     */
    public function isMultiLanguage()
    {
        return $this->multilanguage;
    }

    /**
     * Return current language
     * @return string
     */
    public function getUserStateLanguage()
    {
        if (null === $this->_userStateLanguage) {
            if (isset(Yii::app()->request->cookies[self::LANGUAGE_KEY]->value)) {
                $this->_userStateLanguage = $this->checkLanguageCode(Yii::app()->request->cookies[self::LANGUAGE_KEY]->value);
            } else if ($language = $this->getPreferredLanguage()) {
                $this->_userStateLanguage = $this->checkLanguageCode($language);
                $this->setSiteStates($this->_userStateLanguage);
            } else {
                $this->_userStateLanguage = Yii::app()->language;
            }
        }
        return $this->_userStateLanguage;
    }

    /**
     * Data language
     * @param string $langCode
     */
    private function setUserStateLanguage($langCode)
    {
        if (Yii::app()->request->cookies[self::LANGUAGE_KEY]
            && Yii::app()->request->cookies[self::LANGUAGE_KEY]->value != $langCode) {
            Yii::app()->request->cookies[self::LANGUAGE_KEY] = new CHttpCookie(self::LANGUAGE_KEY, $langCode);
        } elseif (!Yii::app()->request->cookies[self::LANGUAGE_KEY]) {
            Yii::app()->request->cookies[self::LANGUAGE_KEY] = new CHttpCookie(self::LANGUAGE_KEY, $langCode);
        }
    }

    /**
     * Additional set language in cookie
     * @param $language
     */
    public function setAdditionalStateLanguage($language = '')
    {
        if (!empty($language)) {
            $language = trim($language);

            if (Yii::app()->request->cookies[self::LANGUAGE_ADDITIONAL_KEY]
                && Yii::app()->request->cookies[self::LANGUAGE_ADDITIONAL_KEY]->value != $language) {
                Yii::app()->request->cookies[self::LANGUAGE_ADDITIONAL_KEY] = new CHttpCookie(self::LANGUAGE_ADDITIONAL_KEY, $language);
            }
            elseif (!Yii::app()->request->cookies[self::LANGUAGE_ADDITIONAL_KEY]) {
                Yii::app()->request->cookies[self::LANGUAGE_ADDITIONAL_KEY] = new CHttpCookie(self::LANGUAGE_ADDITIONAL_KEY, $language);
            }
        }
    }

    /**
     * @return string
     */
    public function getAdditionalStateLanguage()
    {
        if (isset(Yii::app()->request->cookies[self::LANGUAGE_ADDITIONAL_KEY]->value)) {
            return $this->checkLanguageCode(Yii::app()->request->cookies[self::LANGUAGE_ADDITIONAL_KEY]->value);
        }
    }

    /**
     * Return preferred language based on client locale
     * @return string
     */
    public function getPreferredLanguage()
    {
        if ($language = Yii::app()->request->getPreferredLanguage()) {
            list($language,) = explode('_', $language);
        }
        if (Yii::app()->params['useCountryResidentOnly']) {
            $language = strtolower(Yii::app()->params['countryResident']);
        } elseif ($language != Yii::app()->params['countryResident']) {
            if ($code = Yii::app()->geoip->lookupLocation(Yii::app()->request->getUserIp())) {
                if ($data = $code->getData()) {
                    $language = strtolower($data['countryCode']);
                }
            }
        }
        return $language;
    }

    /**
     *
     * @return string
     */
    public function getHomeUrl()
    {
        return Yii::app()->createUrl('/');
    }

    /**
     *
     * @param bool $onlyKeys
     * @return array
     */
    public function getLanguages($onlyKeys = false)
    {
        if ($onlyKeys) {
            return array_keys($this->languages);
        } else {
            return $this->languages;
        }
    }

    /**
     *  clear component state after execute process
     */
    public function resetState()
    {
        $this->clearCommand();
        $this->clearQueryParam();
        $this->clearAllowedColumns();
        $this->clearQueryColumns();
        $this->clearParsedQuery();
        $this->clearRowPk();
    }

    /**
     * Return parsed Query
     * @return array
     */
    public function getParsedQuery()
    {
        return $this->_parsedQuery;
    }

    /**
     * Set parsed query
     * @param array $data
     */
    protected function setParsedQuery($data = array())
    {
        $this->_parsedQuery = $data;
    }

    /**
     * Return params for ZDBCommand
     * @return array
     */
    public function getQueryParam()
    {
        return $this->_queryParam;
    }

    /**
     * Set params for ZDBCommand
     * @param $data array
     */
    protected function setQueryParam($data = array())
    {
        $this->_queryParam = $data;
    }

    /**
     * @param mixed $data raw format
     */
    public function setRowPk($data)
    {
        $this->_rowPk = $data;
    }

    /**
     * @return mixed
     */
    public function getRowPk()
    {
        return $this->_rowPk;
    }

    /**
     *
     * @return
     */
    protected function getQueryColumns()
    {
        return $this->_queryColumns;
    }

    /**
     *
     * @param type $data array
     */
    protected function setQueryColumns($data)
    {
        $this->_queryColumns = $data;
    }

    /**
     * @return array
     */
    public function getAllowedColumns()
    {
        return $this->_allowedColumns;
    }

    /**
     * @param array $data
     */
    public function setAllowedColumns($data = array())
    {
        $this->_allowedColumns = $data;
    }

    /**
     *
     */
    private function clearQueryColumns()
    {
        $this->_queryColumns = null;
    }

    /**
     *
     */
    private function clearRowPk()
    {
        $this->_rowPk = null;
    }

    /**
     * Set Tables
     * @param array $tables
     */
    protected function setTables($tables)
    {
        $this->_tables = $tables;
    }

    /**
     *
     * @param string $key string table name
     * @return array
     */
    protected function getTables($key = null)
    {
        if (is_null($key)) {
            return $this->_tables;
        } else {
            return $this->getTable($key);
        }
    }

    /**
     * set unsync tables
     * @param array $tables
     */
    protected function setUnsyncTables($tables)
    {
        $this->_unsyncTables = $tables;
    }

    /**
     *
     * @return array of unsync tables
     */
    public function getUnsyncTables()
    {
        return $this->_unsyncTables;
    }

    /**
     *
     * @param type $key sting
     * @return array table columns
     */
    public function getTable($key)
    {
        $key = str_replace(array('{', '}'), '', $key);
        if (isset($this->_tables[$key])) {
            return $this->_tables[$key];
        } else {
            return array();
        }
    }

    /**
     *
     * @return boolean
     */
    public function isPrimaryQuery()
    {
        return (bool)$this->_primaryQuery;
    }

    /**
     * Return language list
     * @return array
     */
    public function getQueryLanguages()
    {
        if ($this->isPrimaryQuery()) {
            //only for NOT for update process
            return array($this->defaultLanguage => '');
        } else {
            $languages = $this->languages;
            /*
             * UPDATE: FIRST - update row CURRENT language;
             *      NEXT - other languages WITHOUT CURRENT
             *
             * INSERT/DELETE: FIRST - execute query for PRIMARY language;
             *      NEXT  - for other WITHOUT primary
             */
            if ($this->isUpdateProcess()) {
                unset($languages[Yii::app()->language]);
            } else {
                unset($languages[$this->defaultLanguage]);
            }
            return $languages;
        }
    }

    /**
     * Return Execute strategy
     * @return AbstractExecuteSingletonStrategy
     */
    public function getExecuteStrategy()
    {
        $query = $this->getParsedQuery();
        $strategy = null;
        if (isset($query['ALTER'])) {
            return null;
        }
        if (isset($query['UPDATE'])) {
            $strategy = 'UpdateSingletonStrategy';
        } else if (isset($query['INSERT'])) {
            $strategy = 'InsertSingletonStrategy';
        } else if (isset($query['DELETE'])) {
            $strategy = 'DeleteSingletonStrategy';
        }

        return $strategy ? $strategy::getInstance($this) : null;
    }

    /**
     *
     * @param ZDbCommand $commandDb object
     */
    public function prepareMultiQuery(ZDbCommand $commandDb)
    {
        $this->resetState();
        $this->parseQuery($commandDb);
    }

    /**
     *
     * @return boolean is multisate
     */
    public function isUpdateProcess()
    {
        $query = $this->getParsedQuery();
        return is_array($query) && isset($query['UPDATE']);
    }

    /**
     *
     * @param ZDbCommand $commandDb
     */
    private function parseQuery(ZDbCommand $commandDb)
    {
        $parser = new PHPSQLParser($commandDb->getPdoStatement()->queryString);
        $this->setParsedQuery($parser->parsed);
    }

    /**
     * Make and execute query
     * @param ZDbCommand $commandDb
     * @param array $queryParam
     * @return int
     */
    public function makeQuery(ZDbCommand $commandDb, $queryParam = array())
    {
        if (null == $this->getParsedQuery()) {
            $this->parseQuery($commandDb);
        }

        if (null == $this->getQueryParam()) {
            $this->setQueryParam(array_merge($commandDb->getParamLog(), $queryParam));
        }
        $n = 0;
        $strategy = $this->getExecuteStrategy();
        if ($strategy) {
            $command = $this->getCommand();
            foreach ($this->getQueryLanguages() as $language => $value) {
                $command->reset();
                $strategy->setLanguage($language);
                $strategy->execute();
                if ($this->isPrimaryQuery() && $command->getPdoStatement()) {
                    $n = $command->getPdoStatement()->rowCount();
                }
            }
        }
        return $n;
    }

    /**
     * @return boolean is SingleTablesKit => (execute only for current system language)
     */
    public function getIsUnsyncTable()
    {
        $tableName = $this->getParsedTableName();
        return count(array_filter($this->getUnsyncTables(), function ($unsyncTable) use ($tableName) {
                return 0 === strpos($tableName, $unsyncTable);
            })) > 0;
    }

    /**
     * Whether table is system
     * @return boolean
     */
    public function getIsSystemTable()
    {
        return in_array($this->getParsedTableName(), $this->systemTables);
    }

    /**
     * Return table name from parsed query
     * @return string
     */
    private function getParsedTableName()
    {
        $executeTable = '';
        $query = $this->getParsedQuery();
        if (isset($query['INSERT']['table'])) {
            $executeTable = $query['INSERT']['table'];
        } else if (isset($query['DELETE']['TABLES'][0])) {
            $executeTable = $query['DELETE']['TABLES'][0];
        } else if (isset($query['UPDATE'][0]['table'])) {
            $executeTable = $query['UPDATE'][0]['table'];
        }
        $executeTable = str_replace('`', '', $executeTable);
        return preg_replace("/^" . Yii::app()->language . "/", '', $executeTable);
    }

    /**
     *
     * @param string $language
     * @return string
     */
    public function checkLanguageCode($language)
    {
        if (in_array($language, array_keys($this->languages))) {
            return $language;
        } else {
            return $this->getDefaultLanguage();
        }
    }

    /**
     *
     * @param CHttpRequest $request object
     */
    private function processedRequestUri(CHttpRequest $request)
    {
        $urlManager = Yii::app()->getUrlManager();
        $url = $request->requestUri;
        if ($baseUrl = $urlManager->getBaseUrl()) {
            $url = preg_replace('/^' . preg_quote($baseUrl, '/') . '/', '', $url);
        }
        $requestUriArr = explode('/', $url);
        if (isset($requestUriArr[1]) && !empty($requestUriArr[1])) {
            $language = $this->checkLanguageCode($requestUriArr[1]);
            if ($language == $requestUriArr[1]) {
                unset($requestUriArr[1]);

                $this->setSiteStates($language);
            }
        } else {

            $this->setSiteStates($this->getUserStateLanguage());
        }
        //To refresh language depends component
        $urlManager->skipSitemapInit = false;
        $urlManager->init();
    }

    /**
     * @param null $language
     */
    public function setEnvSiteStates($language = null)
    {
        $this->setSiteStates($language);
    }

    /**
     * @param string $language
     * @return string
     */
    private function setSiteStates($language = null)
    {
        if (!$language) {
            $language = $this->getDefaultLanguage();
        }
        Yii::app()->language = $language;
        Yii::app()->db->tablePrefix = $language;

        $this->setUserStateLanguage($language);
        return $language;
    }

    /**
     * Return multilanguage url
     * @param string $url
     * @param string $baseUrl
     * @return string
     */
    public function getMultiLanguageUrl($url = '/', $baseUrl = '')
    {
        $language = $this->getUserStateLanguage();
        $languages = $this->getLanguages(true);

        if ($baseUrl) {
            $url = preg_replace('/^' . preg_quote($baseUrl, '/') . '/', '', $url);
        }
        if ('/' == $url || count(array_filter($languages, function ($key) use ($url) {
                return "/$key" == $url;
            })) > 0
        ) {
            $url = $baseUrl . '/' . $language;
        } else {
            $url = $baseUrl . '/' . $language . $url;
        }
        return $url;
    }

    /**
     * Change current language
     * @param string $lang
     */
    public function changeLanguage($lang)
    {
        Yii::app()->db->tablePrefix = $lang;
        Yii::app()->language = $lang;
        Yii::app()->db->schema->refresh();
    }

    /**
     * Apply callback method for all languages
     * Don't forget refresh your AR schema
     * @param callable $callback
     * @param array $params
     */
    public function applyLanguages($callback, $params = array())
    {
        $languages = array_keys($this->languages);
        $current = Yii::app()->language;

        foreach ($languages as $lang) {
            Yii::app()->zlanguage->changeLanguage($lang);
            //$this->owner->refreshMetaData();
            //$this->owner->refresh();

            call_user_func_array($callback, CMap::mergeArray($params, [
                'language' => $lang,
                'current' => $current,
            ]));
        }
        $this->changeLanguage($current);
        //$this->owner->refreshMetaData();
        //$this->owner->refresh();
    }

    /**
     *
     * @return string
     */
    public function getLocale()
    {
        $current = 'en_US';
        if ($this->isMultiLanguage()) {
            $current = Yii::app()->language;
            if ($current == 'en') {
                $current = $current . '_US';
            } else {
                $current = $current . '_' . strtoupper($current);
            }
        }
        return $current;
    }

    /**
     * @param bool $woCurrent
     * @return array
     */
    public function getLocales($woCurrent = true)
    {
        $locales = [];
        if ($this->isMultiLanguage()) {
            $languages = array_keys($this->languages);
            $current = Yii::app()->language;
            foreach ($languages as $lang) {
                if ($woCurrent && $current == $lang) {
                    continue;
                }
                if ($lang == 'en') {
                    $lang = $lang . '_US';
                } else {
                    $lang = $lang . '_' . strtoupper($lang);
                }
                $locales[] = $lang;
            }
        }
        return $locales;
    }

    /**
     * @param bool $woCurrent
     * @return array language Urls
     */
    public function getMultiLangUrls(CActiveRecord $model, $route, $woCurrent = true)
    {
        $urls = [];
        $languages = $this->getLanguages(true);
        if ($model instanceof IMultiLangUrls) {
            $current = Yii::app()->language;
            $table = $model->tableName();
            foreach ($languages as $lang) {
                if ($woCurrent && $current == $lang) {
                    continue;
                }

            }
        } else {

        }
        return $urls;
    }

}