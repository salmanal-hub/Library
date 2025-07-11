<?php

/**
 * Category Model
 * 
 * Handles book category management operations
 */

class Category extends BaseModel
{
    protected $table = 'categories';
    protected $fillable = ['name', 'description'];

    /**
     * Create category with validation
     */
    public function createCategory($data)
    {
        try {
            // Validate required fields
            $this->validateRequired($data, ['name']);

            // Check if category name already exists
            if ($this->exists(['name' => $data['name']])) {
                throw new Exception("Category name already exists");
            }

            return $this->create($data);
        } catch (Exception $e) {
            throw new Exception("Error creating category: " . $e->getMessage());
        }
    }

    /**
     * Update category with validation
     */
    public function updateCategory($categoryId, $data)
    {
        try {
            // Check if category name already exists for other categories
            if (!empty($data['name'])) {
                $existingCategory = $this->first(['name' => $data['name']]);
                if ($existingCategory && $existingCategory['id'] != $categoryId) {
                    throw new Exception("Category name already exists");
                }
            }

            return $this->update($categoryId, $data);
        } catch (Exception $e) {
            throw new Exception("Error updating category: " . $e->getMessage());
        }
    }

    /**
     * Delete category with validation
     */
    public function deleteCategory($categoryId)
    {
        try {
            // Check if category has books
            $sql = "SELECT COUNT(*) as count FROM books WHERE category_id = :category_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();

            if ($result['count'] > 0) {
                throw new Exception("Cannot delete category that has books assigned to it");
            }

            return $this->delete($categoryId);
        } catch (Exception $e) {
            throw new Exception("Error deleting category: " . $e->getMessage());
        }
    }

    /**
     * Get categories with book count
     */
    public function getCategoriesWithBookCount()
    {
        try {
            $sql = "SELECT c.*, COUNT(b.id) as book_count
                    FROM {$this->table} c
                    LEFT JOIN books b ON c.id = b.category_id
                    GROUP BY c.id
                    ORDER BY c.name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error getting categories with book count: " . $e->getMessage());
        }
    }

    /**
     * Search categories
     */
    public function searchCategories($searchTerm, $limit = null)
    {
        return $this->search($searchTerm, ['name', 'description'], $limit);
    }

    /**
     * Get categories with pagination and search
     */
    public function getCategoriesWithPagination($page = 1, $perPage = 10, $searchTerm = '')
    {
        try {
            $offset = ($page - 1) * $perPage;

            $sql = "SELECT c.*, COUNT(b.id) as book_count
                    FROM {$this->table} c
                    LEFT JOIN books b ON c.id = b.category_id";

            $params = [];

            // Add search condition
            if (!empty($searchTerm)) {
                $sql .= " WHERE (c.name LIKE :search OR c.description LIKE :search)";
                $params['search'] = "%{$searchTerm}%";
            }

            $sql .= " GROUP BY c.id ORDER BY c.name ASC LIMIT {$perPage} OFFSET {$offset}";

            $stmt = $this->db->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }

            $stmt->execute();
            $records = $stmt->fetchAll();

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM {$this->table}";

            if (!empty($searchTerm)) {
                $countSql .= " WHERE (name LIKE :search OR description LIKE :search)";
            }

            $countStmt = $this->db->prepare($countSql);

            if (!empty($searchTerm)) {
                $countStmt->bindValue(':search', "%{$searchTerm}%");
            }

            $countStmt->execute();
            $totalRecords = $countStmt->fetch()['total'];

            $totalPages = ceil($totalRecords / $perPage);

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
            throw new Exception("Error getting categories with pagination: " . $e->getMessage());
        }
    }

    /**
     * Get category statistics
     */
    public function getCategoryStatistics()
    {
        try {
            $stats = [];

            // Total categories
            $stats['total_categories'] = $this->count();

            // Categories with books
            $sql = "SELECT COUNT(DISTINCT category_id) as count FROM books WHERE category_id IS NOT NULL";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $stats['categories_with_books'] = $stmt->fetch()['count'];

            // Empty categories
            $stats['empty_categories'] = $stats['total_categories'] - $stats['categories_with_books'];

            // Most popular category
            $sql = "SELECT c.name, COUNT(b.id) as book_count
                    FROM {$this->table} c
                    LEFT JOIN books b ON c.id = b.category_id
                    GROUP BY c.id
                    ORDER BY book_count DESC
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['most_popular'] = $result;

            return $stats;
        } catch (PDOException $e) {
            throw new Exception("Error getting category statistics: " . $e->getMessage());
        }
    }

    /**
     * Get popular categories (by book count)
     */
    public function getPopularCategories($limit = 10)
    {
        try {
            $sql = "SELECT c.*, COUNT(b.id) as book_count
                    FROM {$this->table} c
                    LEFT JOIN books b ON c.id = b.category_id
                    GROUP BY c.id
                    ORDER BY book_count DESC, c.name ASC
                    LIMIT {$limit}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error getting popular categories: " . $e->getMessage());
        }
    }

    /**
     * Get empty categories
     */
    public function getEmptyCategories()
    {
        try {
            $sql = "SELECT c.*
                    FROM {$this->table} c
                    LEFT JOIN books b ON c.id = b.category_id
                    WHERE b.id IS NULL
                    ORDER BY c.name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error getting empty categories: " . $e->getMessage());
        }
    }
}
