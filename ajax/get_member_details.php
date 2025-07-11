<?php

/**
 * AJAX handler for member details modal
 * File: ajax/get_member_details.php
 */

// Adjust the path to config.php based on your folder structure
require_once '../config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Access denied. Please login.</div>';
    exit;
}

// Include required classes
require_once '../classes/BaseModel.php';
require_once '../classes/Member.php';

// Get member ID
$memberId = (int)($_GET['id'] ?? 0);

if (!$memberId) {
    echo '<div class="alert alert-danger">Invalid member ID.</div>';
    exit;
}

try {
    $memberModel = new Member();
    $member = $memberModel->find($memberId);

    if (!$member) {
        echo '<div class="alert alert-warning">Member not found.</div>';
        exit;
    }

    // Get member current loans
    $currentLoans = $memberModel->getMemberCurrentLoans($memberId);

    // Get member loan history
    $loanHistory = $memberModel->getMemberLoanHistory($memberId, 5);

    // Calculate age if date of birth is available
    $age = null;
    if ($member['date_of_birth']) {
        $birthDate = new DateTime($member['date_of_birth']);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
    }

    // Calculate days since member
    $memberSince = new DateTime($member['member_since']);
    $today = new DateTime();
    $daysSinceMember = $today->diff($memberSince)->days;

?>
    <div class="row">
        <div class="col-md-4">
            <div class="text-center mb-4">
                <div class="member-avatar mx-auto mb-3" style="width: 120px; height: 120px; font-size: 2rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                    <?php echo strtoupper(substr($member['full_name'], 0, 2)); ?>
                </div>
                <h4><?php echo htmlspecialchars($member['full_name']); ?></h4>
                <p class="text-muted mb-2"><?php echo htmlspecialchars($member['member_code']); ?></p>
                <?php
                $statusClass = [
                    'active' => 'bg-success',
                    'inactive' => 'bg-secondary',
                    'suspended' => 'bg-danger'
                ];
                ?>
                <span class="badge <?php echo $statusClass[$member['status']] ?? 'bg-secondary'; ?> fs-6">
                    <?php echo ucfirst($member['status']); ?>
                </span>
            </div>
        </div>

        <div class="col-md-8">
            <h5 class="mb-3">Personal Information</h5>

            <div class="row mb-2">
                <div class="col-sm-4"><strong>Email:</strong></div>
                <div class="col-sm-8">
                    <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>">
                        <?php echo htmlspecialchars($member['email']); ?>
                    </a>
                </div>
            </div>

            <?php if ($member['phone']): ?>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>Phone:</strong></div>
                    <div class="col-sm-8">
                        <a href="tel:<?php echo htmlspecialchars($member['phone']); ?>">
                            <?php echo htmlspecialchars($member['phone']); ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row mb-2">
                <div class="col-sm-4"><strong>Gender:</strong></div>
                <div class="col-sm-8">
                    <i class="fas fa-<?php echo $member['gender'] === 'male' ? 'mars' : 'venus'; ?> me-1"></i>
                    <?php echo ucfirst($member['gender']); ?>
                </div>
            </div>

            <?php if ($member['date_of_birth']): ?>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>Date of Birth:</strong></div>
                    <div class="col-sm-8">
                        <?php echo date('F d, Y', strtotime($member['date_of_birth'])); ?>
                        <?php if ($age !== null): ?>
                            <small class="text-muted">(<?php echo $age; ?> years old)</small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($member['address']): ?>
                <div class="row mb-2">
                    <div class="col-sm-4"><strong>Address:</strong></div>
                    <div class="col-sm-8"><?php echo nl2br(htmlspecialchars($member['address'])); ?></div>
                </div>
            <?php endif; ?>

            <div class="row mb-2">
                <div class="col-sm-4"><strong>Member Since:</strong></div>
                <div class="col-sm-8">
                    <?php echo date('F d, Y', strtotime($member['member_since'])); ?>
                    <small class="text-muted">
                        (<?php echo $daysSinceMember; ?> days ago)
                    </small>
                </div>
            </div>

            <div class="row mb-2">
                <div class="col-sm-4"><strong>Created At:</strong></div>
                <div class="col-sm-8">
                    <?php echo date('F d, Y H:i', strtotime($member['created_at'])); ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($currentLoans)): ?>
        <hr class="my-4">
        <h6 class="mb-3">
            <i class="fas fa-book-open me-2"></i>Current Loans
            <span class="badge bg-warning"><?php echo count($currentLoans); ?></span>
        </h6>

        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Book</th>
                        <th>Loan Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($currentLoans as $loan): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($loan['book_title']); ?></strong>
                                <br><small class="text-muted">by <?php echo htmlspecialchars($loan['book_author']); ?></small>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($loan['loan_date'])); ?></td>
                            <td>
                                <?php
                                $dueDate = strtotime($loan['due_date']);
                                $isOverdue = $dueDate < time();
                                $daysOverdue = $isOverdue ? ceil((time() - $dueDate) / (60 * 60 * 24)) : 0;
                                ?>
                                <span class="<?php echo $isOverdue ? 'text-danger fw-bold' : ''; ?>">
                                    <?php echo date('M d, Y', $dueDate); ?>
                                </span>
                                <?php if ($isOverdue): ?>
                                    <br><small class="text-danger">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        <?php echo $daysOverdue; ?> days overdue
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isOverdue): ?>
                                    <span class="badge bg-danger">Overdue</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Borrowed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <hr class="my-4">
        <div class="text-center text-muted">
            <i class="fas fa-book-open fa-2x mb-2"></i>
            <p>No current loans</p>
        </div>
    <?php endif; ?>

    <?php if (!empty($loanHistory)): ?>
        <hr class="my-4">
        <h6 class="mb-3">
            <i class="fas fa-history me-2"></i>Recent Loan History
        </h6>

        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Book</th>
                        <th>Loan Date</th>
                        <th>Due Date</th>
                        <th>Return Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loanHistory as $loan): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($loan['book_title']); ?></strong>
                                <br><small class="text-muted">by <?php echo htmlspecialchars($loan['book_author']); ?></small>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($loan['loan_date'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($loan['due_date'])); ?></td>
                            <td>
                                <?php if ($loan['return_date']): ?>
                                    <?php echo date('M d, Y', strtotime($loan['return_date'])); ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $statusClass = '';
                                $statusText = '';

                                switch ($loan['status']) {
                                    case 'borrowed':
                                        if (strtotime($loan['due_date']) < time()) {
                                            $statusClass = 'bg-danger';
                                            $statusText = 'Overdue';
                                        } else {
                                            $statusClass = 'bg-warning';
                                            $statusText = 'Borrowed';
                                        }
                                        break;
                                    case 'returned':
                                        $statusClass = 'bg-success';
                                        $statusText = 'Returned';
                                        break;
                                    case 'lost':
                                        $statusClass = 'bg-danger';
                                        $statusText = 'Lost';
                                        break;
                                    case 'overdue':
                                        $statusClass = 'bg-danger';
                                        $statusText = 'Overdue';
                                        break;
                                    default:
                                        $statusClass = 'bg-secondary';
                                        $statusText = ucfirst($loan['status']);
                                }
                                ?>
                                <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="text-center mt-3">
            <a href="../loans.php?search=<?php echo urlencode($member['member_code']); ?>" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-history me-2"></i>View Full History
            </a>
        </div>
    <?php else: ?>
        <hr class="my-4">
        <div class="text-center text-muted">
            <i class="fas fa-info-circle me-2"></i>No loan history available for this member.
        </div>
    <?php endif; ?>

    <!-- Statistics Section -->
    <?php
    // Calculate member statistics
    $totalLoans = count($loanHistory) + count($currentLoans);
    $returnedLoans = 0;
    $overdueCount = 0;

    foreach ($loanHistory as $loan) {
        if ($loan['status'] === 'returned') {
            $returnedLoans++;
        }
    }

    foreach ($currentLoans as $loan) {
        if (strtotime($loan['due_date']) < time()) {
            $overdueCount++;
        }
    }
    ?>

    <?php if ($totalLoans > 0): ?>
        <hr class="my-4">
        <h6 class="mb-3">
            <i class="fas fa-chart-bar me-2"></i>Loan Statistics
        </h6>

        <div class="row text-center">
            <div class="col-3">
                <div class="card border-0 bg-light">
                    <div class="card-body py-2">
                        <h6 class="card-title mb-1"><?php echo $totalLoans; ?></h6>
                        <small class="text-muted">Total Loans</small>
                    </div>
                </div>
            </div>
            <div class="col-3">
                <div class="card border-0 bg-light">
                    <div class="card-body py-2">
                        <h6 class="card-title mb-1"><?php echo count($currentLoans); ?></h6>
                        <small class="text-muted">Current</small>
                    </div>
                </div>
            </div>
            <div class="col-3">
                <div class="card border-0 bg-light">
                    <div class="card-body py-2">
                        <h6 class="card-title mb-1"><?php echo $returnedLoans; ?></h6>
                        <small class="text-muted">Returned</small>
                    </div>
                </div>
            </div>
            <div class="col-3">
                <div class="card border-0 bg-light">
                    <div class="card-body py-2">
                        <h6 class="card-title mb-1 <?php echo $overdueCount > 0 ? 'text-danger' : ''; ?>"><?php echo $overdueCount; ?></h6>
                        <small class="text-muted">Overdue</small>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <hr class="my-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <?php if ($member['status'] === 'active' && $memberModel->canBorrow($memberId)): ?>
                <a href="../loans.php?action=add&member_id=<?php echo $member['id']; ?>" class="btn btn-success">
                    <i class="fas fa-plus me-2"></i>New Loan
                </a>
            <?php else: ?>
                <button class="btn btn-secondary" disabled title="<?php echo $member['status'] !== 'active' ? 'Member is not active' : 'Loan limit reached'; ?>">
                    <i class="fas fa-times me-2"></i>
                    <?php echo $member['status'] !== 'active' ? 'Member Not Active' : 'Loan Limit Reached'; ?>
                </button>
            <?php endif; ?>
        </div>

        <div>
            <a href="../members.php?action=edit&id=<?php echo $member['id']; ?>" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-edit me-1"></i>Edit
            </a>

            <?php if ($member['status'] === 'active'): ?>
                <a href="../members.php?action=suspend&id=<?php echo $member['id']; ?>"
                    class="btn btn-outline-warning btn-sm ms-1"
                    onclick="return confirm('Are you sure you want to suspend this member?')"
                    title="Suspend Member">
                    <i class="fas fa-ban me-1"></i>Suspend
                </a>
            <?php elseif ($member['status'] === 'suspended'): ?>
                <a href="../members.php?action=activate&id=<?php echo $member['id']; ?>"
                    class="btn btn-outline-success btn-sm ms-1"
                    onclick="return confirm('Are you sure you want to activate this member?')"
                    title="Activate Member">
                    <i class="fas fa-check me-1"></i>Activate
                </a>
            <?php endif; ?>

            <button type="button" class="btn btn-outline-danger btn-sm ms-1"
                onclick="deleteMemberFromModal(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['full_name']); ?>')"
                title="Delete Member">
                <i class="fas fa-trash me-1"></i>Delete
            </button>
        </div>
    </div>

    <script>
        function deleteMemberFromModal(id, name) {
            if (confirm(`Are you sure you want to delete the member "${name}"?\n\nThis action cannot be undone and will remove all related data.`)) {
                window.parent.location.href = `../members.php?action=delete&id=${id}`;
            }
        }

        // Add tooltips for disabled buttons
        document.addEventListener('DOMContentLoaded', function() {
            // Check if Bootstrap is available
            if (typeof bootstrap !== 'undefined') {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
                tooltipTriggerList.map(function(tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }
        });
    </script>

<?php

} catch (Exception $e) {
    echo '<div class="alert alert-danger">';
    echo '<i class="fas fa-exclamation-circle me-2"></i>';
    echo 'Error loading member details: ' . htmlspecialchars($e->getMessage());
    echo '</div>';
}
?>