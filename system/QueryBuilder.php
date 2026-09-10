<?php

namespace App\system;

use InvalidArgumentException;
use PDO;
use Throwable;

class QueryBuilder {
    private string $queryType = '';
    private string $table = '';
    private array $select = [];
    private array $joins = [];
    private array $where = [];
    private array $groupBy = [];
    private array $having = [];
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $set = [];
    private array $params = [];
    private int $bindIndex = 0;
    private ?array $passwordCheck = null;

    private const CONDITIONS = [
        '>', '>=', '=', '!=', '<=', '<',
        'IN', 'NOT IN',
        'BETWEEN', 'NOT BETWEEN',
        'IS', 'IS NOT',
        'LIKE', 'NOT LIKE',
    ];

    private const OPTIONS = ['PASSWORD', 'NUMBER', 'ALPHABET', 'PHONE', 'EMAIL'];

    public function __construct(private readonly PDO $pdo) {
    }

    public function table(string $table): static {
        $this->reset();
        $this->table = $this->tableName($table);
        return $this;
    }

    public function select(string ...$columns): static {
        $this->queryType = 'select';
        $this->select = array_map(fn (string $column): string => $this->selectExpression($column), $columns);
        return $this;
    }

    public function insert(array $set): static {
        $this->queryType = 'insert';
        $this->set = $this->makeSet($set);
        return $this;
    }

    public function upsert(array $set): static {
        $this->queryType = 'upsert';
        $this->set = $this->makeSet($set);
        return $this;
    }

    public function update(array $set): static {
        $this->queryType = 'update';
        $this->set = $this->makeSet($set);
        return $this;
    }

    public function delete(): static {
        $this->queryType = 'delete';
        return $this;
    }

    public function where(string $leftCondition, string $operator, int|string|array|null $rightCondition, string $combinator = 'AND'): static {
        $this->where[] = $this->condition($leftCondition, $operator, $rightCondition, $combinator);
        return $this;
    }

    public function join(string $table, string $joinType, string $leftCondition, string $operator, int|string|null $rightCondition): static {
        $joinType = strtoupper($joinType);
        if (!in_array($joinType, ['INNER', 'LEFT', 'RIGHT'], true)) {
            throw new InvalidArgumentException('Unsupported join type.');
        }
        if (!in_array(strtoupper($operator), ['=', '!=', '<', '<=', '>', '>='], true)) {
            throw new InvalidArgumentException('Unsupported join operator.');
        }

        $this->joins[] = $joinType . ' JOIN ' . $this->tableName($table)
            . ' ON ' . $this->identifier($leftCondition) . ' ' . strtoupper($operator) . ' ' . $this->identifier((string) $rightCondition);
        return $this;
    }

    public function groupBy(string ...$columns): static {
        $this->groupBy = array_map(fn (string $column): string => $this->identifier($column), $columns);
        return $this;
    }

    public function having(string $leftCondition, string $operator, int|string|array|null $rightCondition, string $combinator = 'AND'): static {
        $this->having[] = $this->condition($leftCondition, $operator, $rightCondition, $combinator, true);
        return $this;
    }

    public function orderBy(string $column, string $order): static {
        $order = strtoupper($order);
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException('Unsupported sort direction.');
        }
        $this->orderBy[] = $this->identifier($column) . ' ' . $order;
        return $this;
    }

    public function limit(int $limit): static {
        if ($limit < 0) {
            throw new InvalidArgumentException('Limit must be zero or greater.');
        }
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): static {
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset must be zero or greater.');
        }
        $this->offset = $offset;
        return $this;
    }

    public function beginTransaction(): bool {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool {
        if (!$this->pdo->inTransaction()) {
            throw new InvalidArgumentException('A transaction must be started before commit.');
        }
        return $this->pdo->commit();
    }

    public function rollBack(): bool {
        if (!$this->pdo->inTransaction()) {
            throw new InvalidArgumentException('A transaction must be started before rollback.');
        }
        return $this->pdo->rollBack();
    }

    public function transaction(callable $callback): mixed {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function exec(): false|array|int|string {
        $query = $this->query();
        $stmt = $this->pdo->prepare($query);
        foreach ($this->params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        if (!$stmt->execute()) {
            return false;
        }

        return match ($this->queryType) {
            'select' => $this->fetchSelect($stmt),
            'insert', 'upsert' => $this->pdo->lastInsertId(),
            'update', 'delete' => $stmt->rowCount(),
            default => false,
        };
    }

    public function debug(): array {
        return ['query' => $this->query(), 'params' => $this->params];
    }

    private function reset(): void {
        $this->queryType = '';
        $this->table = '';
        $this->select = [];
        $this->joins = [];
        $this->where = [];
        $this->groupBy = [];
        $this->having = [];
        $this->orderBy = [];
        $this->limit = null;
        $this->offset = null;
        $this->set = [];
        $this->params = [];
        $this->bindIndex = 0;
        $this->passwordCheck = null;
    }

    private function query(): string {
        if ($this->table === '' || $this->queryType === '') {
            throw new InvalidArgumentException('A table and query type are required.');
        }

        return match ($this->queryType) {
            'select' => $this->selectQuery(),
            'insert' => 'INSERT INTO ' . $this->table . ' SET ' . implode(', ', $this->set),
            'upsert' => 'INSERT INTO ' . $this->table . ' SET ' . implode(', ', $this->set)
                . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $this->duplicateSet()),
            'update' => $this->writeQuery('UPDATE ' . $this->table . ' SET ' . implode(', ', $this->set)),
            'delete' => $this->writeQuery('DELETE FROM ' . $this->table),
            default => throw new InvalidArgumentException('Unsupported query type.'),
        };
    }

    private function selectQuery(): string {
        if ($this->select === []) {
            throw new InvalidArgumentException('At least one select column is required.');
        }

        $query = 'SELECT ' . implode(', ', $this->select) . ' FROM ' . $this->table;
        if ($this->joins !== []) {
            $query .= ' ' . implode(' ', $this->joins);
        }
        if ($this->where !== []) {
            $query .= ' WHERE ' . $this->combineConditions($this->where);
        }
        if ($this->groupBy !== []) {
            $query .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }
        if ($this->having !== []) {
            $query .= ' HAVING ' . $this->combineConditions($this->having);
        }
        if ($this->orderBy !== []) {
            $query .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }
        if ($this->limit !== null) {
            $query .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $query .= ' OFFSET ' . $this->offset;
        }
        return $query;
    }

    private function writeQuery(string $query): string {
        if ($this->where === []) {
            throw new InvalidArgumentException('Write queries require a where condition.');
        }
        return $query . ' WHERE ' . $this->combineConditions($this->where);
    }

    private function condition(string $left, string $operator, int|string|array|null $right, string $combinator, bool $allowAggregate = false): ?array {
        $operator = strtoupper($operator);
        $combinator = strtoupper($combinator);
        if (!in_array($operator, self::CONDITIONS, true)) {
            throw new InvalidArgumentException('Unsupported condition operator.');
        }
        if (!in_array($combinator, ['AND', 'OR'], true)) {
            throw new InvalidArgumentException('Unsupported condition combinator.');
        }

        $left = $allowAggregate ? $this->selectExpression($left) : $this->identifier($left);
        $optional = $this->optionalValue($right);
        if ($optional !== null && strtoupper($optional['option']) === 'PASSWORD') {
            $this->passwordCheck = ['column' => $left, 'value' => (string) $optional['value']];
            return null;
        }

        if (in_array($operator, ['IS', 'IS NOT'], true)) {
            if (!in_array($right, [null, true, false], true)) {
                throw new InvalidArgumentException('IS conditions only accept null or boolean values.');
            }
            $literal = match ($right) {
                null => 'NULL',
                true => 'TRUE',
                false => 'FALSE',
            };
            return ['sql' => "{$left} {$operator} {$literal}", 'combinator' => $combinator];
        }

        if (in_array($operator, ['IN', 'NOT IN'], true)) {
            if (!is_array($right) || $right === []) {
                throw new InvalidArgumentException('IN conditions require a non-empty array.');
            }
            $placeholders = array_map(fn (mixed $value): string => $this->bind($value), $right);
            return ['sql' => "{$left} {$operator} (" . implode(', ', $placeholders) . ')', 'combinator' => $combinator];
        }

        if (in_array($operator, ['BETWEEN', 'NOT BETWEEN'], true)) {
            if (!is_array($right) || count($right) !== 2) {
                throw new InvalidArgumentException('BETWEEN conditions require exactly two values.');
            }
            $values = array_values($right);
            return [
                'sql' => "{$left} {$operator} " . $this->bind($values[0]) . ' AND ' . $this->bind($values[1]),
                'combinator' => $combinator,
            ];
        }

        return ['sql' => "{$left} {$operator} " . $this->bind($this->normalizeValue($right)), 'combinator' => $combinator];
    }

    private function combineConditions(array $conditions): string {
        $conditions = array_values(array_filter($conditions));
        $sql = '';
        foreach ($conditions as $index => $condition) {
            $sql .= ($index === 0 ? '' : ' ' . $condition['combinator'] . ' ') . $condition['sql'];
        }
        return $sql;
    }

    private function makeSet(array $set): array {
        if ($set === []) {
            throw new InvalidArgumentException('Set values are required.');
        }

        $parts = [];
        foreach ($set as $column => $value) {
            $parts[] = $this->identifier((string) $column) . ' = ' . $this->bind($this->normalizeValue($value));
        }
        return $parts;
    }

    private function duplicateSet(): array {
        return array_map(static function (string $set): string {
            $column = trim(strtok($set, '='));
            return $column . ' = VALUES(' . $column . ')';
        }, $this->set);
    }

    private function normalizeValue(mixed $value): mixed {
        $optional = $this->optionalValue($value);
        if ($optional === null) {
            return $value;
        }

        return match (strtoupper($optional['option'])) {
            'PASSWORD' => password_hash((string) $optional['value'], PASSWORD_DEFAULT),
            'NUMBER' => is_numeric($optional['value'])
                ? $optional['value']
                : throw new InvalidArgumentException('Value must be numeric.'),
            'ALPHABET' => ctype_alpha((string) $optional['value'])
                ? $optional['value']
                : throw new InvalidArgumentException('Value must contain only letters.'),
            'PHONE' => preg_match('/^[0-9]{3}-[0-9]{4}-[0-9]{4}$/', (string) $optional['value'])
                ? $optional['value']
                : throw new InvalidArgumentException('Value must be a phone number.'),
            'EMAIL' => filter_var($optional['value'], FILTER_VALIDATE_EMAIL)
                ? $optional['value']
                : throw new InvalidArgumentException('Value must be an email address.'),
        };
    }

    private function optionalValue(mixed $value): ?array {
        if (is_array($value) && count($value) === 2 && isset($value[1]) && in_array(strtoupper((string) $value[1]), self::OPTIONS, true)) {
            return ['value' => $value[0], 'option' => (string) $value[1]];
        }
        return null;
    }

    private function bind(mixed $value): string {
        $key = ':p' . ++$this->bindIndex;
        $this->params[$key] = $value;
        return $key;
    }

    private function fetchSelect(\PDOStatement $stmt): false|array {
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($this->passwordCheck === null) {
            return $result;
        }
        if (count($result) !== 1 || !array_key_exists($this->passwordCheck['column'], $result[0])) {
            throw new InvalidArgumentException('Password conditions require exactly one selected row.');
        }
        return password_verify($this->passwordCheck['value'], (string) $result[0][$this->passwordCheck['column']]) ? $result : false;
    }

    private function tableName(string $table): string {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\s+(?:AS\s+)?[A-Za-z_][A-Za-z0-9_]*)?$/i', $table)) {
            throw new InvalidArgumentException('Invalid table name.');
        }
        return $table;
    }

    private function identifier(string $identifier): string {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.(?:[A-Za-z_][A-Za-z0-9_]*|\*))?$/', $identifier)) {
            throw new InvalidArgumentException('Invalid SQL identifier.');
        }
        return $identifier;
    }

    private function selectExpression(string $expression): string {
        if ($expression === '*') {
            return '*';
        }
        if (preg_match('/^[A-Z_]+\((?:\*|[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?)\)(?:\s+AS\s+[A-Za-z_][A-Za-z0-9_]*)?$/i', $expression)) {
            return $expression;
        }
        return $this->identifier($expression);
    }
}
