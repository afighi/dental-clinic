<?php

if (!function_exists('loadEnv')) {
    function loadEnv($path = null) {
        static $loaded = false;
        if ($loaded) return;
        $loaded = true;
        if ($path === null) {
            $path = dirname(__DIR__) . '/.env';
        }
        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                    continue;
                }
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val, " \t\n\r\0\x0B\"'");
                if (getenv($key) === false) {
                    putenv("{$key}={$val}");
                    $_ENV[$key] = $val;
                    $_SERVER[$key] = $val;
                }
            }
        }
    }
}
loadEnv();

class SupabasePDO extends PDO {
    public static function rewriteQuery($query) {
        if (!is_string($query)) {
            return $query;
        }
        // Match table references to 'user' and quote as '"user"' for PostgreSQL compatibility
        $pattern = '/\b(from|join|into|update|table)\s+`?user`?\b/i';
        return preg_replace_callback($pattern, function($matches) {
            return $matches[1] . ' "user"';
        }, $query);
    }

    #[\ReturnTypeWillChange]
    public function prepare($query, $options = []) {
        return parent::prepare(self::rewriteQuery($query), $options);
    }

    #[\ReturnTypeWillChange]
    public function query($query, $fetchMode = null, ...$fetch_mode_args) {
        $rewritten = self::rewriteQuery($query);
        if ($fetchMode === null) {
            return parent::query($rewritten);
        }
        return parent::query($rewritten, $fetchMode, ...$fetch_mode_args);
    }

    #[\ReturnTypeWillChange]
    public function exec($statement) {
        return parent::exec(self::rewriteQuery($statement));
    }
}

class Database {
    private $driver;
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        $this->driver   = getenv('DB_DRIVER') ?: 'pgsql';
        $this->host     = getenv('DB_HOST') ?: 'localhost';
        $this->port     = getenv('DB_PORT') ?: '5432';
        $this->db_name  = getenv('DB_NAME') ?: 'postgres';
        $this->username = getenv('DB_USER') ?: 'postgres';
        $this->password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
    }

    // Method to get the database connection
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "pgsql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name;
            $this->conn = new SupabasePDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }
}