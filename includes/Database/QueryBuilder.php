<?php
namespace BattleLedger\Database;

/**
 * Query builder utility
 */
class QueryBuilder {
    
    private $table;
    private $select = '*';
    private $where = [];
    private $orderby = '';
    private $limit = '';
    private $join = [];
    
    public function __construct($table) {
        global $wpdb;
        $this->table = $wpdb->prefix . $table;
    }
    
    /**
     * Set SELECT columns
     */
    public function select($columns) {
        $this->select = is_array($columns) ? implode(', ', $columns) : $columns;
        return $this;
    }
    
    /**
     * Add WHERE condition
     */
    public function where($column, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $allowed_operators = ['=', '!=', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IS', 'IS NOT'];
        if (!in_array(strtoupper($operator), $allowed_operators, true)) {
            $operator = '=';
        }
        $column = preg_replace('/[^a-zA-Z0-9_.]/', '', $column);
        
        global $wpdb;
        $this->where[] = $wpdb->prepare("`$column` $operator %s", $value);
        return $this;
    }
    
    /**
     * Add WHERE IN condition
     */
    public function whereIn($column, array $values) {
        global $wpdb;
        $column = preg_replace('/[^a-zA-Z0-9_.]/', '', $column);
        $placeholders = implode(', ', array_fill(0, count($values), '%s'));
        $this->where[] = $wpdb->prepare("`$column` IN ($placeholders)", $values);
        return $this;
    }
    
    /**
     * Add ORDER BY
     */
    public function orderBy($column, $direction = 'ASC') {
        $column = preg_replace('/[^a-zA-Z0-9_.]/', '', $column);
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderby = "ORDER BY `$column` $direction";
        return $this;
    }
    
    /**
     * Add LIMIT
     */
    public function limit($limit, $offset = 0) {
        $this->limit = sprintf('LIMIT %d, %d', (int) $offset, (int) $limit);
        return $this;
    }
    
    /**
     * Add JOIN
     */
    public function join($table, $on, $type = 'INNER') {
        global $wpdb;
        $table = $wpdb->prefix . $table;
        $this->join[] = "$type JOIN $table ON $on";
        return $this;
    }
    
    /**
     * Get results
     */
    public function get() {
        global $wpdb;
        
        $sql = "SELECT {$this->select} FROM {$this->table}";
        
        if (!empty($this->join)) {
            $sql .= ' ' . implode(' ', $this->join);
        }
        
        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }
        
        if ($this->orderby) {
            $sql .= ' ' . $this->orderby;
        }
        
        if ($this->limit) {
            $sql .= ' ' . $this->limit;
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get first result
     */
    public function first() {
        $this->limit(1);
        $results = $this->get();
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Get count
     */
    public function count() {
        global $wpdb;
        
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        
        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }
        
        return (int) $wpdb->get_var($sql);
    }
    
    /**
     * Insert record
     */
    public function insert(array $data) {
        global $wpdb;
        
        $wpdb->insert($this->table, $data);
        return $wpdb->insert_id;
    }
    
    /**
     * Update records
     */
    public function update(array $data) {
        global $wpdb;
        
        $where_clause = !empty($this->where) ? implode(' AND ', $this->where) : '1=1';
        
        $set_parts = [];
        foreach ($data as $column => $value) {
            $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
            $set_parts[] = $wpdb->prepare("`$column` = %s", $value);
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $set_parts) . " WHERE $where_clause";
        
        return $wpdb->query($sql);
    }
    
    /**
     * Delete records
     */
    public function delete() {
        global $wpdb;
        
        $where_clause = !empty($this->where) ? implode(' AND ', $this->where) : '1=1';
        
        $sql = "DELETE FROM {$this->table} WHERE $where_clause";
        
        return $wpdb->query($sql);
    }
}
