<?php

/**
 * User Model
 * 
 * Handles user authentication and user management
 * Modified to work without password hashing
 */

class User extends BaseModel
{
    protected $table = 'users';
    protected $fillable = ['username', 'password', 'email', 'full_name', 'role'];
    protected $hidden = ['password'];

    /**
     * Authenticate user login - TANPA HASH
     */
    public function authenticate($username, $password)
    {
        try {
            // Find user by username
            $sql = "SELECT * FROM {$this->table} WHERE username = :username";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->execute();

            $user = $stmt->fetch();

            // PERUBAHAN: Bandingkan password langsung tanpa hash
            if ($user && $user['password'] === $password) {
                // Remove password from returned data
                unset($user['password']);
                return $user;
            }

            return false;
        } catch (PDOException $e) {
            throw new Exception("Authentication error: " . $e->getMessage());
        }
    }

    /**
     * Create new user - TANPA HASH PASSWORD
     */
    public function createUser($data)
    {
        try {
            // Validate required fields
            $this->validateRequired($data, ['username', 'password', 'email', 'full_name']);

            // Check if username already exists
            if ($this->exists(['username' => $data['username']])) {
                throw new Exception("Username already exists");
            }

            // Check if email already exists
            if ($this->exists(['email' => $data['email']])) {
                throw new Exception("Email already exists");
            }

            // PERUBAHAN: Simpan password tanpa hash
            // $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);

            // Set default role if not provided
            if (!isset($data['role'])) {
                $data['role'] = 'staff';
            }

            return $this->create($data);
        } catch (Exception $e) {
            throw new Exception("Error creating user: " . $e->getMessage());
        }
    }

    /**
     * Update user password - TANPA HASH
     */
    public function updatePassword($userId, $currentPassword, $newPassword)
    {
        try {
            // Get user with password
            $sql = "SELECT password FROM {$this->table} WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $user = $stmt->fetch();

            if (!$user) {
                throw new Exception("User not found");
            }

            // PERUBAHAN: Verify current password tanpa hash
            if ($currentPassword !== $user['password']) {
                throw new Exception("Current password is incorrect");
            }

            // PERUBAHAN: Update dengan password baru tanpa hash
            return $this->update($userId, ['password' => $newPassword]);
        } catch (Exception $e) {
            throw new Exception("Error updating password: " . $e->getMessage());
        }
    }

    /**
     * Get user statistics
     */
    public function getStatistics()
    {
        try {
            $stats = [];

            // Total users
            $stats['total_users'] = $this->count();

            // Users by role
            $sql = "SELECT role, COUNT(*) as count FROM {$this->table} GROUP BY role";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $roleStats = $stmt->fetchAll();

            foreach ($roleStats as $stat) {
                $stats['by_role'][$stat['role']] = $stat['count'];
            }

            // Recent users (last 30 days)
            $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
            $stats['recent_users'] = $this->count(['created_at' => ['>=', $thirtyDaysAgo]]);

            return $stats;
        } catch (PDOException $e) {
            throw new Exception("Error getting user statistics: " . $e->getMessage());
        }
    }

    /**
     * Search users
     */
    public function searchUsers($searchTerm, $limit = null)
    {
        return $this->search($searchTerm, ['username', 'full_name', 'email'], $limit);
    }

    /**
     * Get active users
     */
    public function getActiveUsers()
    {
        // For now, return all users. In a real system, you might track last login
        return $this->all([], 'full_name ASC');
    }

    /**
     * Update user profile
     */
    public function updateProfile($userId, $data)
    {
        try {
            // Remove password and username from profile updates
            unset($data['password'], $data['username']);

            // Validate email uniqueness if email is being updated
            if (isset($data['email'])) {
                $existingUser = $this->first(['email' => $data['email']]);
                if ($existingUser && $existingUser['id'] != $userId) {
                    throw new Exception("Email already exists");
                }
            }

            return $this->update($userId, $data);
        } catch (Exception $e) {
            throw new Exception("Error updating profile: " . $e->getMessage());
        }
    }

    /**
     * Check if user has permission
     */
    public function hasPermission($userId, $permission)
    {
        try {
            $user = $this->find($userId);

            if (!$user) {
                return false;
            }

            // Admin has all permissions
            if ($user['role'] === 'admin') {
                return true;
            }

            // Define permissions for staff
            $staffPermissions = [
                'view_books',
                'create_books',
                'edit_books',
                'view_members',
                'create_members',
                'edit_members',
                'view_loans',
                'create_loans',
                'edit_loans',
                'return_books',
                'view_categories'
            ];

            // Admin-only permissions
            $adminPermissions = [
                'delete_books',
                'delete_members',
                'delete_loans',
                'create_categories',
                'edit_categories',
                'delete_categories',
                'view_users',
                'create_users',
                'edit_users',
                'delete_users',
                'view_reports',
                'system_settings'
            ];

            if ($user['role'] === 'staff') {
                return in_array($permission, $staffPermissions);
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get users with pagination and search
     */
    public function getUsersWithPagination($page = 1, $perPage = 10, $searchTerm = '')
    {
        try {
            $conditions = [];

            if (!empty($searchTerm)) {
                // Custom search query for multiple fields
                $sql = "SELECT * FROM {$this->table} 
                        WHERE username LIKE :search 
                        OR full_name LIKE :search 
                        OR email LIKE :search 
                        ORDER BY full_name ASC";

                $offset = ($page - 1) * $perPage;
                $sql .= " LIMIT {$perPage} OFFSET {$offset}";

                $stmt = $this->db->prepare($sql);
                $searchParam = "%{$searchTerm}%";
                $stmt->bindParam(':search', $searchParam);
                $stmt->execute();
                $records = $stmt->fetchAll();

                // Get total count for search
                $countSql = "SELECT COUNT(*) as total FROM {$this->table} 
                           WHERE username LIKE :search 
                           OR full_name LIKE :search 
                           OR email LIKE :search";
                $countStmt = $this->db->prepare($countSql);
                $countStmt->bindParam(':search', $searchParam);
                $countStmt->execute();
                $totalRecords = $countStmt->fetch()['total'];

                $totalPages = ceil($totalRecords / $perPage);

                return [
                    'data' => array_map([$this, 'hideFields'], $records),
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_records' => $totalRecords,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1,
                    'next_page' => $page < $totalPages ? $page + 1 : null,
                    'prev_page' => $page > 1 ? $page - 1 : null
                ];
            } else {
                return $this->paginate($page, $perPage, $conditions, 'full_name ASC');
            }
        } catch (Exception $e) {
            throw new Exception("Error getting users with pagination: " . $e->getMessage());
        }
    }

    /**
     * Helper method untuk debug - CEK PASSWORD DI DATABASE
     */
    public function debugUser($username)
    {
        try {
            $sql = "SELECT username, password, role FROM {$this->table} WHERE username = :username";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->execute();

            return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }
}
