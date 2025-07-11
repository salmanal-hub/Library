<?php

/**
 * Member Model
 * 
 * Handles library member management operations
 */

class Member extends BaseModel
{
    protected $table = 'members';
    protected $fillable = [
        'member_code',
        'full_name',
        'email',
        'phone',
        'address',
        'date_of_birth',
        'gender',
        'member_since',
        'status'
    ];

    /**
     * Create member with auto-generated member code
     */
    public function createMember($data)
    {
        try {
            // Validate required fields
            $this->validateRequired($data, ['full_name', 'email', 'gender']);

            // Check if email already exists
            if ($this->exists(['email' => $data['email']])) {
                throw new Exception("Email already exists");
            }

            // Generate unique member code
            do {
                $memberCode = generateCode('MBR', 3);
            } while ($this->exists(['member_code' => $memberCode]));

            $data['member_code'] = $memberCode;

            // Set default values
            if (!isset($data['member_since'])) {
                $data['member_since'] = date('Y-m-d');
            }
            if (!isset($data['status'])) {
                $data['status'] = 'active';
            }

            return $this->create($data);
        } catch (Exception $e) {
            throw new Exception("Error creating member: " . $e->getMessage());
        }
    }

    /**
     * Search members
     */
    public function searchMembers($searchTerm, $limit = null)
    {
        return $this->search($searchTerm, ['member_code', 'full_name', 'email', 'phone'], $limit);
    }

    /**
     * Get members with pagination and search
     */
    public function getMembersWithPagination($page = 1, $perPage = 10, $searchTerm = '', $status = null)
    {
        try {
            $offset = ($page - 1) * $perPage;

            $sql = "SELECT * FROM {$this->table}";
            $conditions = [];
            $params = [];

            // Add search condition
            if (!empty($searchTerm)) {
                $conditions[] = "(member_code LIKE :search OR full_name LIKE :search OR email LIKE :search OR phone LIKE :search)";
                $params['search'] = "%{$searchTerm}%";
            }

            // Add status filter
            if ($status) {
                $conditions[] = "status = :status";
                $params['status'] = $status;
            }

            // Add WHERE clause if conditions exist
            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }

            $sql .= " ORDER BY full_name ASC LIMIT {$perPage} OFFSET {$offset}";

            $stmt = $this->db->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }

            $stmt->execute();
            $records = $stmt->fetchAll();

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM {$this->table}";

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
            throw new Exception("Error getting members with pagination: " . $e->getMessage());
        }
    }

    /**
     * Get member loan history
     */
    public function getMemberLoanHistory($memberId, $limit = 10)
    {
        try {
            $sql = "SELECT l.*, b.title as book_title, b.author as book_author
                    FROM loans l
                    JOIN books b ON l.book_id = b.id
                    WHERE l.member_id = :member_id
                    ORDER BY l.loan_date DESC
                    LIMIT {$limit}";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':member_id', $memberId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error getting member loan history: " . $e->getMessage());
        }
    }

    /**
     * Get member's current loans
     */
    public function getMemberCurrentLoans($memberId)
    {
        try {
            $sql = "SELECT l.*, b.title as book_title, b.author as book_author
                    FROM loans l
                    JOIN books b ON l.book_id = b.id
                    WHERE l.member_id = :member_id AND l.status = 'borrowed'
                    ORDER BY l.due_date ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':member_id', $memberId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error getting member current loans: " . $e->getMessage());
        }
    }

    /**
     * Get member statistics
     */
    public function getMemberStatistics($memberId)
    {
        try {
            $stats = [];

            // Total loans
            $sql = "SELECT COUNT(*) as total FROM loans WHERE member_id = :member_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':member_id', $memberId, PDO::PARAM_INT);
            $stmt->execute();
            $stats['total_loans'] = $stmt->fetch()['total'];

            // Current loans
            $sql = "SELECT COUNT(*) as current FROM loans WHERE member_id = :member_id AND status = 'borrowed'";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':member_id', $memberId, PDO::PARAM_INT);
            $stmt->execute();
            $stats['current_loans'] = $stmt->fetch()['current'];

            // Returned loans
            $sql = "SELECT COUNT(*) as returned FROM loans WHERE member_id = :member_id AND status = 'returned'";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':member_id', $memberId, PDO::PARAM_INT);
            $stmt->execute();
            $stats['returned_loans'] = $stmt->fetch()['returned'];

            // Overdue loans
            $sql = "SELECT COUNT(*) as overdue FROM loans WHERE member_id = :member_id AND status = 'overdue'";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':member_id', $memberId, PDO::PARAM_INT);
            $stmt->execute();
            $stats['overdue_loans'] = $stmt->fetch()['overdue'];

            // Total fines
            $sql = "SELECT SUM(fine_amount) as total_fines FROM loans WHERE member_id = :member_id AND fine_amount > 0";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':member_id', $memberId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['total_fines'] = $result['total_fines'] ?? 0;

            return $stats;
        } catch (PDOException $e) {
            throw new Exception("Error getting member statistics: " . $e->getMessage());
        }
    }

    /**
     * Get active members
     */
    public function getActiveMembers($limit = null)
    {
        return $this->all(['status' => 'active'], 'full_name ASC', $limit);
    }

    /**
     * Get recent members
     */
    public function getRecentMembers($limit = 10)
    {
        return $this->all([], 'created_at DESC', $limit);
    }

    /**
     * Get new members this month
     */
    public function getNewMembersThisMonth()
    {
        try {
            $firstDayOfMonth = date('Y-m-01');
            $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                    WHERE DATE(member_since) >= :first_day";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':first_day', $firstDayOfMonth);
            $stmt->execute();

            $result = $stmt->fetch();
            return (int) $result['count'];
        } catch (PDOException $e) {
            throw new Exception("Error getting new members this month: " . $e->getMessage());
        }
    }

    /**
     * Update member with validation
     */
    public function updateMember($memberId, $data)
    {
        try {
            // Check if email already exists for other members (if provided)
            if (!empty($data['email'])) {
                $existingMember = $this->first(['email' => $data['email']]);
                if ($existingMember && $existingMember['id'] != $memberId) {
                    throw new Exception("Email already exists for another member");
                }
            }

            // Don't allow direct update of member_code through this method
            unset($data['member_code']);

            return $this->update($memberId, $data);
        } catch (Exception $e) {
            throw new Exception("Error updating member: " . $e->getMessage());
        }
    }

    /**
     * Delete member with validation
     */
    public function deleteMember($memberId)
    {
        try {
            // Check if member has active loans
            $sql = "SELECT COUNT(*) as count FROM loans WHERE member_id = :member_id AND status = 'borrowed'";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':member_id', $memberId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();

            if ($result['count'] > 0) {
                throw new Exception("Cannot delete member with active loans");
            }

            return $this->delete($memberId);
        } catch (Exception $e) {
            throw new Exception("Error deleting member: " . $e->getMessage());
        }
    }

    /**
     * Suspend member
     */
    public function suspendMember($memberId, $reason = '')
    {
        try {
            return $this->update($memberId, ['status' => 'suspended']);
        } catch (Exception $e) {
            throw new Exception("Error suspending member: " . $e->getMessage());
        }
    }

    /**
     * Activate member
     */
    public function activateMember($memberId)
    {
        try {
            return $this->update($memberId, ['status' => 'active']);
        } catch (Exception $e) {
            throw new Exception("Error activating member: " . $e->getMessage());
        }
    }

    /**
     * Check if member can borrow (not suspended, no overdue loans)
     */
    public function canBorrow($memberId)
    {
        try {
            $member = $this->find($memberId);

            if (!$member || $member['status'] !== 'active') {
                return false;
            }

            // Check for overdue loans
            $sql = "SELECT COUNT(*) as count FROM loans 
                    WHERE member_id = :member_id AND status = 'overdue'";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':member_id', $memberId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch();

            return $result['count'] == 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get member borrowing limit
     */
    public function getBorrowingLimit($memberId)
    {
        // Default borrowing limit (can be made configurable)
        $defaultLimit = 5;

        try {
            $currentLoans = count($this->getMemberCurrentLoans($memberId));
            return $defaultLimit - $currentLoans;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get members with overdue loans
     */
    public function getMembersWithOverdueLoans()
    {
        try {
            $sql = "SELECT DISTINCT m.*, COUNT(l.id) as overdue_count
                    FROM {$this->table} m
                    JOIN loans l ON m.id = l.member_id
                    WHERE l.status = 'overdue'
                    GROUP BY m.id
                    ORDER BY overdue_count DESC, m.full_name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error getting members with overdue loans: " . $e->getMessage());
        }
    }

    /**
     * Generate member report data
     */
    public function getMemberReportData($startDate = null, $endDate = null)
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

            // New members in date range
            $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                    WHERE DATE(member_since) BETWEEN :start_date AND :end_date";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();
            $data['new_members'] = $stmt->fetch()['count'];

            // Members by status
            $sql = "SELECT status, COUNT(*) as count FROM {$this->table} 
                    GROUP BY status ORDER BY count DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $data['by_status'] = $stmt->fetchAll();

            // Members by gender
            $sql = "SELECT gender, COUNT(*) as count FROM {$this->table} 
                    GROUP BY gender ORDER BY count DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $data['by_gender'] = $stmt->fetchAll();

            // Most active members (by loan count)
            $sql = "SELECT m.full_name, m.member_code, COUNT(l.id) as loan_count
                    FROM {$this->table} m
                    LEFT JOIN loans l ON m.id = l.member_id 
                    AND DATE(l.loan_date) BETWEEN :start_date AND :end_date
                    GROUP BY m.id
                    HAVING loan_count > 0
                    ORDER BY loan_count DESC
                    LIMIT 10";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();
            $data['most_active'] = $stmt->fetchAll();

            // Members with overdue loans
            $data['with_overdue'] = $this->getMembersWithOverdueLoans();

            return $data;
        } catch (PDOException $e) {
            throw new Exception("Error generating member report: " . $e->getMessage());
        }
    }
}
