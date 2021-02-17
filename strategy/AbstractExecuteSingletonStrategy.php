<?php

/**
 * AbstractExecuteSingletonStrategy abstract class
 *
 * @author Roman Sokolov

 * @package language
 */
abstract class AbstractExecuteSingletonStrategy
{
    CONST FIELD_EXPRESSION = 'expression';

    CONST FIELD_FUNCTION = 'function';

    CONST FIELD_OPERATOR = 'operator';

    CONST FIELD_COLREF = 'colref';

    CONST FIELD_BRACKET_EXPRESSION = 'bracket_expression';

    CONST FIELD_CONST = 'const';

    /**
     * @var AbstractExecuteSingletonStrategy
     */
    private static $_instances = array();

    /**
     *
     * @var ZLanguage ZLanguage
     */
    private $_zLanguage;

    /**
     *
     * @var string
     */
    private $_language;

    /**
     * Return execute strategy instance
     * @param ZLanguage $zLanguage object
     * @param type $language string
     * @return type
     */
    public static function getInstance(ZLanguage $zLanguage/*, $language*/)
    {
        $obj = new static($zLanguage);
        $className = get_class($obj);

        if (false === array_key_exists($className, self::$_instances)) {
            self::$_instances[$className] = $obj;
        }

        //self::$_instances[$className]->setLanguage($language);

        return self::$_instances[$className];
    }

    /**
     * Constructor
     * @param ZLanguage $zLanguage
     */
    private function __construct(ZLanguage $zLanguage)
    {
        $this->_zLanguage = $zLanguage;
    }

    /**
     *
     * @param type $exp string
     * @return type string
     */
    protected function clearDbExpression($exp)
    {
        return trim(str_replace('`', '', $exp));
    }

    /**
     *
     */
    public function reset()
    {
        $this->_language = null;
    }

    /**
     * Return Language component
     * @return ZLanguage
     */
    protected function getZLanguage()
    {
        return $this->_zLanguage;
    }

    /**
     * Return language name
     * @return string
     * @throws CException
     */
    protected function getLanguage()
    {
        if (is_null($this->_language)) {
            throw new CException('ZLanguage', 'Not valid language in multilanguage process');
        }

        return $this->_language;
    }

    /**
     * Set language
     * @param string $lang
     */
    public function setLanguage($lang)
    {
        $this->_language = $lang;
    }

    /**
     * Return Table Name
     * @return string
     */
    protected function getTable()
    {
        return preg_replace(
            "/^".Yii::app()->language."/",
            $this->getLanguage(),
            $this->getTableFromQuery($this->getZLanguage()->getParsedQuery())
        );
    }

    /**
     * Execute query
     */
    abstract public function execute();

    /**
     * Return table name from parsed query
     * @return string
     * @throws CException
     */
    abstract protected function getTableFromQuery(array $query);
}