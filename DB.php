<?php
use PDO;
use PDOException;

class DB
{
    // --- Connection Properties ---
    private static ?PDO $pdoRead = null;
    private static ?PDO $pdoWrite = null;

    // --- Query Building Properties ---
    private string $table;
    private array $columns = ['*'];
    private array $joins = [];
    private array $where = [];
    private array $orderBy = [];
    private array $groupBy = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $bindings = [];
    private bool $forceWriteConnection = false;

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {}

    /**
     * Establishes the database connection(s).
     *
     * @param array $config The database configuration array.
     * Can be a single connection config or an array with 'read' and 'write' keys.
     */
    public static function connect(array $config): void
    {
        // If 'write' key exists, assume a read/write setup.
        if (isset($config['write'])) {
            self::$pdoWrite = self::createConnection($config['write']);
            // Use read config if provided, otherwise fallback to write connection for reads.
            self::$pdoRead = isset($config['read']) ? self::createConnection($config['read']) : self::$pdoWrite;
        } else {
            // Assume a single connection for both read and write.
            $connection = self::createConnection($config);
            self::$pdoWrite = $connection;
            self::$pdoRead = $connection;
        }
    }

    /**
     * Helper method to create a PDO connection instance.
     *
     * @param array $config
     * @return PDO
     */
    private static function createConnection(array $config): PDO
    {
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            return new PDO($dsn, $config['user'], $config['password'], $options);
        } catch (PDOException $e) {
            // In a real app, you would log this error more gracefully.
            throw new PDOException($e->getMessage(), (int)$e->getCode());
        }
    }

    /**
     * Specifies the table to query. This starts a new query chain.
     *
     * @param string $tableName
     * @return self
     */
    public static function table(string $tableName): self
    {
        if (self::$pdoRead === null || self::$pdoWrite === null) {
            throw new \RuntimeException("Database not connected. Please call DB::connect() first.");
        }
        $instance = new self();
        $instance->table = $tableName;
        return $instance;
    }

    /**
     * Determines which PDO connection to use for the query.
     *
     * @param bool $isWriteOperation Indicates if the operation modifies data.
     * @return PDO
     */
    private function getConnection(bool $isWriteOperation = false): PDO
    {
        if ($isWriteOperation || $this->forceWriteConnection) {
            return self::$pdoWrite;
        }
        return self::$pdoRead;
    }

    /**
     * Force the next query to use the "write" connection.
     * Useful for reading data immediately after a write to bypass replication lag.
     *
     * @return self
     */
    public function useWritePdo(): self
    {
        $this->forceWriteConnection = true;
        return $this;
    }

    // --- Query Building Methods (unchanged from v2) ---
    public function select(array $columns = ['*']): self { $this->columns = $columns; return $this; }
    public function limit(int $value): self { $this->limit = $value; return $this; }
    public function offset(int $value): self { $this->offset = $value; return $this; }
    public function where(string $column, string $operator, $value = null): self { if (func_num_args() === 2) { $value = $operator; $operator = '='; } $this->where[] = ['clause' => "{$column} {$operator} ?", 'boolean' => 'AND']; $this->bindings[] = $value; return $this; }
    public function orWhere(string $column, string $operator, $value = null): self { if (func_num_args() === 2) { $value = $operator; $operator = '='; } $this->where[] = ['clause' => "{$column} {$operator} ?", 'boolean' => 'OR']; $this->bindings[] = $value; return $this; }
    public function whereIn(string $column, array $values, string $boolean = 'AND', bool $not = false): self { if (empty($values)) { return $this; } $placeholders = implode(', ', array_fill(0, count($values), '?')); $type = $not ? 'NOT IN' : 'IN'; $this->where[] = ['clause' => "{$column} {$type} ({$placeholders})", 'boolean' => $boolean]; $this->bindings = array_merge($this->bindings, $values); return $this; }
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self { $this->joins[] = "{$type} JOIN {$table} ON {$first} {$operator} {$second}"; return $this; }
    public function leftJoin(string $table, string $first, string $operator, string $second): self { return $this->join($table, $first, $operator, $second, 'LEFT'); }
    public function orderBy(string $column, string $direction = 'ASC'): self { $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC'; $this->orderBy[] = "{$column} {$direction}"; return $this; }
    public function groupBy(...$columns): self { $this->groupBy = array_merge($this->groupBy, $columns); return $this; }
    
    // --- Terminating Methods (Execution) ---
    // These methods are now updated to use the getConnection() helper.

    public function get(): array
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare($this->toSql());
        $stmt->execute($this->bindings);
        return $stmt->fetchAll();
    }

    public function first()
    {
        $pdo = $this->getConnection();
        $this->limit(1);
        $stmt = $pdo->prepare($this->toSql());
        $stmt->execute($this->bindings);
        return $stmt->fetch();
    }
    
    public function find($id)
    {
        return $this->where('id', '=', $id)->first();
    }

    public function cursor(): \Generator
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare($this->toSql());
        $stmt->execute($this->bindings);
        while ($record = $stmt->fetch()) {
            yield $record;
        }
    }

    private function aggregate(string $function, string $column = '*'): mixed
    {
        $pdo = $this->getConnection();
        $this->columns = ["{$function}({$column}) as aggregate"];
        $sql = $this->toSql();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($this->bindings);
        $result = $stmt->fetch();
        return $result ? $result->aggregate : null;
    }

    public function count(string $column = '*'): int { return (int) $this->aggregate('COUNT', $column); }
    public function sum(string $column) { return $this->aggregate('SUM', $column); }
    public function avg(string $column) { return $this->aggregate('AVG', $column); }
    public function min(string $column) { return $this->aggregate('MIN', $column); }
    public function max(string $column) { return $this->aggregate('MAX', $column); }

    public function insert(array $data): string
    {
        $pdo = $this->getConnection(true); // Force write connection
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($data));
        return $pdo->lastInsertId();
    }

    public function update(array $data): int
    {
        $pdo = $this->getConnection(true); // Force write connection
        $setClauses = [];
        $updateBindings = [];
        foreach ($data as $column => $value) {
            $setClauses[] = "{$column} = ?";
            $updateBindings[] = $value;
        }
        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses);
        if ($this->where) {
            $sql .= $this->buildWhereClause();
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($updateBindings, $this->bindings));
        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $pdo = $this->getConnection(true); // Force write connection
        $sql = "DELETE FROM {$this->table}";
        if ($this->where) {
            $sql .= $this->buildWhereClause();
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt->rowCount();
    }

    // --- SQL Generation & Helpers ---
    private function buildWhereClause(): string { $sql = " WHERE "; $first = true; foreach ($this->where as $condition) { if (!$first) { $sql .= " {$condition['boolean']} "; } $sql .= $condition['clause']; $first = false; } return $sql; }
    public function toSql(): string { $sql = "SELECT " . implode(', ', $this->columns) . " FROM {$this->table}"; if ($this->joins) $sql .= ' ' . implode(' ', $this->joins); if ($this->where) $sql .= $this->buildWhereClause(); if ($this->groupBy) $sql .= " GROUP BY " . implode(', ', $this->groupBy); if ($this->orderBy) $sql .= " ORDER BY " . implode(', ', $this->orderBy); if ($this->limit !== null) $sql .= " LIMIT {$this->limit}"; if ($this->offset !== null) $sql .= " OFFSET {$this->offset}"; return $sql; }
    public function getBindings(): array { return $this->bindings; }

    // --- Transaction Methods ---
    // Transactions must always use the write connection.
    public static function beginTransaction(): void { self::$pdoWrite->beginTransaction(); }
    public static function commit(): void { self::$pdoWrite->commit(); }
    public static function rollBack(): void { self::$pdoWrite->rollBack(); }
}
