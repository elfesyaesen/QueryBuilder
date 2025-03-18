<?php

class QueryBuilder
{
    private PDO $pdo;
    private string $query = '';
    private array $params = [];
    private bool $isTransaction = false;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function select(string|array $columns = '*'): self
    {
        $columns = is_array($columns) ? implode(', ', array_map([$this, 'sanitizeColumn'], $columns)) : $this->sanitizeColumn($columns);
        $this->query = "SELECT $columns ";
        return $this;
    }

    public function from(string $table, ?string $alias = null): self
    {
        $this->query .= "FROM " . $this->sanitizeTable($table);
        if ($alias) {
            $this->query .= " AS " . $this->sanitizeColumn($alias);
        }
        $this->query .= " ";
        return $this;
    }

    public function where(string $column, Operator $operator, mixed $value): self
    {
        return $this->addCondition('WHERE ', $column, $operator, $value);
    }

    public function andWhere(string $column, Operator $operator, mixed $value): self
    {
        return $this->addCondition('AND', $column, $operator, $value);
    }

    public function orWhere(string $column, Operator $operator, mixed $value): self
    {
        return $this->addCondition('OR', $column, $operator, $value);
    }

    public function whereIn(string $column, array $values): self
    {
        $placeholders = implode(', ', array_map(fn($index) => ":in_$index", array_keys($values)));
        $this->query .= ($this->hasWhereClause() ? 'AND ' : 'WHERE ') . $this->sanitizeColumn($column) . " IN ($placeholders) ";
        foreach ($values as $index => $value) {
            $this->params[":in_$index"] = $value;
        }
        return $this;
    }

    public function whereBetween(string $column, mixed $start, mixed $end): self
    {
        $param1 = ":between_start_" . uniqid();
        $param2 = ":between_end_" . uniqid();
        $this->query .= ($this->hasWhereClause() ? 'AND ' : 'WHERE ') . $this->sanitizeColumn($column) . " BETWEEN $param1 AND $param2 ";
        $this->params[$param1] = $start;
        $this->params[$param2] = $end;
        return $this;
    }

    public function whereNull(string $column): self
    {
        return $this->addCondition(($this->hasWhereClause() ? 'AND' : 'WHERE'), $column, Operator::IS_NULL, null);
    }

    public function whereNotNull(string $column): self
    {
        return $this->addCondition(($this->hasWhereClause() ? 'AND' : 'WHERE'), $column, Operator::IS_NOT_NULL, null);
    }

    public function join(string $type, string $table, string $firstColumn, Operator $operator, string $secondColumn): self
    {
        $this->query .= "$type " . $this->sanitizeTable($table) . " ON " . $this->sanitizeColumn($firstColumn) . " " . $operator->value . " " . $this->sanitizeColumn($secondColumn) . " ";
        return $this;
    }

    public function innerJoin(string $table, string $firstColumn, Operator $operator, string $secondColumn): self
    {
        return $this->join('INNER JOIN', $table, $firstColumn, $operator, $secondColumn);
    }

    public function leftJoin(string $table, string $firstColumn, Operator $operator, string $secondColumn): self
    {
        return $this->join('LEFT JOIN', $table, $firstColumn, $operator, $secondColumn);
    }

    public function rightJoin(string $table, string $firstColumn, Operator $operator, string $secondColumn): self
    {
        return $this->join('RIGHT JOIN', $table, $firstColumn, $operator, $secondColumn);
    }

    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_map([$this, 'sanitizeColumn'], array_keys($data)));
        $placeholders = ':' . implode(', :', array_keys($data));
        $this->query = "INSERT INTO " . $this->sanitizeTable($table) . " ($columns) VALUES ($placeholders)";
        $this->params = $this->prepareParams($data);

        $stmt = $this->execute();
        return $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, ?string $whereColumn = null, mixed $whereValue = null): int
    {
        $setClause = implode(', ', array_map(fn($col) => $this->sanitizeColumn($col) . " = :$col", array_keys($data)));
        $this->query = "UPDATE " . $this->sanitizeTable($table) . " SET $setClause ";
        $this->params = $this->prepareParams($data);

        if ($whereColumn && $whereValue !== null) {
            $this->query .= "WHERE " . $this->sanitizeColumn($whereColumn) . " = :where_" . $this->sanitizeColumn($whereColumn);
            $this->params[":where_" . $this->sanitizeColumn($whereColumn)] = $whereValue;
        }

        $stmt = $this->execute();
        return $stmt->rowCount();
    }

    public function delete(string $table): self
    {
        $this->query = "DELETE FROM " . $this->sanitizeTable($table) . " ";
        return $this;
    }

    public function groupBy(string|array $columns): self
    {
        $columns = is_array($columns) ? implode(', ', array_map([$this, 'sanitizeColumn'], $columns)) : $this->sanitizeColumn($columns);
        $this->query .= "GROUP BY $columns ";
        return $this;
    }

    public function orderBy(string|array $columns, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        $columns = is_array($columns) ? implode(', ', array_map([$this, 'sanitizeColumn'], $columns)) : $this->sanitizeColumn($columns);
        $this->query .= "ORDER BY $columns $direction ";
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->query .= "LIMIT $limit ";
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->query .= "OFFSET $offset ";
        return $this;
    }

    public function paginate(int $page, int $perPage): self
    {
        return $this->limit($perPage)->offset(($page - 1) * $perPage);
    }

    public function rawSql(string $sql): self
    {
        $this->query .= $sql . " ";
        return $this;
    }

    public function subQuery(QueryBuilder $subQuery, string $alias): self
    {
        $subQuerySql = $subQuery->getQuery();
        $subQueryParams = $subQuery->getParams();

        $this->query .= "(" . $subQuerySql . ") AS " . $this->sanitizeColumn($alias) . " ";
        $this->params = array_merge($this->params, $subQueryParams);
        return $this;
    }

    public function execute(): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($this->query);
            $stmt->execute($this->params);
            $this->clearParams();
            return $stmt;
        } catch (PDOException $e) {
            throw new RuntimeException("Sorgu çalıştırma hatası : " . $e->getMessage());
        }
    }

    public function get(): array
    {
        return $this->execute()->fetchAll(PDO::FETCH_ASSOC);
    }

    public function first(): ?array
    {
        return $this->execute()->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    private function addCondition(string $type, string $column, Operator $operator, mixed $value): self
    {
        if ($operator === Operator::IS_NULL || $operator === Operator::IS_NOT_NULL) {
            $this->query .= "$type " . $this->sanitizeColumn($column) . " " . $operator->value . " ";
        } elseif ($operator === Operator::IN && $value instanceof QueryBuilder) {
            $subQuery = $value;
            $subQuerySql = $subQuery->getQuery();
            $subQueryParams = $subQuery->getParams();

            $this->query .= "$type " . $this->sanitizeColumn($column) . " IN ($subQuerySql) ";
            $this->params = array_merge($this->params, $subQueryParams);
        } else {
            $paramKey = ":cond_" . uniqid();
            $this->query .= "$type " . $this->sanitizeColumn($column) . " " . $operator->value . " $paramKey ";
            $this->params[$paramKey] = $value;
        }
        return $this;
    }

    private function hasWhereClause(): bool
    {
        return stripos($this->query, 'WHERE') !== false || stripos($this->query, 'HAVING') !== false;
    }

    private function prepareParams(array $data): array
    {
        $params = [];
        foreach ($data as $key => $value) {
            $params[":" . $this->sanitizeColumn($key)] = $value;
        }
        return $params;
    }

    private function sanitizeColumn(string $column): string
    {
        return preg_replace('/[^a-zA-Z0-9*_.]/', '', $column);
    }

    private function sanitizeTable(string $table): string
    {
        return preg_replace('/[^a-zA-Z0-9_.]/', '', $table);
    }

    private function clearParams(): void
    {
        $this->params = [];
    }

    public function beginTransaction(): self
    {
        if ($this->isTransaction) {
            throw new RuntimeException("Transaction aktif durumda.");
        }
        $this->pdo->beginTransaction();
        $this->isTransaction = true;
        return $this;
    }

    public function commit(): self
    {
        if (!$this->isTransaction) {
            throw new RuntimeException("Etkin bir işlem yok.");
        }
        $this->pdo->commit();
        $this->isTransaction = false;
        return $this;
    }

    public function rollBack(): self
    {
        if (!$this->isTransaction) {
            throw new RuntimeException("Geri alınacak etkin işlem yok.");
        }
        $this->pdo->rollBack();
        $this->isTransaction = false;
        return $this;
    }

    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }
}

enum Operator: string
{
    case EQUALS = '=';
    case NOT_EQUALS = '!=';
    case GREATER_THAN = '>';
    case LESS_THAN = '<';
    case GREATER_THAN_OR_EQUAL = '>=';
    case LESS_THAN_OR_EQUAL = '<=';
    case LIKE = 'LIKE';
    case NOT_LIKE = 'NOT LIKE';
    case IN = 'IN';
    case NOT_IN = 'NOT IN';
    case BETWEEN = 'BETWEEN';
    case NOT_BETWEEN = 'NOT BETWEEN';
    case IS_NULL = 'IS NULL';
    case IS_NOT_NULL = 'IS NOT NULL';
}
