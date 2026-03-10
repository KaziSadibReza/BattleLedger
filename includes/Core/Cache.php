<?php
namespace BattleLedger\Core;

/**
 * Caching utility class
 */
class Cache {
    
    private static $instance = null;
    private $redis_enabled = false;
    private $redis = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_redis();
    }
    
    /**
     * Initialize Redis if available
     */
    private function init_redis() {
        if (!class_exists('Redis')) {
            return;
        }
        
        $enable_redis = get_option('battle_ledger_enable_redis', false);
        
        if (!$enable_redis) {
            return;
        }
        
        try {
            $this->redis = new \Redis();
            $this->redis->connect('127.0.0.1', 6379);
            $this->redis_enabled = true;
        } catch (\Exception $e) {
            error_log('BattleLedger Redis connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get cached value
     */
    public function get($key) {
        $key = $this->prefix_key($key);
        
        // Try Redis first
        if ($this->redis_enabled && $this->redis) {
            $value = $this->redis->get($key);
            if ($value !== false) {
                return maybe_unserialize($value);
            }
        }
        
        // Fallback to WordPress object cache
        return wp_cache_get($key, 'battle_ledger');
    }
    
    /**
     * Set cache value
     */
    public function set($key, $value, $expiration = 3600) {
        $key = $this->prefix_key($key);
        
        // Set in Redis
        if ($this->redis_enabled && $this->redis) {
            $this->redis->setex($key, $expiration, maybe_serialize($value));
        }
        
        // Set in WordPress object cache
        wp_cache_set($key, $value, 'battle_ledger', $expiration);
        
        return true;
    }
    
    /**
     * Delete cached value
     */
    public function delete($key) {
        $key = $this->prefix_key($key);
        
        // Delete from Redis
        if ($this->redis_enabled && $this->redis) {
            $this->redis->del($key);
        }
        
        // Delete from WordPress object cache
        return wp_cache_delete($key, 'battle_ledger');
    }
    
    /**
     * Flush all cache
     */
    public function flush() {
        // Flush Redis (only BattleLedger keys)
        if ($this->redis_enabled && $this->redis) {
            $keys = $this->redis->keys('battle_ledger:*');
            if (!empty($keys)) {
                $this->redis->del($keys);
            }
        }
        
        // Flush WordPress object cache group
        wp_cache_flush_group('battle_ledger');
        
        return true;
    }
    
    /**
     * Prefix cache key
     */
    private function prefix_key($key) {
        return 'battle_ledger:' . $key;
    }
    
    /**
     * Check if Redis is enabled
     */
    public function is_redis_enabled() {
        return $this->redis_enabled;
    }
}
