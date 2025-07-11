<?php

/**
 * BaseModel Class
 * 
 * Base class for all models with common database operations
 * Implements Active Record pattern and provides CRUD operations
 */

abstract class BaseModel
{
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $hidden = [];
    protected $timestamps = true;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Find record by ID
     */
    public function find($id)
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch();
            if ($result) {
                return $this->hideFields($result);
            }
            return null;
        } catch (PDOException $e) {
            throw new Exception("Error finding record: " . $e->getMessage());
        }
    }

    /**
     * Get all records with optional conditions
     */
    public function all($conditions = [], $orderBy = null, $limit = null, $offset = null)
    {
        try {
            $sql = "SELECT * FROM {$this->table}";
            $params = [];

            // Add WHERE conditions
            if (!empty($conditions)) {
                $whereClause = [];
                foreach ($conditions as $field => $value) {
                    if (is_array($value)) {
                        // Handle operators like ['>', 10] or ['LIKE', '%search%']
                        $operator = $value[0];
                        $whereClause[] = "{$field} {$operator} :{$field}";
                        $params[$field] = $value[1];
                    } else {
                        $whereClause[] = "{$field} = :{$field}";
                        $params[$field] = $value;
                    }
                }
                $sql .= " WHERE " . implode(" AND ", $whereClause);
            }

            // Add ORDER BY
            if ($orderBy) {
                $sql .= " ORDER BY {$orderBy}";
            }

            // Add LIMIT and OFFSET
            if ($limit) {
                $sql .= " LIMIT {$limit}";
                if ($offset) {
                    $sql .= " OFFSET {$offset}";
                }
            }

            $stmt = $this->db->prepare($sql);

            // Bind parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }

            $stmt->execute();
            $results = $stmt->fetchAll();

            // Hide protected fields
            return array_map([$this, 'hideFields'], $results);
        } catch (PDOException $e) {
            throw new Exception("Error fetching records: " . $e->getMessage());
        }
    }

    /**
     * Create new record
     */
    public function create($data)
    {
        try {
            // Filter only fillable fields
            $filteredData = $this->filterFillable($data);

            // Add timestamps if enabled
            if ($this->timestamps) {
                $filteredData['created_at'] = date('Y-m-d H:i:s');
                $filteredData['updated_at'] = date('Y-m-d H:i:s');
            }

            $fields = array_keys($filteredData);
            $placeholders = array_map(function ($field) {
                return ":{$field}";
            }, $fields);

            $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") 
                    VALUES (" . implode(', ', $placeholders) . ")";

            $stmt = $this->db->prepare($sql);

            // Bind parameters
            foreach ($filteredData as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }

            $stmt->execute();

            // Return the created record
            return $this->find($this->db->lastInsertId());
        } catch (PDOException $e) {
            throw new Exception("Error creating record: " . $e->getMessage());
        }
    }

    /**
     * Update record by ID
     */
    public function update($id, $data)
    {
        try {
            // Filter only fillable fields
            $filteredData = $this->filterFillable($data);

            // Add updated timestamp if enabled
            if ($this->timestamps) {
                $filteredData['updated_at'] = date('Y-m-d H:i:s');
            }

            $setClause = [];
            foreach ($filteredData as $field => $value) {
                $setClause[] = "{$field} = :{$field}";
            }

            $sql = "UPDATE {$this->table} SET " . implode(', ', $setClause) .
                " WHERE {$this->primaryKey} = :id";

            $stmt = $this->db->prepare($sql);

            // Bind parameters
            foreach ($filteredData as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            $stmt->execute();

            // Return updated record
            return $this->find($id);
        } catch (PDOException $e) {
            throw new Exception("Error updating record: " . $e->getMessage());
        }
    }

    /**
     * Delete record by ID
     */
    public function delete($id)
    {
        try {
            $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Error deleting record: " . $e->getMessage());
        }
    }

    /**
     * Count records with optional conditions
     */
    public function count($conditions = [])
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM {$this->table}";
            $params = [];

            if (!empty($conditions)) {
                $whereClause = [];
                foreach ($conditions as $field => $value) {
                    if (is_array($value)) {
                        $operator = $value[0];
                        $whereClause[] = "{$field} {$operator} :{$field}";
                        $params[$field] = $value[1];
                    } else {
                        $whereClause[] = "{$field} = :{$field}";
                        $params[$field] = $value;
                    }
                }
                $sql .= " WHERE " . implode(" AND ", $whereClause);
            }

            $stmt = $this->db->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }

            $stmt->execute();
            $result = $stmt->fetch();

            return (int) $result['total'];
        } catch (PDOException $e) {
            throw new Exception("Error counting records: " . $e->getMessage());
        }
    }

    /**
     * Search records with LIKE query
     */
    public function search($searchTerm, $searchFields = [], $limit = null)
    {
        try {
            if (empty($searchFields)) {
                throw new Exception("Search fields must be specified");
            }

            $whereClause = [];
            $params = [];

            foreach ($searchFields as $field) {
                $whereClause[] = "{$field} LIKE :search_{$field}";
                $params["search_{$field}"] = "%{$searchTerm}%";
            }

            $sql = "SELECT * FROM {$this->table} WHERE " . implode(" OR ", $whereClause);

            if ($limit) {
                $sql .= " LIMIT {$limit}";
            }

            $stmt = $this->db->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }

            $stmt->execute();
            $results = $stmt->fetchAll();

            return array_map([$this, 'hideFields'], $results);
        } catch (PDOException $e) {
            throw new Exception("Error searching records: " . $e->getMessage());
        }
    }

    /**
     * Paginate records
     */
    public function paginate($page = 1, $perPage = 10, $conditions = [], $orderBy = null)
    {
        try {
            $offset = ($page - 1) * $perPage;

            // Get total count
            $totalRecords = $this->count($conditions);
            $totalPages = ceil($totalRecords / $perPage);

            // Get records for current page
            $records = $this->all($conditions, $orderBy, $perPage, $offset);

            return [
                'data' => $records,
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $totalRecords,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1,
                'next_page' => $page < $totalPages ? $page + 1 : null,
                'prev_page' => $page > 1 ? $page - 1 : null
            ];
        } catch (Exception $e) {
            throw new Exception("Error paginating records: " . $e->getMessage());
        }
    }

    /**
     * Execute custom query
     */
    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->db->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }

            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error executing query: " . $e->getMessage());
        }
    }

    /**
     * Begin transaction
     */
    public function beginTransaction()
    {
        return $this->db->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit()
    {
        return $this->db->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback()
    {
        return $this->db->rollback();
    }

    /**
     * Filter data to only include fillable fields
     */
    protected function filterFillable($data)
    {
        if (empty($this->fillable)) {
            return $data;
        }

        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Hide protected fields from result
     */
    protected function hideFields($data)
    {
        if (empty($this->hidden)) {
            return $data;
        }

        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    /**
     * Validate required fields
     */
    protected function validateRequired($data, $requiredFields)
    {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Field '{$field}' is required");
            }
        }
    }

    /**
     * Check if record exists
     */
    public function exists($conditions)
    {
        return $this->count($conditions) > 0;
    }

    /**
     * Get first record matching conditions
     */
    public function first($conditions = [], $orderBy = null)
    {
        $results = $this->all($conditions, $orderBy, 1);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Get random records
     */
    public function random($limit = 1)
    {
        try {
            $sql = "SELECT * FROM {$this->table} ORDER BY RAND() LIMIT {$limit}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            $results = $stmt->fetchAll();
            return array_map([$this, 'hideFields'], $results);
        } catch (PDOException $e) {
            throw new Exception("Error getting random records: " . $e->getMessage());
        }
    }
}
