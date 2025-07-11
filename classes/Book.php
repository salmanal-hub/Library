<?php

/**
 * Book Model - Complete Implementation
 * 
 * Handles book management operations with full functionality
 */

class Book extends BaseModel
{
    protected $table = 'books';
    protected $fillable = [
        'title',
        'author',
        'isbn',
        'category_id',
        'publisher',
        'year_published',
        'pages',
        'stock',
        'available_stock',
        'description',
        'cover_image'
    ];

    /**
     * Get books with category information
     */
    public function getBooksWithCategory($conditions = [], $orderBy = 'title ASC', $limit = null, $offset = null)
    {
        try {
            $sql = "SELECT b.*, c.name as category_name 
                    FROM {$this->table} b 
                    LEFT JOIN categories c ON b.category_id = c.id";

            $params = [];

            // Add WHERE conditions
            if (!empty($conditions)) {
                $whereClause = [];
                foreach ($conditions as $field => $value) {
                    if (is_array($value)) {
                        $operator = $value[0];
                        $whereClause[] = "b.{$field} {$operator} :{$field}";
                        $params[$field] = $value[1];
                    } else {
                        $whereClause[] = "b.{$field} = :{$field}";
                        $params[$field] = $value;
                    }
                }
                $sql .= " WHERE " . implode(" AND ", $whereClause);
            }

            // Add ORDER BY
            if ($orderBy) {
                $sql .= " ORDER BY " . $orderBy;
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
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error fetching books with category: " . $e->getMessage());
        }
    }

    /**
     * Search books
     */
    public function searchBooks($searchTerm, $limit = null)
    {
        try {
            $sql = "SELECT b.*, c.name as category_name 
                    FROM {$this->table} b 
                    LEFT JOIN categories c ON b.category_id = c.id
                    WHERE b.title LIKE :search 
                    OR b.author LIKE :search 
                    OR b.isbn LIKE :search 
                    OR c.name LIKE :search
                    ORDER BY b.title ASC";

            if ($limit) {
                $sql .= " LIMIT {$limit}";
            }

            $stmt = $this->db->prepare($sql);
            $searchParam = "%{$searchTerm}%";
            $stmt->bindParam(':search', $searchParam);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error searching books: " . $e->getMessage());
        }
    }

    /**
     * Get books with pagination and search
     */
    public function getBooksWithPagination($page = 1, $perPage = 10, $searchTerm = '', $categoryId = null)
    {
        try {
            $offset = ($page - 1) * $perPage;

            $sql = "SELECT b.*, c.name as category_name 
                    FROM {$this->table} b 
                    LEFT JOIN categories c ON b.category_id = c.id";

            $conditions = [];
            $params = [];

            // Add search condition
            if (!empty($searchTerm)) {
                $conditions[] = "(b.title LIKE :search OR b.author LIKE :search OR b.isbn LIKE :search OR c.name LIKE :search)";
                $params['search'] = "%{$searchTerm}%";
            }

            // Add category filter
            if ($categoryId) {
                $conditions[] = "b.category_id = :category_id";
                $params['category_id'] = $categoryId;
            }

            // Add WHERE clause if conditions exist
            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }

            $sql .= " ORDER BY b.title ASC LIMIT {$perPage} OFFSET {$offset}";

            $stmt = $this->db->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }

            $stmt->execute();
            $records = $stmt->fetchAll();

            // Get total count
            $countSql = "SELECT COUNT(*) as total 
                        FROM {$this->table} b 
                        LEFT JOIN categories c ON b.category_id = c.id";

            if (!empty($conditions)) {
                $countSql .= " WHERE " . implode(" AND ", $conditions);
            }

            $countStmt = $this->db->prepare($countSql);

            foreach ($params as $key => $value) {
                $countStmt->bindValue(":{$key}", $value);
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
            throw new Exception("Error getting books with pagination: " . $e->getMessage());
        }
    }

    /**
     * Create book with validation
     */
    public function createBook($data)
    {
        try {
            // Validate required fields
            $this->validateRequired($data, ['title', 'author', 'category_id']);

            // Check if ISBN already exists (if provided)
            if (!empty($data['isbn']) && $this->exists(['isbn' => $data['isbn']])) {
                throw new Exception("ISBN already exists");
            }

            // Set default values
            if (!isset($data['stock'])) {
                $data['stock'] = 1;
            }
            if (!isset($data['available_stock'])) {
                $data['available_stock'] = $data['stock'];
            }

            return $this->create($data);
        } catch (Exception $e) {
            throw new Exception("Error creating book: " . $e->getMessage());
        }
    }

    /**
     * Update book stock when borrowed
     */
    public function borrowBook($bookId)
    {
        try {
            $book = $this->find($bookId);

            if (!$book) {
                throw new Exception("Book not found");
            }

            if ($book['available_stock'] <= 0) {
                throw new Exception("Book is not available for borrowing");
            }

            return $this->update($bookId, [
                'available_stock' => $book['available_stock'] - 1
            ]);
        } catch (Exception $e) {
            throw new Exception("Error borrowing book: " . $e->getMessage());
        }
    }

    /**
     * Update book stock when returned
     */
    public function returnBook($bookId)
    {
        try {
            $book = $this->find($bookId);

            if (!$book) {
                throw new Exception("Book not found");
            }

            if ($book['available_stock'] >= $book['stock']) {
                throw new Exception("Book return error: stock inconsistency");
            }

            return $this->update($bookId, [
                'available_stock' => $book['available_stock'] + 1
            ]);
        } catch (Exception $e) {
            throw new Exception("Error returning book: " . $e->getMessage());
        }
    }

    /**
     * Get available books count
     */
    public function getAvailableBooksCount()
    {
        try {
            $sql = "SELECT SUM(available_stock) as total FROM {$this->table}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch();

            return (int) ($result['total'] ?? 0);
        } catch (PDOException $e) {
            throw new Exception("Error getting available books count: " . $e->getMessage());
        }
    }

    /**
     * Get popular books (most borrowed)
     */
    public function getPopularBooks($limit = 10)
    {
        try {
            $sql = "SELECT b.*, c.name as category_name, COUNT(l.id) as loan_count
                    FROM {$this->table} b 
                    LEFT JOIN categories c ON b.category_id = c.id
                    LEFT JOIN loans l ON b.id = l.book_id
                    GROUP BY b.id
                    ORDER BY loan_count DESC, b.title ASC
                    LIMIT {$limit}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error getting popular books: " . $e->getMessage());
        }
    }

    /**
     * Get books with low stock
     */
    public function getLowStockBooks($threshold = 2, $limit = 10)
    {
        try {
            $sql = "SELECT b.*, c.name as category_name
                    FROM {$this->table} b 
                    LEFT JOIN categories c ON b.category_id = c.id
                    WHERE b.available_stock <= :threshold
                    ORDER BY b.available_stock ASC, b.title ASC
                    LIMIT {$limit}";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':threshold', $threshold, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error getting low stock books: " . $e->getMessage());
        }
    }

    /**
     * Get category statistics
     */
    public function getCategoryStatistics()
    {
        try {
            $sql = "SELECT c.name, COUNT(b.id) as count
                    FROM categories c
                    LEFT JOIN {$this->table} b ON c.id = b.category_id
                    GROUP BY c.id, c.name
                    ORDER BY count DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error getting category statistics: " . $e->getMessage());
        }
    }

    /**
     * Get book statistics
     */
    public function getBookStatistics()
    {
        try {
            $stats = [];

            // Total books
            $stats['total_books'] = $this->count();

            // Available books
            $stats['available_books'] = $this->getAvailableBooksCount();

            // Books by category
            $stats['by_category'] = $this->getCategoryStatistics();

            // Low stock books
            $stats['low_stock'] = count($this->getLowStockBooks());

            // Average pages
            $sql = "SELECT AVG(pages) as avg_pages FROM {$this->table} WHERE pages > 0";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['avg_pages'] = round($result['avg_pages'] ?? 0);

            // Books published this year
            $currentYear = date('Y');
            $stats['published_this_year'] = $this->count(['year_published' => $currentYear]);

            return $stats;
        } catch (PDOException $e) {
            throw new Exception("Error getting book statistics: " . $e->getMessage());
        }
    }

    /**
     * Get recently added books
     */
    public function getRecentBooks($limit = 10)
    {
        return $this->getBooksWithCategory([], 'b.created_at DESC', $limit);
    }

    /**
     * Check book availability
     */
    public function isAvailable($bookId)
    {
        try {
            $book = $this->find($bookId);
            return $book && $book['available_stock'] > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get books by author
     */
    public function getBooksByAuthor($author, $limit = null)
    {
        try {
            $conditions = ['author' => ['LIKE', "%{$author}%"]];
            return $this->getBooksWithCategory($conditions, 'b.title ASC', $limit);
        } catch (Exception $e) {
            throw new Exception("Error getting books by author: " . $e->getMessage());
        }
    }

    /**
     * Get books by category
     */
    public function getBooksByCategory($categoryId, $limit = null)
    {
        try {
            $conditions = ['category_id' => $categoryId];
            return $this->getBooksWithCategory($conditions, 'b.title ASC', $limit);
        } catch (Exception $e) {
            throw new Exception("Error getting books by category: " . $e->getMessage());
        }
    }

    /**
     * Update book with validation
     */
    public function updateBook($bookId, $data)
    {
        try {
            // Check if ISBN already exists for other books (if provided)
            if (!empty($data['isbn'])) {
                $existingBook = $this->first(['isbn' => $data['isbn']]);
                if ($existingBook && $existingBook['id'] != $bookId) {
                    throw new Exception("ISBN already exists for another book");
                }
            }

            // Don't allow direct update of available_stock through this method
            unset($data['available_stock']);

            return $this->update($bookId, $data);
        } catch (Exception $e) {
            throw new Exception("Error updating book: " . $e->getMessage());
        }
    }

    /**
     * Delete book with validation
     */
    public function deleteBook($bookId)
    {
        try {
            // Check if book has active loans
            $sql = "SELECT COUNT(*) as count FROM loans WHERE book_id = :book_id AND status = 'borrowed'";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();

            if ($result['count'] > 0) {
                throw new Exception("Cannot delete book with active loans");
            }

            return $this->delete($bookId);
        } catch (Exception $e) {
            throw new Exception("Error deleting book: " . $e->getMessage());
        }
    }

    /**
     * Get book loan history
     */
    public function getBookLoanHistory($bookId, $limit = 10)
    {
        try {
            $sql = "SELECT l.*, m.full_name as member_name, m.member_code
                    FROM loans l
                    JOIN members m ON l.member_id = m.id
                    WHERE l.book_id = :book_id
                    ORDER BY l.loan_date DESC
                    LIMIT {$limit}";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error getting book loan history: " . $e->getMessage());
        }
    }

    /**
     * Generate book report data
     */
    public function getBookReportData($startDate = null, $endDate = null)
    {
        try {
            $data = [];

            // Set default date range if not provided
            if (!$startDate) {
                $startDate = date('Y-m-01'); // First day of current month
            }
            if (!$endDate) {
                $endDate = date('Y-m-d'); // Today
            }

            // Books added in date range
            $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                    WHERE DATE(created_at) BETWEEN :start_date AND :end_date";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();
            $data['books_added'] = $stmt->fetch()['count'];

            // Most borrowed books in date range
            $sql = "SELECT b.title, b.author, COUNT(l.id) as loan_count
                    FROM {$this->table} b
                    LEFT JOIN loans l ON b.id = l.book_id 
                    AND DATE(l.loan_date) BETWEEN :start_date AND :end_date
                    GROUP BY b.id
                    HAVING loan_count > 0
                    ORDER BY loan_count DESC
                    LIMIT 10";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();
            $data['most_borrowed'] = $stmt->fetchAll();

            // Books by category
            $data['by_category'] = $this->getCategoryStatistics();

            // Low stock alert
            $data['low_stock'] = $this->getLowStockBooks();

            return $data;
        } catch (PDOException $e) {
            throw new Exception("Error generating book report: " . $e->getMessage());
        }
    }

    /**
     * Validate required fields
     */


    /**
     * Export books to CSV
     */
    public function exportToCSV($conditions = [])
    {
        try {
            $books = $this->getBooksWithCategory($conditions, 'b.title ASC');

            $filename = 'books_export_' . date('Y-m-d_H-i-s') . '.csv';
            $filepath = 'exports/' . $filename;

            // Create exports directory if it doesn't exist
            if (!is_dir('exports')) {
                mkdir('exports', 0755, true);
            }

            $file = fopen($filepath, 'w');

            // Write CSV headers
            fputcsv($file, [
                'ID',
                'Title',
                'Author',
                'ISBN',
                'Category',
                'Publisher',
                'Year Published',
                'Pages',
                'Stock',
                'Available Stock',
                'Description'
            ]);

            // Write data rows
            foreach ($books as $book) {
                fputcsv($file, [
                    $book['id'],
                    $book['title'],
                    $book['author'],
                    $book['isbn'],
                    $book['category_name'],
                    $book['publisher'],
                    $book['year_published'],
                    $book['pages'],
                    $book['stock'],
                    $book['available_stock'],
                    $book['description']
                ]);
            }

            fclose($file);

            return $filepath;
        } catch (Exception $e) {
            throw new Exception("Error exporting books to CSV: " . $e->getMessage());
        }
    }

    /**
     * Import books from CSV
     */
    public function importFromCSV($filepath)
    {
        try {
            if (!file_exists($filepath)) {
                throw new Exception("CSV file not found");
            }

            $file = fopen($filepath, 'r');
            $headers = fgetcsv($file); // Skip headers

            $imported = 0;
            $errors = [];

            while (($row = fgetcsv($file)) !== FALSE) {
                try {
                    $data = [
                        'title' => $row[1] ?? '',
                        'author' => $row[2] ?? '',
                        'isbn' => $row[3] ?? '',
                        'category_id' => $row[4] ?? 1, // Default category
                        'publisher' => $row[5] ?? '',
                        'year_published' => $row[6] ?? null,
                        'pages' => $row[7] ?? null,
                        'stock' => $row[8] ?? 1,
                        'description' => $row[9] ?? ''
                    ];

                    $this->createBook($data);
                    $imported++;
                } catch (Exception $e) {
                    $errors[] = "Row " . ($imported + count($errors) + 2) . ": " . $e->getMessage();
                }
            }

            fclose($file);

            return [
                'imported' => $imported,
                'errors' => $errors
            ];
        } catch (Exception $e) {
            throw new Exception("Error importing books from CSV: " . $e->getMessage());
        }
    }
}
