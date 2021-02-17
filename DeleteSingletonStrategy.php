<?php

Yii::import('wmdl.components.language.strategy.AbstractExecuteSingletonStrategy', true);

/**
 * DeleteSingletonStrategy represents delete execute startegy action
 *
 * @author Roman Sokolov

 * @package language
 */
class DeleteSingletonStrategy extends AbstractExecuteSingletonStrategy
{
    /**
     * Return table name from parsed query
     * @return string
     * @throws CException
     */
    protected function getTableFromQuery(array $query)
    {
        if (!isset($query['DELETE']['TABLES'][0])) {
            throw new CException('ZLanguage.delete', 'Not valid table name');
        }

        $table = $query['DELETE']['TABLES'][0];
        return str_replace('`', '', $table);
    }

    /**
     * 
     * @param type $table string
     * @return string array
     */
    private function getCondition($table)
    {
        $query = $this->getZLanguage()->getParsedQuery();
        $condition = '';

        if (isset($query['WHERE'])) {
            $count = count($query['WHERE']);

            for ($i = 0; $i < $count; $i++) {
                $conditionWhere = explode('.', $query['WHERE'][$i]['base_expr']);
                $conditionWhere = end($conditionWhere);
                $condition.= ' ' . str_replace($table, '', $conditionWhere);
            }
        }

        return trim($condition) ? $condition : null;
    }

    /**
     * Execute query for delete record
     * @see AbstractExecuteSingletonStrategy::execute()
     */
    public function execute()
    {
        $table = $this->getTable();
        $conditions = $this->getCondition($table);

        if (null !== $conditions) {
            $this->getZLanguage()->getCommand()->delete($table, $conditions, $this->getZLanguage()->getQueryParam(), false);
        }
    }
}