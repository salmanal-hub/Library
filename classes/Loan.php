<?php

/**
 * Loan Model
 * 
 * Handles book loan/borrowing management operations
 */

class Loan extends BaseModel
{
    protected $table = 'loans';
    protected $fillable = [
        'loan_code',
        'member_id',
        'book_id',
        'loan_date',
        'due_date',
        'return_date',
        'status',
        'fine_amount',
        'notes'
    ];

    // Constants for loan settings
    const DEFAULT_LOAN_DAYS = 14; // 2 weeks
    const FINE_PER_DAY = 1000; // Rp 1,000 per day

    /**
     * Create loan with validation
     */
    public function createLoan($data)
    {
        try {
            // Validate required fields
            $this->validateRequired($data, ['member_id', 'book_id']);

            // Check member eligibility
            $memberModel = new Member();
            if (!$memberModel->canBorrow($data['member_id'])) {
                throw new Exception("Member is not eligible to borrow books");
            }

            // Check book availability
            $bookModel = new Book();
            if (!$bookModel->isAvailable($data['book_id'])) {
                throw new Exception("Book is not available for borrowing");
            }

            // Generate unique loan code
            do {
                $loanCode = generateCode('LN', 3);
            } while ($this->exists(['loan_code' => $loanCode]));

            $data['loan_code'] = $loanCode;

            // Set default values
            if (!isset($data['loan_date'])) {
                $data['loan_date'] = date('Y-m-d');
            }

            if (!isset($data['due_date'])) {
                $data['due_date'] = date('Y-m-d', strtotime($data['loan_date'] . ' +' . self::DEFAULT_LOAN_DAYS . ' days'));
            }

            if (!isset($data['status'])) {
                $data['status'] = 'borrowed';
            }

            // Start transaction
            $this->beginTransaction();

            try {
                // Create loan record
                $loan = $this->create($data);

                // Update book stock
                $bookModel->borrowBook($data['book_id']);

                $this->commit();
                return $loan;
            } catch (Exception $e) {
                $this->rollback();
                throw $e;
            }
        } catch (Exception $e) {
            throw new Exception("Error creating loan: " . $e->getMessage());
        }
    }

    /**
     * Return book
     */
    public function returnBook($loanId, $returnDate = null, $notes = '')
    {
        try {
            $loan = $this->find($loanId);

            if (!$loan) {
                throw new Exception("Loan not found");
            }

            if ($loan['status'] === 'returned') {
                throw new Exception("Book has already been returned");
            }

            if (!$returnDate) {
                $returnDate = date('Y-m-d');
            }

            // Calculate fine if overdue
            $fineAmount = 0;
            if ($returnDate > $loan['due_date']) {
                $overdueDays = daysDifference($loan['due_date'], $returnDate);
                $fineAmount = $overdueDays * self::FINE_PER_DAY;
            }

            // Start transaction
            $this->beginTransaction();

            try {
                // Update loan record
                $this->update($loanId, [
                    'return_date' => $returnDate,
                    'status' => 'returned',
                    'fine_amount' => $fineAmount,
                    'notes' => $notes
                ]);

                // Update book stock
                $bookModel = new Book();
                $bookModel->returnBook($loan['book_id']);

                $this->commit();

                return [
                    'fine_amount' => $fineAmount,
                    'overdue_days' => $overdueDays ?? 0
                ];
            } catch (Exception $e) {
                $this->rollback();
                throw $e;
            }
        } catch (Exception $e) {
            throw new Exception("Error returning book: " . $e->getMessage());
        }
    }

    /**
     * Get loans with member and book information
     */
    public function getLoansWithDetails($conditions = [], $orderBy = 'l.loan_date DESC', $limit = null, $offset = null)
    {
        try {
            $sql = "SELECT l.*, 
                           m.full_name as member_name, m.member_code, m.email as member_email,
                           b.title as book_title, b.author as book_author, b.isbn
                    FROM {$this->table} l
                    JOIN members m ON l.member_id = m.id
                    JOIN books b ON l.book_id = b.id";

            $params = [];

            // Add WHERE conditions
            if (!empty($conditions)) {
                $whereClause = [];
                foreach ($conditions as $field => $value) {
                    if (is_array($value)) {
                        $operator = $value[0];
                        $whereClause[] = "l.{$field} {$operator} :{$field}";
                        $params[$field] = $value[1];
                    } else {
                        $whereClause[] = "l.{$field} = :{$field}";
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
            throw new Exception("Error fetching loans with details: " . $e->getMessage());
        }
    }

    /**
     * Search loans
     */
    public function searchLoans($searchTerm, $limit = null)
    {
        try {
            $sql = "SELECT l.*, 
                           m.full_name as member_name, m.member_code,
                           b.title as book_title, b.author as book_author
                    FROM {$this->table} l
                    JOIN members m ON l.member_id = m.id
                    JOIN books b ON l.book_id = b.id
                    WHERE l.loan_code LIKE :search 
                    OR m.full_name LIKE :search 
                    OR m.member_code LIKE :search
                    OR b.title LIKE :search 
                    OR b.author LIKE :search
                    ORDER BY l.loan_date DESC";

            if ($limit) {
                $sql .= " LIMIT {$limit}";
            }

            $stmt = $this->db->prepare($sql);
            $searchParam = "%{$searchTerm}%";
            $stmt->bindParam(':search', $searchParam);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error searching loans: " . $e->getMessage());
        }
    }

    /**
     * Get loans with pagination and search
     */
    public function getLoansWithPagination($page = 1, $perPage = 10, $searchTerm = '', $status = null)
    {
        try {
            $offset = ($page - 1) * $perPage;

            $sql = "SELECT l.*, 
                           m.full_name as member_name, m.member_code,
                           b.title as book_title, b.author as book_author
                    FROM {$this->table} l
                    JOIN members m ON l.member_id = m.id
                    JOIN books b ON l.book_id = b.id";

            $conditions = [];
            $params = [];

            // Add search condition
            if (!empty($searchTerm)) {
                $conditions[] = "(l.loan_code LIKE :search OR m.full_name LIKE :search OR m.member_code LIKE :search OR b.title LIKE :search OR b.author LIKE :search)";
                $params['search'] = "%{$searchTerm}%";
            }

            // Add status filter
            if ($status) {
                $conditions[] = "l.status = :status";
                $params['status'] = $status;
            }

            // Add WHERE clause if conditions exist
            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }

            $sql .= " ORDER BY l.loan_date DESC LIMIT {$perPage} OFFSET {$offset}";

            $stmt = $this->db->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }

            $stmt->execute();
            $records = $stmt->fetchAll();

            // Get total count
            $countSql = "SELECT COUNT(*) as total 
                        FROM {$this->table} l
                        JOIN members m ON l.member_id = m.id
                        JOIN books b ON l.book_id = b.id";

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
            throw new Exception("Error getting loans with pagination: " . $e->getMessage());
        }
    }

    /**
     * Get overdue loans
     */
    public function getOverdueLoans()
    {
        try {
            $today = date('Y-m-d');

            // Update status for overdue loans
            $updateSql = "UPDATE {$this->table} 
                         SET status = 'overdue' 
                         WHERE status = 'borrowed' AND due_date < :today";
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->bindParam(':today', $today);
            $updateStmt->execute();

            // Get overdue loans with details
            return $this->getLoansWithDetails(['status' => 'overdue'], 'l.due_date ASC');
        } catch (PDOException $e) {
            throw new Exception("Error getting overdue loans: " . $e->getMessage());
        }
    }

    /**
     * Get recent loans
     */
    public function getRecentLoans($limit = 10)
    {
        return $this->getLoansWithDetails([], 'l.created_at DESC', $limit);
    }

    /**
     * Get returned books today
     */
    public function getReturnedToday()
    {
        try {
            $today = date('Y-m-d');
            return $this->count([
                'return_date' => $today,
                'status' => 'returned'
            ]);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get monthly loan statistics
     */
    public function getMonthlyLoanStatistics()
    {
        try {
            $sql = "SELECT 
                        DATE_FORMAT(loan_date, '%Y-%m') as month,
                        COUNT(*) as count
                    FROM {$this->table}
                    WHERE loan_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                    GROUP BY DATE_FORMAT(loan_date, '%Y-%m')
                    ORDER BY month ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            $results = $stmt->fetchAll();

            // Format month names
            $formatted = [];
            foreach ($results as $row) {
                $formatted[] = [
                    'month' => date('M Y', strtotime($row['month'] . '-01')),
                    'count' => (int)$row['count']
                ];
            }

            return $formatted;
        } catch (PDOException $e) {
            throw new Exception("Error getting monthly loan statistics: " . $e->getMessage());
        }
    }

    /**
     * Get loan statistics
     */
    public function getLoanStatistics()
    {
        try {
            $stats = [];

            // Total loans
            $stats['total_loans'] = $this->count();

            // Active loans
            $stats['active_loans'] = $this->count(['status' => 'borrowed']);

            // Returned loans
            $stats['returned_loans'] = $this->count(['status' => 'returned']);

            // Overdue loans
            $stats['overdue_loans'] = count($this->getOverdueLoans());

            // Total fines collected
            $sql = "SELECT SUM(fine_amount) as total_fines FROM {$this->table} WHERE fine_amount > 0";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['total_fines'] = $result['total_fines'] ?? 0;

            // Average loan duration
            $sql = "SELECT AVG(DATEDIFF(return_date, loan_date)) as avg_duration 
                    FROM {$this->table} 
                    WHERE status = 'returned' AND return_date IS NOT NULL";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['avg_loan_duration'] = round($result['avg_duration'] ?? 0, 1);

            return $stats;
        } catch (PDOException $e) {
            throw new Exception("Error getting loan statistics: " . $e->getMessage());
        }
    }

    /**
     * Extend loan due date
     */
    public function extendLoan($loanId, $extensionDays = 7)
    {
        try {
            $loan = $this->find($loanId);

            if (!$loan) {
                throw new Exception("Loan not found");
            }

            if ($loan['status'] !== 'borrowed') {
                throw new Exception("Only active loans can be extended");
            }

            $newDueDate = date('Y-m-d', strtotime($loan['due_date'] . " +{$extensionDays} days"));

            return $this->update($loanId, ['due_date' => $newDueDate]);
        } catch (Exception $e) {
            throw new Exception("Error extending loan: " . $e->getMessage());
        }
    }

    /**
     * Generate loan report data
     */
    public function getLoanReportData($startDate = null, $endDate = null)
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

            // Loans in date range
            $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                    WHERE DATE(loan_date) BETWEEN :start_date AND :end_date";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();
            $data['loans_in_period'] = $stmt->fetch()['count'];

            // Returns in date range
            $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                    WHERE DATE(return_date) BETWEEN :start_date AND :end_date";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();
            $data['returns_in_period'] = $stmt->fetch()['count'];

            // Fines collected in date range
            $sql = "SELECT SUM(fine_amount) as total_fines FROM {$this->table} 
                    WHERE DATE(return_date) BETWEEN :start_date AND :end_date AND fine_amount > 0";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();
            $result = $stmt->fetch();
            $data['fines_collected'] = $result['total_fines'] ?? 0;

            // Current overdue loans
            $data['overdue_loans'] = $this->getOverdueLoans();

            // Most borrowed books in period
            $sql = "SELECT b.title, b.author, COUNT(l.id) as loan_count
                    FROM {$this->table} l
                    JOIN books b ON l.book_id = b.id
                    WHERE DATE(l.loan_date) BETWEEN :start_date AND :end_date
                    GROUP BY b.id
                    ORDER BY loan_count DESC
                    LIMIT 10";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();
            $data['most_borrowed_books'] = $stmt->fetchAll();

            // Most active members in period
            $sql = "SELECT m.full_name, m.member_code, COUNT(l.id) as loan_count
                    FROM {$this->table} l
                    JOIN members m ON l.member_id = m.id
                    WHERE DATE(l.loan_date) BETWEEN :start_date AND :end_date
                    GROUP BY m.id
                    ORDER BY loan_count DESC
                    LIMIT 10";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();
            $data['most_active_members'] = $stmt->fetchAll();

            return $data;
        } catch (PDOException $e) {
            throw new Exception("Error generating loan report: " . $e->getMessage());
        }
    }
}
