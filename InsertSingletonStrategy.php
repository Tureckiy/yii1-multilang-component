<?php

Yii::import('wmdl.components.language.strategy.AbstractExecuteSingletonStrategy', true);

/**
 * InsertSingletonStrategy insert execute startegy action
 *
 * @author Roman Sokolov

 * @package language
 */
class InsertSingletonStrategy extends AbstractExecuteSingletonStrategy
{
    /**
     * Return table name from parsed query
     * @return string
     * @throws CException
     */
    protected function getTableFromQuery(array $query)
    {
        if (!isset($query['INSERT']['table'])) {
            throw new CException('ZLanguage.insert', 'Not valid table name');
        }

        $table = $query['INSERT']['table'];
        return str_replace('`', '', $table);
    }

    /**
     * 
     * @param type $table string
     * @param type $columns array
     */
    private function processedPk($table, &$columns)
    {
        $primaryKey = Yii::app()->db->getSchema()->getTable($table)->primaryKey;
        //$info = Yii::app()->db->getSchema()->getTable($table)->getColumn($pk); // info about PK column        
        if ($primaryKey && !is_array($primaryKey)) {
            $rowPk = $this->getZLanguage()->getRowPk();
            if (!is_null($rowPk) && ($rowPk > 0)) {
                $columns[$primaryKey] = $rowPk;
            }
        }
    }

    /**
     *      
     * @param string $table
     * @param array $query
     * @param array $params
     * @return array
     */
    private function getColumns($table, $query, $params)
    {
        $columnsResult = array();
        if (is_array($query['INSERT']['columns'])) {
            foreach ($query['INSERT']['columns'] as $key => $column) {
                if (isset($query['VALUES']) && is_array($query['VALUES']) && count($query['VALUES']) > 0) {
                    if (1 == count($query['VALUES'])) {
                        if (AbstractExecuteSingletonStrategy::FIELD_FUNCTION == $query['VALUES'][0]['data'][$key]['expr_type']) {
                            $columnsResult[$this->clearDbExpression($column['base_expr'])]
                                = new CDbExpression($this->clearDbExpression($query['VALUES'][0]['data'][$key]['base_expr']) . '()'); // ??? NOW without ()
                        } else {
                            $value = $this->clearDbExpression($query['VALUES'][0]['data'][$key]['base_expr']);
                            if (isset($params[$value])) {
                                $columnsResult[$this->clearDbExpression($column['base_expr'])] = $params[$value];
                            }
                        }
                    }
                }
            }
            if (count($columnsResult) != count($query['INSERT']['columns'])) {
                $columnsResult = array();
            }
        } else {
            //$queryParams = $params;            
            $tableSchemaColumns = Yii::app()->db->getSchema()->getTable($table)->columns;
            $tableSchemaColumnsCount = count($tableSchemaColumns);
            $paramsCount = count($params);
            foreach ($tableSchemaColumns as $column) {
                if (($tableSchemaColumnsCount != $paramsCount) && $column->isPrimaryKey) {
                    continue;
                }
                if (count($params) > 0) {
                    $columnsResult[$column->name] = array_shift($params);
                }
            }
        }
        return $columnsResult;
    }

    /**
     * Execute query for insert record
     * @see AbstractExecuteSingletonStrategy::execute()
     */
    public function execute()
    {
        $lang = $this->getZLanguage();
        $table = $this->getTable();
        $query = $this->getZLanguage()->getParsedQuery();
        $params = $this->getZLanguage()->getQueryParam();

        $columns = $this->getColumns($table, $query, $params);

        $result = null;
        if (count($columns)) {
            // for parametrized query
            if ($lang->isPrimaryQuery()) {
                $result = $lang->getCommand()->insert($table, $columns, false);
                $lang->setRowPk($lang->getCommand()->getConnection()->getLastInsertID());
            } else {
                //$this->processedPk($table, &$columns);
                $this->processedPk($table, $columns);
                $result = $lang->getCommand()->insert($table, $columns, false);
            }
        } else {
            // for single or multiple values without params
            if ($sql = $this->prepareInsert($table, $query)) {
                $result = $lang->getCommand()->setText($sql)->execute($params, false);
            }
        }

        if (null === $result) {
            throw new CDbException(Yii::t('wmdl.message', 'There is unsupported query to insert to {table}', array(
                '{table}' => $table,
            )));
        }
        return $result;
    }

    /**
     * Prepare insert statement for query with single or multiple insert records without params
     * @param string $table
     * @param array $query
     * @return bool
     */
    private function prepareInsert($table, $query)
    {
        $headers = $values = array();
        if (isset($query['INSERT']['columns']) && is_array($query['INSERT']['columns'])) {
            foreach ($query['INSERT']['columns'] as $key => $column) {
                $headers[] = $column['base_expr'];
            }
        }
        if (isset($query['VALUES']) && is_array($query['VALUES'])) {
            foreach ($query['VALUES'] as $value) {
                $values[] = $value['base_expr'];
            }
        }

        if (!empty($headers) && !empty($values)) {
            $headers = implode(', ', $headers);
            $values = implode(', ', $values);
            return "INSERT INTO `$table` ($headers) VALUES $values";
        }
        return null;
    }
}