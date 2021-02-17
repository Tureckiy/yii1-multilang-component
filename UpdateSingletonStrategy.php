<?php

Yii::import('wmdl.components.language.strategy.AbstractExecuteSingletonStrategy', true);

/**
 * UpdateSingletonStrategy update execute startegy action
 *
 * @author Roman Sokolov
 * @package language
 */
class UpdateSingletonStrategy extends AbstractExecuteSingletonStrategy
{
    /**
     * 
     * @param array $data
     */
    private function setAllowedColumns($data = array())
    {
        $this->getZLanguage()->setAllowedColumns($data);
    }

    /**
     * 
     * @param array $data
     * @return array
     */
    private function getAllowedColumns($data = array())
    {
        return $this->getZLanguage()->getAllowedColumns();
    }

    /**
     * 
     * @return boolean
     */
    private function hasAllowedColumns()
    {
        $columns = $this->getZLanguage()->getAllowedColumns();
        $result = false;
        if (is_null($columns) || (is_array($columns) && count($columns))) {
            $result = true;        
        }
        return $result;
    }

    /**
     * Return table name from parsed query
     * @return string
     * @throws CException
     */
    protected function getTableFromQuery(array $query)
    {
        if (!isset($query['UPDATE'][0]['table'])) {
            throw new CException('ZLanguage.update', 'Not valid table name');
        }
        $table = $query['UPDATE'][0]['table'];
        return str_replace('`', '', $table);
    }

    /**
     * 
     * @param type $table string
     * @param type $params array
     * @return type array
     */
    private function getColumns($table, $params = array())
    {
        $query = $this->getZLanguage()->getParsedQuery();
        $columnsResult = array();
        
        if (isset($query['SET'])) {
            foreach ($query['SET'] as $column) {            
                if (isset($column['sub_tree'][0]) && isset($column['sub_tree'][1]) && isset($column['sub_tree'][2])) {
                    if (AbstractExecuteSingletonStrategy::FIELD_FUNCTION == $column['sub_tree'][2]['expr_type']) {
                        $columnsResult[$this->clearDbExpression($column['sub_tree'][0]['base_expr'])] =
                            new CDbExpression($this->clearDbExpression($column['sub_tree'][2]['base_expr']) . '()');
                    } else {
                        $value = '';
                        for ($i = 2; $i < count($column['sub_tree']); $i++) {
                            $value .= $column['sub_tree'][$i]['base_expr'];
                        }
                        $columnsResult[$this->clearDbExpression($column['sub_tree'][0]['base_expr'])] =
                            count($column['sub_tree']) > 3 ? new CDbExpression($value) : $this->clearDbExpression($value);
                    }
                }
            }
        }
        
        $tableLangFields = $this->getZLanguage()->getTable(preg_replace("/^".$this->getLanguage()."/", '', $table));        
        unset($column);
        foreach ($columnsResult as $column => $paramKey) {
            if (!$this->getZLanguage()->isPrimaryQuery()) {
                if (in_array($column, $tableLangFields)) {
                    unset($columnsResult[$column]);
                    continue;
                }
            }
            if (is_object($paramKey)) {
                //
            } else if (is_string($paramKey)) {
                if (array_key_exists($paramKey, $params)) {
                    $columnsResult[$column] = $params[$paramKey];
                } else {
                    //$columnsResult[$column] = 0;
                    //$columnsResult[$column] = new CDbExpression();
                    //$columnsResult[$column] = null;
                }
            }
        }
        $this->setAllowedColumns($columnsResult);
        return $columnsResult;
    }

    /**
     * 
     * @param type $params array
     * @return type array
     */
    private function getCondition($params = array())
    {
        $query = $this->getZLanguage()->getParsedQuery();

        if (!isset($query['WHERE'])) {
            return array(
                'condition' => '',
                'params' => array()
            );
        }

        return $this->parseCondition(
            $query['WHERE'],
            $this->getTableFromQuery($this->getZLanguage()->getParsedQuery()),
            $params
        );
    }

    /**
     * Prepare condition with params
     * @param array $query
     * @param string $table
     * @param array $params
     * @return array
     */
    private function parseCondition($query, $table, $params)
    {
        $count = count($query);
        $condition = $separator = '';
        $conditionParams = array();

        for ($i = 0; $i < $count; $i++) {
            if (isset($query[$i]['expr_type'])
                && (AbstractExecuteSingletonStrategy::FIELD_OPERATOR == $query[$i]['expr_type'])
            ) {
                $separator = ' ' . $query[$i]['base_expr'] . ' ';
                continue;
            }

            if (
                isset($query[$i]['expr_type']) &&
                isset($query[$i + 2]['expr_type']) &&
                isset($query[$i + 1]['expr_type']) &&
                (AbstractExecuteSingletonStrategy::FIELD_COLREF == $query[$i]['expr_type'])
            ) {
                if (AbstractExecuteSingletonStrategy::FIELD_COLREF == $query[$i + 2]['expr_type']) {
                    $conditionParams[$query[$i + 2]['base_expr']] = $params[$query[$i + 2]['base_expr']];
                } else if (AbstractExecuteSingletonStrategy::FIELD_CONST == $query[$i + 2]['expr_type']) {
                    // conts - not param
                }

                $field = str_replace(array($table, '`', '.'), array('', '', ''), $query[$i]['base_expr']);
                $operator = $query[$i + 1]['base_expr'];
                $condition .= $separator . $field . $operator . $this->clearDbExpression($query[$i + 2]['base_expr']);
                $i = $i + 2;
            } else if (
                AbstractExecuteSingletonStrategy::FIELD_BRACKET_EXPRESSION == $query[$i]['expr_type']
                && is_array($query[$i]['sub_tree'])
            ) {
                $results = $this->parseCondition($query[$i]['sub_tree'], $table, $params);
                $condition = $condition . $separator . '(' . $results['condition'] . ')';
                $conditionParams = CMap::mergeArray($conditionParams, $results['params']);
            }
        }

        return array(
            'condition' => $condition,
            'params' => $conditionParams
        );
    }

    /**
     * Execute query for update record
     * @see AbstractExecuteSingletonStrategy::execute()
     */
    public function execute()
    {
        if (!$this->hasAllowedColumns()) {
            return;
        }
        //$query = $this->getZLanguage()->getParsedQuery();

        $table = $this->getTable();
        $params = $this->getZLanguage()->getQueryParam();
        $columns = $this->getColumns($table, $params);

        if (!$this->hasAllowedColumns()) {
            return;
        }
        $conditions = $this->getCondition($params);
        //$this->getZLanguage()->getCommand()->update($table, $columns, $conditions['condition'], $params, false);
        $this->getZLanguage()->getCommand()->update($table, $columns, $conditions['condition'], $conditions['params'], false);
    }

}