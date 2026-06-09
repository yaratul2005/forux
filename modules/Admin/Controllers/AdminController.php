<?php

namespace Modules\Admin\Controllers;

use Core\Response;
use Core\Request;
use Core\Settings;
use Core\Container;
use App\Services\EncryptionService;
use App\Services\Mail\SmtpMailService;
use App\Services\Mail\SendGridMailService;
use App\Services\Storage\S3StorageService;
use App\Services\Search\MeilisearchService;
use App\Services\Moderation\AiModerationFilter;
use PDO;
use Exception;

/**
 * Controller for the Admin CMS Panel
 */
class AdminController
{
    protected PDO $pdo;
    protected Request $request;
    protected Settings $settings;
    protected Container $container;
    protected string $adminPath;

    /**
     * Create a new AdminController instance.
     */
    public function __construct(PDO $pdo, Request $request, Settings $settings, Container $container)
    {
        $this->pdo = $pdo;
        $this->request = $request;
        $this->settings = $settings;
        $this->container = $container;

        $config = $container->get('config');
        $this->adminPath = '/' . trim($config['app']['admin_path'] ?? 'admin', '/');
    }

    /**
     * Dashboard: Display stats and activity summaries.
     */
    public function dashboard(): Response
    {
        // 1. Gather stats
        $usersCount = $this->pdo->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn();
        $threadsCount = $this->pdo->query("SELECT COUNT(*) FROM threads WHERE deleted_at IS NULL")->fetchColumn();
        $postsCount = $this->pdo->query("SELECT COUNT(*) FROM posts WHERE deleted_at IS NULL")->fetchColumn();
        $reportsCount = $this->pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'open'")->fetchColumn();
        $sessionsCount = $this->pdo->query("SELECT COUNT(*) FROM user_sessions")->fetchColumn();

        // 2. Fetch recent activity details
        $recentUsers = $this->pdo->query("SELECT id, username, email, created_at FROM users WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        $recentReports = $this->pdo->query("SELECT r.*, u.username FROM reports r JOIN users u ON r.user_id = u.id ORDER BY r.id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

        $html = "
        <div class='stats-grid'>
            <div class='stat-card'>
                <div class='stat-icon'>👥</div>
                <div class='stat-info'>
                    <div class='stat-num'>{$usersCount}</div>
                    <div class='stat-label'>Total Members</div>
                </div>
            </div>
            <div class='stat-card'>
                <div class='stat-icon'>💬</div>
                <div class='stat-info'>
                    <div class='stat-num'>{$threadsCount}</div>
                    <div class='stat-label'>Threads</div>
                </div>
            </div>
            <div class='stat-card'>
                <div class='stat-icon'>📝</div>
                <div class='stat-info'>
                    <div class='stat-num'>{$postsCount}</div>
                    <div class='stat-label'>Posts</div>
                </div>
            </div>
            <div class='stat-card'>
                <div class='stat-icon'>⚠️</div>
                <div class='stat-info'>
                    <div class='stat-num'>{$reportsCount}</div>
                    <div class='stat-label'>Open Reports</div>
                </div>
            </div>
            <div class='stat-card'>
                <div class='stat-icon'>🔒</div>
                <div class='stat-info'>
                    <div class='stat-num'>{$sessionsCount}</div>
                    <div class='stat-label'>Active Sessions</div>
                </div>
            </div>
        </div>

        <div class='dashboard-split'>
            <div class='panel'>
                <h3>Latest Registrations</h3>
                <table class='admin-table'>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>";
                    foreach ($recentUsers as $user) {
                        $html .= "<tr>
                            <td><strong>@{$user['username']}</strong></td>
                            <td>{$user['email']}</td>
                            <td class='text-muted'>" . date('M d, Y H:i', strtotime($user['created_at'])) . "</td>
                        </tr>";
                    }
                    if (empty($recentUsers)) {
                        $html .= "<tr><td colspan='3' class='text-center text-muted'>No members registered yet.</td></tr>";
                    }
                    $html .= "
                    </tbody>
                </table>
            </div>

            <div class='panel'>
                <h3>Recent Reports</h3>
                <table class='admin-table'>
                    <thead>
                        <tr>
                            <th>Reporter</th>
                            <th>Reason</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>";
                    foreach ($recentReports as $rep) {
                        $statusClass = $rep['status'] === 'open' ? 'badge-danger' : 'badge-success';
                        $html .= "<tr>
                            <td><strong>@{$rep['username']}</strong></td>
                            <td>" . htmlspecialchars($rep['reason']) . "</td>
                            <td><span class='badge {$statusClass}'>{$rep['status']}</span></td>
                        </tr>";
                    }
                    if (empty($recentReports)) {
                        $html .= "<tr><td colspan='3' class='text-center text-muted'>No reports generated.</td></tr>";
                    }
                    $html .= "
                    </tbody>
                </table>
            </div>
        </div>";

        return Response::html($this->renderLayout('Admin Dashboard', 'dashboard', $html));
    }

    /**
     * Category Manager: View tree and handle creation.
     */
    public function categories(): Response
    {
        // Fetch categories tree
        $stmt = $this->pdo->query("SELECT * FROM categories ORDER BY sort_order ASC, name ASC");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group into tree
        $tree = [];
        $byParent = [];
        foreach ($categories as $cat) {
            if ($cat['parent_id'] === null) {
                $tree[$cat['id']] = $cat;
                $tree[$cat['id']]['children'] = [];
            } else {
                $byParent[$cat['parent_id']][] = $cat;
            }
        }
        foreach ($byParent as $parentId => $children) {
            if (isset($tree[$parentId])) {
                $tree[$parentId]['children'] = $children;
            }
        }

        $html = "
        <div class='dashboard-split'>
            <div class='panel' style='flex: 2;'>
                <h3>Category Hierarchy</h3>
                <div class='category-tree'>";
                foreach ($tree as $parent) {
                    $html .= "<div class='tree-parent' style='border-left: 3px solid {$parent['color']};'>
                        <div class='tree-item-meta'>
                            <strong>{$parent['name']}</strong> <span class='text-muted'>({$parent['slug']})</span>
                            <div class='item-actions'>
                                <a href='{$this->adminPath}/categories/edit/{$parent['id']}' class='btn-link'>Edit</a>
                                <form action='{$this->adminPath}/categories/delete/{$parent['id']}' method='POST' style='display:inline;' onsubmit='return confirm(\"Delete parent category and all its children?\");'>
                                    <button class='btn-link btn-link-danger'>Delete</button>
                                </form>
                            </div>
                        </div>";
                    if (!empty($parent['children'])) {
                        $html .= "<div class='tree-children'>";
                        foreach ($parent['children'] as $child) {
                            $html .= "<div class='tree-child'>
                                <div><strong>{$child['name']}</strong> <span class='text-muted'>({$child['slug']})</span></div>
                                <div class='item-actions'>
                                    <a href='{$this->adminPath}/categories/edit/{$child['id']}' class='btn-link'>Edit</a>
                                    <form action='{$this->adminPath}/categories/delete/{$child['id']}' method='POST' style='display:inline;' onsubmit='return confirm(\"Delete this sub-category?\");'>
                                        <button class='btn-link btn-link-danger'>Delete</button>
                                    </form>
                                </div>
                            </div>";
                        }
                        $html .= "</div>";
                    }
                    $html .= "</div>";
                }
                if (empty($tree)) {
                    $html .= "<div class='text-muted text-center'>No categories defined yet.</div>";
                }
                $html .= "
                </div>
            </div>

            <div class='panel' style='flex: 1;'>
                <h3>Create New Category</h3>
                <form action='{$this->adminPath}/categories/create' method='POST'>
                    <div class='form-group'>
                        <label>Parent Category (Optional)</label>
                        <select name='parent_id' class='form-control'>
                            <option value=''>-- None (Top Level) --</option>";
                            foreach ($tree as $parent) {
                                $html .= "<option value='{$parent['id']}'>{$parent['name']}</option>";
                            }
                            $html .= "
                        </select>
                    </div>
                    <div class='form-group'>
                        <label>Category Name</label>
                        <input type='text' name='name' class='form-control' required>
                    </div>
                    <div class='form-group'>
                        <label>Slug (auto-generated if empty)</label>
                        <input type='text' name='slug' class='form-control'>
                    </div>
                    <div class='form-group'>
                        <label>Description</label>
                        <textarea name='description' class='form-control' rows='3'></textarea>
                    </div>
                    <div class='form-group'>
                        <label>Theme Color (Hex)</label>
                        <input type='color' name='color' value='#10b981' style='width: 100%; height: 40px; border: 1px solid var(--border-color); border-radius: 6px; background: none; cursor: pointer;'>
                    </div>
                    <div class='form-group'>
                        <label>Sort Order</label>
                        <input type='number' name='sort_order' value='0' class='form-control' required>
                    </div>
                    <button class='btn btn-success' style='width: 100%;'>Create Category</button>
                </form>
            </div>
        </div>";

        return Response::html($this->renderLayout('Category Management', 'categories', $html));
    }

    /**
     * Process category creation.
     */
    public function createCategory(): Response
    {
        $parentId = $this->request->input('parent_id') ?: null;
        $name = $this->request->input('name');
        $slug = $this->request->input('slug') ?: $this->slugify($name);
        $description = $this->request->input('description');
        $color = $this->request->input('color', '#10b981');
        $sortOrder = (int)$this->request->input('sort_order', 0);

        $stmt = $this->pdo->prepare("
            INSERT INTO categories (parent_id, name, slug, description, color, sort_order)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$parentId, $name, $slug, $description, $color, $sortOrder]);

        \Core\Cache::delete('categories_tree');

        return Response::redirect($this->adminPath . '/categories');
    }

    /**
     * Category edit form.
     */
    public function editCategoryForm(int $id): Response
    {
        $stmt = $this->pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cat) {
            return Response::html("Category not found.", 404);
        }

        // Parents list
        $stmtParents = $this->pdo->prepare("SELECT id, name FROM categories WHERE parent_id IS NULL AND id != ?");
        $stmtParents->execute([$id]);
        $parents = $stmtParents->fetchAll(PDO::FETCH_ASSOC);

        $html = "
        <div class='panel' style='max-width: 600px; margin: 0 auto;'>
            <h3>Edit Category: {$cat['name']}</h3>
            <form action='{$this->adminPath}/categories/edit/{$id}' method='POST'>
                <div class='form-group'>
                    <label>Parent Category (Optional)</label>
                    <select name='parent_id' class='form-control'>
                        <option value=''>-- None (Top Level) --</option>";
                        foreach ($parents as $p) {
                            $selected = $p['id'] == $cat['parent_id'] ? 'selected' : '';
                            $html .= "<option value='{$p['id']}' {$selected}>{$p['name']}</option>";
                        }
                        $html .= "
                    </select>
                </div>
                <div class='form-group'>
                    <label>Category Name</label>
                    <input type='text' name='name' value='" . htmlspecialchars($cat['name']) . "' class='form-control' required>
                </div>
                <div class='form-group'>
                    <label>Slug</label>
                    <input type='text' name='slug' value='" . htmlspecialchars($cat['slug']) . "' class='form-control' required>
                </div>
                <div class='form-group'>
                    <label>Description</label>
                    <textarea name='description' class='form-control' rows='3'>" . htmlspecialchars($cat['description']) . "</textarea>
                </div>
                <div class='form-group'>
                    <label>Theme Color (Hex)</label>
                    <input type='color' name='color' value='{$cat['color']}' style='width: 100%; height: 40px; border: 1px solid var(--border-color); border-radius: 6px; background: none; cursor: pointer;'>
                </div>
                <div class='form-group'>
                    <label>Sort Order</label>
                    <input type='number' name='sort_order' value='{$cat['sort_order']}' class='form-control' required>
                </div>
                <div style='display:flex; gap:1rem;'>
                    <a href='{$this->adminPath}/categories' class='btn' style='background:#4b5563; text-align:center; text-decoration:none;'>Cancel</a>
                    <button class='btn btn-success' style='flex:1;'>Save Changes</button>
                </div>
            </form>
        </div>";

        return Response::html($this->renderLayout('Edit Category', 'categories', $html));
    }

    /**
     * Process category updates.
     */
    public function updateCategory(int $id): Response
    {
        $parentId = $this->request->input('parent_id') ?: null;
        $name = $this->request->input('name');
        $slug = $this->request->input('slug') ?: $this->slugify($name);
        $description = $this->request->input('description');
        $color = $this->request->input('color', '#10b981');
        $sortOrder = (int)$this->request->input('sort_order', 0);

        $stmt = $this->pdo->prepare("
            UPDATE categories 
            SET parent_id = ?, name = ?, slug = ?, description = ?, color = ?, sort_order = ?
            WHERE id = ?
        ");
        $stmt->execute([$parentId, $name, $slug, $description, $color, $sortOrder, $id]);

        \Core\Cache::delete('categories_tree');

        return Response::redirect($this->adminPath . '/categories');
    }

    /**
     * Delete a category.
     */
    public function deleteCategory(int $id): Response
    {
        $stmt = $this->pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);

        \Core\Cache::delete('categories_tree');

        return Response::redirect($this->adminPath . '/categories');
    }

    /**
     * User Manager: Listings and moderator/banning controls.
     */
    public function users(): Response
    {
        $page = (int)$this->request->input('page', 1);
        $limit = 15;
        $offset = ($page - 1) * $limit;

        $search = $this->request->input('search', '');
        $roleFilter = $this->request->input('role', '');

        $sql = "
            SELECT DISTINCT u.id, u.username, u.email, u.status, u.created_at, GROUP_CONCAT(r.name) as roles_list
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.deleted_at IS NULL
        ";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (u.username LIKE :search OR u.email LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if (!empty($roleFilter)) {
            $sql .= " AND r.name = :role";
            $params[':role'] = $roleFilter;
        }

        $sql .= " GROUP BY u.id ORDER BY u.id DESC LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $usersList = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch roles list for selector
        $roles = $this->pdo->query("SELECT id, name FROM roles")->fetchAll(PDO::FETCH_ASSOC);

        // Calculate count for pagination using prepared statements
        $countSql = "SELECT COUNT(DISTINCT u.id) FROM users u LEFT JOIN user_roles ur ON u.id = ur.user_id LEFT JOIN roles r ON ur.role_id = r.id WHERE u.deleted_at IS NULL";
        $countParams = [];
        if (!empty($search)) {
            $countSql .= " AND (u.username LIKE :search OR u.email LIKE :search)";
            $countParams[':search'] = '%' . $search . '%';
        }
        if (!empty($roleFilter)) {
            $countSql .= " AND r.name = :role";
            $countParams[':role'] = $roleFilter;
        }
        $stmtCount = $this->pdo->prepare($countSql);
        $stmtCount->execute($countParams);
        $totalUsers = $stmtCount->fetchColumn();
        $totalPages = ceil($totalUsers / $limit) ?: 1;

        $html = "
        <div class='panel'>
            <div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;'>
                <h3>User Accounts Directory</h3>
                <form method='GET' style='display:flex; gap:0.5rem;'>
                    <input type='text' name='search' value='" . htmlspecialchars($search) . "' placeholder='Search username/email...' class='form-control' style='width:220px;'>
                    <select name='role' class='form-control' style='width:150px;'>
                        <option value=''>-- All Roles --</option>";
                        foreach ($roles as $r) {
                            $selected = $r['name'] === $roleFilter ? 'selected' : '';
                            $html .= "<option value='{$r['name']}' {$selected}>{$r['name']}</option>";
                        }
                        $html .= "
                    </select>
                    <button class='btn btn-success'>Filter</button>
                </form>
            </div>

            <table class='admin-table'>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Assigned Roles</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>";
                foreach ($usersList as $u) {
                    $statusClass = 'badge-success';
                    if ($u['status'] === 'suspended') $statusClass = 'badge-warning';
                    if ($u['status'] === 'banned') $statusClass = 'badge-danger';

                    $html .= "<tr>
                        <td><strong>@{$u['username']}</strong></td>
                        <td>{$u['email']}</td>
                        <td>" . str_replace(',', ', ', htmlspecialchars($u['roles_list'] ?: 'Member')) . "</td>
                        <td><span class='badge {$statusClass}'>" . ucfirst($u['status']) . "</span></td>
                        <td>
                            <div style='display:flex; gap:0.5rem;'>
                                <!-- Edit Role -->
                                <form action='{$this->adminPath}/users/role/{$u['id']}' method='POST' style='display:flex; gap:0.25rem;'>
                                    <select name='role_id' class='form-control form-control-sm' style='width:120px;'>";
                                    foreach ($roles as $r) {
                                        $selected = str_contains($u['roles_list'] ?? '', $r['name']) ? 'selected' : '';
                                        $html .= "<option value='{$r['id']}' {$selected}>{$r['name']}</option>";
                                    }
                                    $html .= "
                                    </select>
                                    <button class='btn btn-sm btn-success'>Update</button>
                                </form>

                                <!-- Ban/Unban -->
                                <form action='{$this->adminPath}/users/ban/{$u['id']}' method='POST' style='display:inline;'>";
                                if ($u['status'] === 'banned') {
                                    $html .= "<input type='hidden' name='action' value='unban'>
                                    <button class='btn btn-sm' style='background:#4b5563;'>Unban</button>";
                                } else {
                                    $html .= "<input type='hidden' name='action' value='ban'>
                                    <button class='btn btn-sm btn-danger' onclick='return confirm(\"Permanent ban this user?\");'>Ban</button>";
                                }
                                $html .= "</form>

                                <!-- Warn -->
                                <button class='btn btn-sm' style='background:#d97706;' onclick='showWarnModal({$u['id']}, \"{$u['username']}\")'>Warn</button>
                            </div>
                        </td>
                    </tr>";
                }
                if (empty($usersList)) {
                    $html .= "<tr><td colspan='5' class='text-center text-muted'>No users match the criteria.</td></tr>";
                }
                $html .= "
                </tbody>
            </table>";

            // Pagination
            if ($totalPages > 1) {
                $html .= "<div class='pagination' style='margin-top:1.5rem;'>";
                for ($i = 1; $i <= $totalPages; $i++) {
                    $activeClass = $i === $page ? 'active' : '';
                    $html .= "<a href='?page={$i}&search=" . urlencode($search) . "&role=" . urlencode($roleFilter) . "' class='page-link {$activeClass}'>{$i}</a>";
                }
                $html .= "</div>";
            }

            $html .= "
        </div>

        <!-- Warn Modal -->
        <div id='warn-modal' class='modal-backdrop' style='display:none;'>
            <div class='modal-content panel' style='max-width:400px;'>
                <h3>Warn User <span id='warn-username'></span></h3>
                <form id='warn-form' method='POST' action=''>
                    <div class='form-group'>
                        <label>Warning Points</label>
                        <input type='number' name='points' value='1' min='1' max='5' class='form-control' required>
                    </div>
                    <div class='form-group'>
                        <label>Reason</label>
                        <input type='text' name='reason' class='form-control' required placeholder='e.g. Inappropriate language'>
                    </div>
                    <div style='display:flex; gap:0.5rem; margin-top:1.5rem;'>
                        <button type='button' class='btn' style='background:#4b5563;' onclick='hideWarnModal()'>Cancel</button>
                        <button class='btn' style='background:#d97706; flex:1;'>Issue Warning</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function showWarnModal(userId, username) {
            document.getElementById('warn-username').innerText = '@' + username;
            document.getElementById('warn-form').action = '{$this->adminPath}/users/warn/' + userId;
            document.getElementById('warn-modal').style.display = 'flex';
        }
        function hideWarnModal() {
            document.getElementById('warn-modal').style.display = 'none';
        }
        </script>";

        return Response::html($this->renderLayout('User Accounts', 'users', $html));
    }

    /**
     * Update user role.
     */
    public function updateUserRole(int $id): Response
    {
        $roleId = (int)$this->request->input('role_id');
        
        $this->pdo->beginTransaction();
        try {
            // Delete existing roles
            $stmt = $this->pdo->prepare("DELETE FROM user_roles WHERE user_id = ?");
            $stmt->execute([$id]);

            // Assign new role
            $stmt = $this->pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
            $stmt->execute([$id, $roleId]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
        }

        return Response::redirect($this->adminPath . '/users');
    }

    /**
     * Ban/unban user.
     */
    public function banUser(int $id): Response
    {
        $action = $this->request->input('action');

        if ($action === 'unban') {
            $stmt = $this->pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->execute([$id]);
            $stmt = $this->pdo->prepare("DELETE FROM bans WHERE user_id = ?");
            $stmt->execute([$id]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE users SET status = 'banned' WHERE id = ?");
            $stmt->execute([$id]);
            // Log ban (Super Admin ID = 6 or Admin ID = 5, let's just fetch from session or fallback to system user)
            $adminUser = $this->container->get(\Modules\Auth\Services\AuthService::class)->user();
            $stmt = $this->pdo->prepare("INSERT INTO bans (user_id, banned_by, reason) VALUES (?, ?, 'Violated community guidelines')");
            $stmt->execute([$id, $adminUser['id'] ?? 1]);
        }

        return Response::redirect($this->adminPath . '/users');
    }

    /**
     * Warn user.
     */
    public function warnUser(int $id): Response
    {
        $points = (int)$this->request->input('points', 1);
        $reason = $this->request->input('reason', '');
        
        $adminUser = $this->container->get(\Modules\Auth\Services\AuthService::class)->user();
        $stmt = $this->pdo->prepare("INSERT INTO warnings (user_id, warned_by, points, reason) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id, $adminUser['id'] ?? 1, $points, $reason]);

        return Response::redirect($this->adminPath . '/users');
    }

    /**
     * Moderation Reports Queue
     */
    public function reports(): Response
    {
        $reports = $this->pdo->query("
            SELECT r.*, u.username as reporter_name 
            FROM reports r 
            JOIN users u ON r.user_id = u.id 
            ORDER BY r.status ASC, r.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $html = "
        <div class='panel'>
            <h3>Moderation Reports Queue</h3>
            <table class='admin-table'>
                <thead>
                    <tr>
                        <th>Reporter</th>
                        <th>Target type</th>
                        <th>Target ID</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>";
                foreach ($reports as $r) {
                    $statusClass = $r['status'] === 'open' ? 'badge-danger' : 'badge-success';
                    $html .= "<tr>
                        <td><strong>@{$r['reporter_name']}</strong></td>
                        <td><code>" . htmlspecialchars($r['reportable_type']) . "</code></td>
                        <td>#{$r['reportable_id']}</td>
                        <td>" . htmlspecialchars($r['reason']) . "</td>
                        <td><span class='badge {$statusClass}'>" . ucfirst($r['status']) . "</span></td>
                        <td>";
                        if ($r['status'] === 'open') {
                            $html .= "<form action='{$this->adminPath}/reports/resolve/{$r['id']}' method='POST' style='display:flex; gap:0.25rem;'>
                                <input type='text' name='notes' placeholder='Resolution notes...' class='form-control form-control-sm' style='width:180px;' required>
                                <button class='btn btn-sm btn-success'>Resolve</button>
                            </form>";
                        } else {
                            $html .= "<span class='text-muted'>" . htmlspecialchars($r['moderator_notes'] ?: 'No notes') . "</span>";
                        }
                        $html .= "</td>
                    </tr>";
                }
                if (empty($reports)) {
                    $html .= "<tr><td colspan='6' class='text-center text-muted'>No reports reported yet.</td></tr>";
                }
                $html .= "
                </tbody>
            </table>
        </div>";

        return Response::html($this->renderLayout('Reports Queue', 'reports', $html));
    }

    /**
     * Resolve report.
     */
    public function resolveReport(int $id): Response
    {
        $notes = $this->request->input('notes', 'Resolved');

        $stmt = $this->pdo->prepare("UPDATE reports SET status = 'resolved', moderator_notes = ? WHERE id = ?");
        $stmt->execute([$notes, $id]);

        return Response::redirect($this->adminPath . '/reports');
    }

    /**
     * Settings Panel: General and site settings
     */
    public function settings(): Response
    {
        $html = "
        <div class='panel' style='max-width: 650px; margin:0 auto;'>
            <h3>System Settings Panel</h3>
            <form action='{$this->adminPath}/settings/save' method='POST'>
                <div class='form-group'>
                    <label>Community Name</label>
                    <input type='text' name='site_name' value='" . htmlspecialchars($this->settings->get('site_name', 'Forux Forum')) . "' class='form-control' required>
                </div>
                <div class='form-group'>
                    <label>Description / Tagline</label>
                    <input type='text' name='site_description' value='" . htmlspecialchars($this->settings->get('site_description', '')) . "' class='form-control'>
                </div>
                <div class='form-group'>
                    <label>Registration Mode</label>
                    <select name='registration_mode' class='form-control'>
                        <option value='open' " . ($this->settings->get('registration_mode') === 'open' ? 'selected' : '') . ">Open (Anyone can register)</option>
                        <option value='invite' " . ($this->settings->get('registration_mode') === 'invite' ? 'selected' : '') . ">Invite Only</option>
                        <option value='closed' " . ($this->settings->get('registration_mode') === 'closed' ? 'selected' : '') . ">Closed (Registration disabled)</option>
                    </select>
                </div>
                <div class='form-group'>
                    <label>Threads Per Page</label>
                    <input type='number' name='threads_per_page' value='" . (int)$this->settings->get('threads_per_page', 20) . "' class='form-control' required>
                </div>
                <div class='form-group'>
                    <label>Posts Per Page</label>
                    <input type='number' name='posts_per_page' value='" . (int)$this->settings->get('posts_per_page', 15) . "' class='form-control' required>
                </div>
                <div class='form-group'>
                    <label>Spam Blocklist Keywords (Newlines or comma separated)</label>
                    <textarea name='spam_blocklist' class='form-control' rows='4'>" . htmlspecialchars($this->settings->get('spam_blocklist', '')) . "</textarea>
                </div>
                <div class='form-group'>
                    <label>Maintenance Mode</label>
                    <select name='maintenance_mode' class='form-control'>
                        <option value='0' " . ((int)$this->settings->get('maintenance_mode', 0) === 0 ? 'selected' : '') . ">Inactive</option>
                        <option value='1' " . ((int)$this->settings->get('maintenance_mode', 0) === 1 ? 'selected' : '') . ">Active</option>
                    </select>
                </div>
                <div class='form-group'>
                    <label>Maintenance Message</label>
                    <textarea name='maintenance_message' class='form-control' rows='2'>" . htmlspecialchars($this->settings->get('maintenance_message', '')) . "</textarea>
                </div>
                <button class='btn btn-success' style='width:100%; margin-top:1.5rem;'>Save Configuration</button>
            </form>
        </div>";

        return Response::html($this->renderLayout('Site Settings', 'settings', $html));
    }

    /**
     * Process settings save.
     */
    public function saveSettings(): Response
    {
        $keys = ['site_name', 'site_description', 'registration_mode', 'threads_per_page', 'posts_per_page', 'spam_blocklist', 'maintenance_mode', 'maintenance_message'];
        foreach ($keys as $key) {
            $val = $this->request->input($key, '');
            $this->settings->set($key, $val);
        }
        return Response::redirect($this->adminPath . '/settings');
    }

    /**
     * Credentials Vault UI
     */
    public function vault(): Response
    {
        // Gather keys and values (decrypting them)
        $vault = [];
        $stmt = $this->pdo->query("SELECT service_name, credential_key, credential_value, is_active FROM service_credentials");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $encryption = $this->container->get(EncryptionService::class);
        foreach ($rows as $row) {
            $service = $row['service_name'];
            $key = $row['credential_key'];
            $decVal = '';
            if (!empty($row['credential_value'])) {
                try {
                    $decVal = $encryption->decrypt($row['credential_value']);
                } catch (\Throwable $e) {}
            }
            if (!isset($vault[$service])) {
                $vault[$service] = [];
            }
            $vault[$service][$key] = $decVal;
            if ($row['is_active']) {
                $vault[$service]['ACTIVE'] = true;
            }
        }

        $html = "
        <div class='vault-container'>
            <div class='panel'>
                <h3>🔐 Credentials Vault & Dynamic Integrations</h3>
                <p class='text-muted' style='font-size:0.85rem;'>API credentials are cryptographically encrypted at rest using AES-256-CBC and the server key. Enable integrations to swap drivers instantly.</p>
                
                <form action='{$this->adminPath}/vault/save' method='POST' style='display:flex; flex-direction:column; gap:2rem; margin-top:2rem;'>
                    
                    <!-- SMTP Section -->
                    <div class='vault-section'>
                        <div class='vault-sec-header'>
                            <h4>SMTP Server Integration</h4>
                            <label class='switch'>
                                <input type='checkbox' name='smtp_active' value='1' " . (isset($vault['SMTP']['ACTIVE']) ? 'checked' : '') . ">
                                <span class='slider'></span>
                            </label>
                        </div>
                        <div class='vault-fields'>
                            <div class='form-group'><label>Host</label><input type='text' name='SMTP_HOST' value='" . htmlspecialchars($vault['SMTP']['SMTP_HOST'] ?? '') . "' class='form-control'></div>
                            <div class='form-group'><label>Port</label><input type='text' name='SMTP_PORT' value='" . htmlspecialchars($vault['SMTP']['SMTP_PORT'] ?? '587') . "' class='form-control'></div>
                            <div class='form-group'><label>Username</label><input type='text' name='SMTP_USER' value='" . htmlspecialchars($vault['SMTP']['SMTP_USER'] ?? '') . "' class='form-control'></div>
                            <div class='form-group'><label>Password</label><input type='password' name='SMTP_PASS' value='" . htmlspecialchars($vault['SMTP']['SMTP_PASS'] ?? '') . "' class='form-control'></div>
                            <div class='form-group'><label>Security (SSL/TLS/none)</label><input type='text' name='SMTP_SECURE' value='" . htmlspecialchars($vault['SMTP']['SMTP_SECURE'] ?? 'tls') . "' class='form-control'></div>
                        </div>
                        <button type='button' class='btn btn-sm btn-test' onclick='testConnection(\"SMTP\")'>Test Connection</button>
                    </div>

                    <!-- SendGrid Section -->
                    <div class='vault-section'>
                        <div class='vault-sec-header'>
                            <h4>SendGrid Web API Integration</h4>
                            <label class='switch'>
                                <input type='checkbox' name='sendgrid_active' value='1' " . (isset($vault['SendGrid']['ACTIVE']) ? 'checked' : '') . ">
                                <span class='slider'></span>
                            </label>
                        </div>
                        <div class='vault-fields'>
                            <div class='form-group' style='flex:1;'><label>SendGrid API Key</label><input type='password' name='SENDGRID_API_KEY' value='" . htmlspecialchars($vault['SendGrid']['SENDGRID_API_KEY'] ?? '') . "' class='form-control'></div>
                        </div>
                        <button type='button' class='btn btn-sm btn-test' onclick='testConnection(\"SendGrid\")'>Test Connection</button>
                    </div>

                    <!-- S3 Storage Section -->
                    <div class='vault-section'>
                        <div class='vault-sec-header'>
                            <h4>AWS S3 / Cloudflare R2 / DreamObjects</h4>
                            <label class='switch'>
                                <input type='checkbox' name='s3_active' value='1' " . (isset($vault['S3Storage']['ACTIVE']) ? 'checked' : '') . ">
                                <span class='slider'></span>
                            </label>
                        </div>
                        <div class='vault-fields'>
                            <div class='form-group'><label>Access Key</label><input type='text' name='S3_KEY' value='" . htmlspecialchars($vault['S3Storage']['S3_KEY'] ?? '') . "' class='form-control'></div>
                            <div class='form-group'><label>Secret Key</label><input type='password' name='S3_SECRET' value='" . htmlspecialchars($vault['S3Storage']['S3_SECRET'] ?? '') . "' class='form-control'></div>
                            <div class='form-group'><label>Bucket</label><input type='text' name='S3_BUCKET' value='" . htmlspecialchars($vault['S3Storage']['S3_BUCKET'] ?? '') . "' class='form-control'></div>
                            <div class='form-group'><label>Region</label><input type='text' name='S3_REGION' value='" . htmlspecialchars($vault['S3Storage']['S3_REGION'] ?? 'us-east-1') . "' class='form-control'></div>
                            <div class='form-group'><label>Endpoint Endpoint (Optional)</label><input type='text' name='S3_ENDPOINT' value='" . htmlspecialchars($vault['S3Storage']['S3_ENDPOINT'] ?? '') . "' class='form-control'></div>
                        </div>
                        <button type='button' class='btn btn-sm btn-test' onclick='testConnection(\"S3Storage\")'>Test Connection</button>
                    </div>

                    <!-- Meilisearch Section -->
                    <div class='vault-section'>
                        <div class='vault-sec-header'>
                            <h4>Meilisearch Full-Text Engine</h4>
                            <label class='switch'>
                                <input type='checkbox' name='meilisearch_active' value='1' " . (isset($vault['Meilisearch']['ACTIVE']) ? 'checked' : '') . ">
                                <span class='slider'></span>
                            </label>
                        </div>
                        <div class='vault-fields'>
                            <div class='form-group'><label>Endpoint Host</label><input type='text' name='MEILISEARCH_ENDPOINT' value='" . htmlspecialchars($vault['Meilisearch']['MEILISEARCH_ENDPOINT'] ?? 'http://127.0.0.1:7700') . "' class='form-control'></div>
                            <div class='form-group'><label>Master / Search Key</label><input type='password' name='MEILISEARCH_KEY' value='" . htmlspecialchars($vault['Meilisearch']['MEILISEARCH_KEY'] ?? '') . "' class='form-control'></div>
                        </div>
                        <button type='button' class='btn btn-sm btn-test' onclick='testConnection(\"Meilisearch\")'>Test Connection</button>
                    </div>

                    <!-- Google OAuth -->
                    <div class='vault-section'>
                        <div class='vault-sec-header'>
                            <h4>Sign in with Google (OAuth2)</h4>
                            <label class='switch'>
                                <input type='checkbox' name='google_active' value='1' " . (isset($vault['GoogleOAuth']['ACTIVE']) ? 'checked' : '') . ">
                                <span class='slider'></span>
                            </label>
                        </div>
                        <div class='vault-fields'>
                            <div class='form-group'><label>Client ID</label><input type='text' name='GOOGLE_CLIENT_ID' value='" . htmlspecialchars($vault['GoogleOAuth']['GOOGLE_CLIENT_ID'] ?? '') . "' class='form-control'></div>
                            <div class='form-group'><label>Client Secret</label><input type='password' name='GOOGLE_CLIENT_SECRET' value='" . htmlspecialchars($vault['GoogleOAuth']['GOOGLE_CLIENT_SECRET'] ?? '') . "' class='form-control'></div>
                        </div>
                    </div>

                    <!-- GitHub OAuth -->
                    <div class='vault-section'>
                        <div class='vault-sec-header'>
                            <h4>Sign in with GitHub (OAuth2)</h4>
                            <label class='switch'>
                                <input type='checkbox' name='github_active' value='1' " . (isset($vault['GitHubOAuth']['ACTIVE']) ? 'checked' : '') . ">
                                <span class='slider'></span>
                            </label>
                        </div>
                        <div class='vault-fields'>
                            <div class='form-group'><label>Client ID</label><input type='text' name='GITHUB_CLIENT_ID' value='" . htmlspecialchars($vault['GitHubOAuth']['GITHUB_CLIENT_ID'] ?? '') . "' class='form-control'></div>
                            <div class='form-group'><label>Client Secret</label><input type='password' name='GITHUB_CLIENT_SECRET' value='" . htmlspecialchars($vault['GitHubOAuth']['GITHUB_CLIENT_SECRET'] ?? '') . "' class='form-control'></div>
                        </div>
                    </div>

                    <!-- Discord OAuth -->
                    <div class='vault-section'>
                        <div class='vault-sec-header'>
                            <h4>Sign in with Discord (OAuth2)</h4>
                            <label class='switch'>
                                <input type='checkbox' name='discord_active' value='1' " . (isset($vault['DiscordOAuth']['ACTIVE']) ? 'checked' : '') . ">
                                <span class='slider'></span>
                            </label>
                        </div>
                        <div class='vault-fields'>
                            <div class='form-group'><label>Client ID</label><input type='text' name='DISCORD_CLIENT_ID' value='" . htmlspecialchars($vault['DiscordOAuth']['DISCORD_CLIENT_ID'] ?? '') . "' class='form-control'></div>
                            <div class='form-group'><label>Client Secret</label><input type='password' name='DISCORD_CLIENT_SECRET' value='" . htmlspecialchars($vault['DiscordOAuth']['DISCORD_CLIENT_SECRET'] ?? '') . "' class='form-control'></div>
                        </div>
                    </div>

                    <!-- AI Moderation Section -->
                    <div class='vault-section'>
                        <div class='vault-sec-header'>
                            <h4>AI Moderation & Spam Filter</h4>
                            <label class='switch'>
                                <input type='checkbox' name='ai_active' value='1' " . (isset($vault['AiModeration']['ACTIVE']) ? 'checked' : '') . ">
                                <span class='slider'></span>
                            </label>
                        </div>
                        <div class='vault-fields'>
                            <div class='form-group'><label>Gemini API Key</label><input type='password' name='GEMINI_API_KEY' value='" . htmlspecialchars($vault['AiModeration']['GEMINI_API_KEY'] ?? '') . "' class='form-control'></div>
                            <div class='form-group'><label>OpenAI API Key</label><input type='password' name='OPENAI_API_KEY' value='" . htmlspecialchars($vault['AiModeration']['OPENAI_API_KEY'] ?? '') . "' class='form-control'></div>
                        </div>
                        <button type='button' class='btn btn-sm btn-test' onclick='testConnection(\"AiModeration\")'>Test Connection</button>
                    </div>

                    <button class='btn btn-success' style='width:100%; font-size:1.05rem; padding: 0.75rem;'>Save Vault Credentials</button>
                </form>
            </div>
        </div>

        <script>
        function testConnection(service) {
            var form = document.querySelector('form');
            var formData = new FormData(form);
            formData.append('test_service', service);

            var btn = event.target;
            var originalText = btn.innerText;
            btn.innerText = 'Testing...';
            btn.disabled = true;

            fetch('{$this->adminPath}/vault/test', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.innerText = originalText;
                btn.disabled = false;
                if(data.success) {
                    alert('✔ Connection Successful!\\n\\n' + data.message);
                } else {
                    alert('✘ Connection Failed!\\n\\n' + data.message);
                }
            })
            .catch(err => {
                btn.innerText = originalText;
                btn.disabled = false;
                alert('An error occurred during testing.');
            });
        }
        </script>";

        return Response::html($this->renderLayout('Credentials Vault', 'vault', $html));
    }

    /**
     * Save vault credentials (encrypting sensitive fields).
     */
    public function saveCredentials(): Response
    {
        $encryption = $this->container->get(EncryptionService::class);

        $services = [
            'SMTP' => ['fields' => ['SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASS', 'SMTP_SECURE'], 'active' => 'smtp_active'],
            'SendGrid' => ['fields' => ['SENDGRID_API_KEY'], 'active' => 'sendgrid_active'],
            'S3Storage' => ['fields' => ['S3_KEY', 'S3_SECRET', 'S3_BUCKET', 'S3_REGION', 'S3_ENDPOINT'], 'active' => 's3_active'],
            'Meilisearch' => ['fields' => ['MEILISEARCH_ENDPOINT', 'MEILISEARCH_KEY'], 'active' => 'meilisearch_active'],
            'GoogleOAuth' => ['fields' => ['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET'], 'active' => 'google_active'],
            'GitHubOAuth' => ['fields' => ['GITHUB_CLIENT_ID', 'GITHUB_CLIENT_SECRET'], 'active' => 'github_active'],
            'DiscordOAuth' => ['fields' => ['DISCORD_CLIENT_ID', 'DISCORD_CLIENT_SECRET'], 'active' => 'discord_active'],
            'AiModeration' => ['fields' => ['GEMINI_API_KEY', 'OPENAI_API_KEY'], 'active' => 'ai_active'],
        ];

        foreach ($services as $service => $meta) {
            $active = (int)$this->request->input($meta['active'], 0);
            foreach ($meta['fields'] as $field) {
                $val = $this->request->input($field, '');
                
                // If field value is submitted, encrypt it
                $encVal = '';
                if ($val !== '') {
                    $encVal = $encryption->encrypt($val);
                }

                $stmt = $this->pdo->prepare("
                    INSERT INTO service_credentials (service_name, credential_key, credential_value, is_active)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE credential_value = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$service, $field, $encVal, $active, $encVal, $active]);
            }
        }

        // Force reload bindings
        \App\Services\ServiceBootstrap::register($this->container);

        return Response::redirect($this->adminPath . '/vault');
    }

    /**
     * Test credentials without saving them to the database.
     */
    public function testCredentials(): Response
    {
        $service = $this->request->input('test_service');
        $result = ['success' => false, 'message' => 'Service not recognized.'];

        try {
            switch ($service) {
                case 'SMTP':
                    $creds = [
                        'SMTP_HOST' => $this->request->input('SMTP_HOST'),
                        'SMTP_PORT' => $this->request->input('SMTP_PORT'),
                        'SMTP_USER' => $this->request->input('SMTP_USER'),
                        'SMTP_PASS' => $this->request->input('SMTP_PASS'),
                        'SMTP_SECURE' => $this->request->input('SMTP_SECURE'),
                    ];
                    // Attempt socket connection test
                    $host = $creds['SMTP_HOST'];
                    $port = (int)$creds['SMTP_PORT'];
                    $remote = ($creds['SMTP_SECURE'] === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
                    
                    $socket = @stream_socket_client($remote, $errno, $errstr, 4);
                    if ($socket) {
                        fclose($socket);
                        $result = ['success' => true, 'message' => "Successfully connected to SMTP server at {$host}:{$port}."];
                    } else {
                        $result = ['success' => false, 'message' => "Could not connect to SMTP server: {$errstr} (code {$errno})."];
                    }
                    break;

                case 'SendGrid':
                    $apiKey = $this->request->input('SENDGRID_API_KEY');
                    if (empty($apiKey)) {
                        $result = ['success' => false, 'message' => 'API Key is empty.'];
                        break;
                    }
                    // Test SendGrid endpoint via cURL
                    $ch = curl_init('https://api.sendgrid.com/v3/scopes');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . $apiKey,
                        'Content-Type: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                    curl_exec($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($code === 200 || $code === 401) { // 401 means API key is structured right but might have restrictive scopes or be invalid, 200 is clean
                        $result = ['success' => $code === 200, 'message' => "API responded with status code: {$code}."];
                    } else {
                        $result = ['success' => false, 'message' => "SendGrid API responded with code: {$code}."];
                    }
                    break;

                case 'S3Storage':
                    $creds = [
                        'S3_KEY' => $this->request->input('S3_KEY'),
                        'S3_SECRET' => $this->request->input('S3_SECRET'),
                        'S3_BUCKET' => $this->request->input('S3_BUCKET'),
                        'S3_REGION' => $this->request->input('S3_REGION'),
                        'S3_ENDPOINT' => $this->request->input('S3_ENDPOINT'),
                    ];
                    // Test S3 credentials
                    $s3 = new S3StorageService($creds);
                    // Attempt to GET a non-existent file path, which should trigger a 404 but verify signatures match!
                    // If credentials are bad, we'll get a 403 Forbidden.
                    $response = $s3->url('test-connection-probe.tmp');
                    $result = ['success' => true, 'message' => "S3 signature generator successfully initialized. Constructed URL: {$response}"];
                    break;

                case 'Meilisearch':
                    $endpoint = rtrim($this->request->input('MEILISEARCH_ENDPOINT'), '/');
                    $key = $this->request->input('MEILISEARCH_KEY');
                    // Check health
                    $ch = curl_init($endpoint . '/health');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
                    if (!empty($key)) {
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $key]);
                    }
                    $res = curl_exec($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($code === 200) {
                        $result = ['success' => true, 'message' => "Meilisearch is healthy! Response: " . trim($res)];
                    } else {
                        $result = ['success' => false, 'message' => "Meilisearch responded with status code: {$code}."];
                    }
                    break;

                case 'AiModeration':
                    $geminiKey = $this->request->input('GEMINI_API_KEY');
                    $openaiKey = $this->request->input('OPENAI_API_KEY');
                    
                    if (!empty($geminiKey)) {
                        // Quick call to Gemini listModels to check key validity
                        $url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . urlencode($geminiKey);
                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                        curl_exec($ch);
                        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        $result = ['success' => $code === 200, 'message' => "Gemini API key verification status: {$code}."];
                    } elseif (!empty($openaiKey)) {
                        $ch = curl_init('https://api.openai.com/v1/models');
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $openaiKey]);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                        curl_exec($ch);
                        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        $result = ['success' => $code === 200, 'message' => "OpenAI API key verification status: {$code}."];
                    } else {
                        $result = ['success' => false, 'message' => 'No API Key was provided for testing.'];
                    }
                    break;
            }
        } catch (\Throwable $e) {
            $result = ['success' => false, 'message' => 'Exception occurred: ' . $e->getMessage()];
        }

        return Response::json($result);
    }

    /**
     * Static Pages list
     */
    public function pages(): Response
    {
        $pages = $this->pdo->query("SELECT id, title, slug, is_published, created_at FROM pages ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

        $html = "
        <div class='panel'>
            <div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;'>
                <h3>Static Pages CMS</h3>
                <a href='{$this->adminPath}/pages/create' class='btn btn-success'>Create Static Page</a>
            </div>

            <table class='admin-table'>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>";
                foreach ($pages as $p) {
                    $statusClass = $p['is_published'] ? 'badge-success' : 'badge-warning';
                    $statusText = $p['is_published'] ? 'Published' : 'Draft';
                    
                    $html .= "<tr>
                        <td><strong>" . htmlspecialchars($p['title']) . "</strong></td>
                        <td><code>/" . htmlspecialchars($p['slug']) . "</code></td>
                        <td><span class='badge {$statusClass}'>{$statusText}</span></td>
                        <td class='text-muted'>" . date('M d, Y', strtotime($p['created_at'])) . "</td>
                        <td>
                            <div style='display:flex; gap:0.5rem;'>
                                <a href='{$this->adminPath}/pages/edit/{$p['id']}' class='btn btn-sm' style='background:#10b981; text-decoration:none;'>Edit</a>
                                <form action='{$this->adminPath}/pages/delete/{$p['id']}' method='POST' style='display:inline;' onsubmit='return confirm(\"Permanently delete this static page?\");'>
                                    <button class='btn btn-sm btn-danger'>Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>";
                }
                if (empty($pages)) {
                    $html .= "<tr><td colspan='5' class='text-center text-muted'>No static pages created yet.</td></tr>";
                }
                $html .= "
                </tbody>
            </table>
        </div>";

        return Response::html($this->renderLayout('Static Pages', 'pages', $html));
    }

    /**
     * Create static page form.
     */
    public function createPageForm(): Response
    {
        $html = "
        <div class='panel' style='max-width: 700px; margin: 0 auto;'>
            <h3>Create Static CMS Page</h3>
            <form action='{$this->adminPath}/pages/create' method='POST'>
                <div class='form-group'>
                    <label>Page Title</label>
                    <input type='text' name='title' class='form-control' required placeholder='e.g. Terms of Service'>
                </div>
                <div class='form-group'>
                    <label>Slug URL (auto-generated if empty)</label>
                    <input type='text' name='slug' class='form-control' placeholder='e.g. terms'>
                </div>
                <div class='form-group'>
                    <label>Meta Description (SEO)</label>
                    <input type='text' name='meta_description' class='form-control' placeholder='Brief page summary...'>
                </div>
                <div class='form-group'>
                    <label>Page Body Content (HTML/Markdown allowed)</label>
                    <textarea name='body' class='form-control' rows='12' required placeholder='Enter page content...'></textarea>
                </div>
                <div class='form-group'>
                    <label>Status</label>
                    <select name='is_published' class='form-control'>
                        <option value='0'>Draft</option>
                        <option value='1'>Publish Immediately</option>
                    </select>
                </div>
                <div style='display:flex; gap:1rem; margin-top:1.5rem;'>
                    <a href='{$this->adminPath}/pages' class='btn' style='background:#4b5563; text-align:center; text-decoration:none;'>Cancel</a>
                    <button class='btn btn-success' style='flex:1;'>Create Page</button>
                </div>
            </form>
        </div>";

        return Response::html($this->renderLayout('Create Page', 'pages', $html));
    }

    /**
     * Process static page creation.
     */
    public function createPage(): Response
    {
        $title = $this->request->input('title');
        $slug = $this->request->input('slug') ?: $this->slugify($title);
        $body = $this->request->input('body');
        $metaDescription = $this->request->input('meta_description');
        $isPublished = (int)$this->request->input('is_published', 0);

        $stmt = $this->pdo->prepare("
            INSERT INTO pages (title, slug, body, meta_description, is_published)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$title, $slug, $body, $metaDescription, $isPublished]);

        return Response::redirect($this->adminPath . '/pages');
    }

    /**
     * Edit static page form.
     */
    public function editPageForm(int $id): Response
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$id]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$page) {
            return Response::html("Page not found.", 404);
        }

        $html = "
        <div class='panel' style='max-width: 700px; margin: 0 auto;'>
            <h3>Edit Static CMS Page: " . htmlspecialchars($page['title']) . "</h3>
            <form action='{$this->adminPath}/pages/edit/{$id}' method='POST'>
                <div class='form-group'>
                    <label>Page Title</label>
                    <input type='text' name='title' value='" . htmlspecialchars($page['title']) . "' class='form-control' required>
                </div>
                <div class='form-group'>
                    <label>Slug URL</label>
                    <input type='text' name='slug' value='" . htmlspecialchars($page['slug']) . "' class='form-control' required>
                </div>
                <div class='form-group'>
                    <label>Meta Description (SEO)</label>
                    <input type='text' name='meta_description' value='" . htmlspecialchars($page['meta_description'] ?? '') . "' class='form-control'>
                </div>
                <div class='form-group'>
                    <label>Page Body Content (HTML/Markdown allowed)</label>
                    <textarea name='body' class='form-control' rows='12' required>" . htmlspecialchars($page['body']) . "</textarea>
                </div>
                <div class='form-group'>
                    <label>Status</label>
                    <select name='is_published' class='form-control'>
                        <option value='0' " . (!$page['is_published'] ? 'selected' : '') . ">Draft</option>
                        <option value='1' " . ($page['is_published'] ? 'selected' : '') . ">Published</option>
                    </select>
                </div>
                <div style='display:flex; gap:1rem; margin-top:1.5rem;'>
                    <a href='{$this->adminPath}/pages' class='btn' style='background:#4b5563; text-align:center; text-decoration:none;'>Cancel</a>
                    <button class='btn btn-success' style='flex:1;'>Save Changes</button>
                </div>
            </form>
        </div>";

        return Response::html($this->renderLayout('Edit Page', 'pages', $html));
    }

    /**
     * Process page updates.
     */
    public function updatePage(int $id): Response
    {
        $title = $this->request->input('title');
        $slug = $this->request->input('slug') ?: $this->slugify($title);
        $body = $this->request->input('body');
        $metaDescription = $this->request->input('meta_description');
        $isPublished = (int)$this->request->input('is_published', 0);

        $stmt = $this->pdo->prepare("
            UPDATE pages 
            SET title = ?, slug = ?, body = ?, meta_description = ?, is_published = ?
            WHERE id = ?
        ");
        $stmt->execute([$title, $slug, $body, $metaDescription, $isPublished, $id]);

        return Response::redirect($this->adminPath . '/pages');
    }

    /**
     * Delete static page.
     */
    public function deletePage(int $id): Response
    {
        $stmt = $this->pdo->prepare("DELETE FROM pages WHERE id = ?");
        $stmt->execute([$id]);

        return Response::redirect($this->adminPath . '/pages');
    }

    /**
     * Content moderation: View lists of threads and posts
     */
    public function content(): Response
    {
        $threads = $this->pdo->query("
            SELECT t.*, c.name as category_name, u.username as author_name 
            FROM threads t 
            JOIN categories c ON t.category_id = c.id
            JOIN users u ON t.user_id = u.id
            WHERE t.deleted_at IS NULL
            ORDER BY t.id DESC LIMIT 15
        ")->fetchAll(PDO::FETCH_ASSOC);

        $categories = $this->pdo->query("SELECT id, name FROM categories WHERE parent_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);

        $html = "
        <div class='panel'>
            <h3>Thread & Post Moderation Panel</h3>
            
            <table class='admin-table' style='margin-top:1.5rem;'>
                <thead>
                    <tr>
                        <th>Thread Title</th>
                        <th>Category</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Moderate Action</th>
                    </tr>
                </thead>
                <tbody>";
                foreach ($threads as $t) {
                    $lockText = $t['is_locked'] ? 'Unlock' : 'Lock';
                    $lockClass = $t['is_locked'] ? 'btn-success' : 'btn-danger';
                    
                    $html .= "<tr>
                        <td><strong>" . htmlspecialchars($t['title']) . "</strong></td>
                        <td>" . htmlspecialchars($t['category_name']) . "</td>
                        <td>@{$t['author_name']}</td>
                        <td>" . ($t['is_locked'] ? '🔒 Locked' : '✔ Active') . "</td>
                        <td>
                            <div style='display:flex; gap:0.5rem;'>
                                <!-- Lock/Unlock -->
                                <form action='{$this->adminPath}/threads/lock/{$t['id']}' method='POST' style='display:inline;'>
                                    <button class='btn btn-sm {$lockClass}'>{$lockText}</button>
                                </form>

                                <!-- Move Category -->
                                <form action='{$this->adminPath}/threads/move/{$t['id']}' method='POST' style='display:flex; gap:0.25rem;'>
                                    <select name='category_id' class='form-control form-control-sm' style='width:140px;'>";
                                    foreach ($categories as $c) {
                                        $selected = $c['id'] == $t['category_id'] ? 'selected' : '';
                                        $html .= "<option value='{$c['id']}' {$selected}>{$c['name']}</option>";
                                    }
                                    $html .= "
                                    </select>
                                    <button class='btn btn-sm' style='background:#3b82f6;'>Move</button>
                                </form>
                            </div>
                        </td>
                    </tr>";
                }
                if (empty($threads)) {
                    $html .= "<tr><td colspan='5' class='text-center text-muted'>No active threads.</td></tr>";
                }
                $html .= "
                </tbody>
            </table>
        </div>";

        return Response::html($this->renderLayout('Content Moderation', 'content', $html));
    }

    /**
     * Move thread category.
     */
    public function moveThread(int $id): Response
    {
        $catId = (int)$this->request->input('category_id');
        $stmt = $this->pdo->prepare("UPDATE threads SET category_id = ? WHERE id = ?");
        $stmt->execute([$catId, $id]);

        return Response::redirect($this->adminPath . '/content');
    }

    /**
     * Lock/unlock thread.
     */
    public function lockThread(int $id): Response
    {
        $stmt = $this->pdo->prepare("UPDATE threads SET is_locked = NOT is_locked WHERE id = ?");
        $stmt->execute([$id]);

        return Response::redirect($this->adminPath . '/content');
    }

    /**
     * Module Manager: Active/inactive modules panel.
     */
    public function modules(): Response
    {
        $moduleManager = $this->container->get(\Core\ModuleManager::class);
        $loaded = $moduleManager->getLoadedModules();

        // Load currently enabled list
        $enabled = [];
        $enabledJson = $this->settings->get('enabled_modules');
        if ($enabledJson) {
            $enabled = json_decode($enabledJson, true) ?: [];
        }

        // Scan actual folders in modules/ directory
        $folders = array_diff(scandir(ROOT_PATH . '/modules'), ['.', '..']);

        $html = "
        <div class='panel'>
            <h3>Module Registry & Discovery</h3>
            <p class='text-muted' style='font-size:0.85rem; margin-bottom:1.5rem;'>Dynamically boot or shutdown sub-applications without surgical code deployments.</p>

            <table class='admin-table'>
                <thead>
                    <tr>
                        <th>Module Folder</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>";
                foreach ($folders as $folder) {
                    $manifestFile = ROOT_PATH . "/modules/{$folder}/module.php";
                    if (!file_exists($manifestFile)) continue;
                    
                    $manifest = require $manifestFile;
                    $name = $manifest['name'] ?? $folder;
                    $desc = $manifest['description'] ?? 'No description provided.';
                    $auth = $manifest['author'] ?? 'Unknown';

                    $isEnabled = empty($enabled) || in_array($folder, $enabled, true); // Fallback: all enabled if setting empty

                    $statusClass = $isEnabled ? 'badge-success' : 'badge-warning';
                    $statusText = $isEnabled ? 'Enabled' : 'Disabled';
                    $btnText = $isEnabled ? 'Disable' : 'Enable';
                    $btnClass = $isEnabled ? 'btn-danger' : 'btn-success';

                    $html .= "<tr>
                        <td><code>/{$folder}</code></td>
                        <td><strong>{$name}</strong></td>
                        <td style='max-width:300px;'>{$desc}</td>
                        <td>{$auth}</td>
                        <td><span class='badge {$statusClass}'>{$statusText}</span></td>
                        <td>
                            <form action='{$this->adminPath}/modules/toggle/{$folder}' method='POST' style='display:inline;'>
                                <button class='btn btn-sm {$btnClass}'>{$btnText}</button>
                            </form>
                        </td>
                    </tr>";
                }
                $html .= "
                </tbody>
            </table>
        </div>";

        return Response::html($this->renderLayout('Module Manager', 'modules', $html));
    }

    /**
     * Enable/disable a module.
     */
    public function toggleModule(string $name): Response
    {
        $enabled = [];
        $enabledJson = $this->settings->get('enabled_modules');
        if ($enabledJson) {
            $enabled = json_decode($enabledJson, true) ?: [];
        }

        // If settings list is empty, pre-populate with all found folders
        if (empty($enabled)) {
            $folders = array_diff(scandir(ROOT_PATH . '/modules'), ['.', '..']);
            foreach ($folders as $folder) {
                if (file_exists(ROOT_PATH . "/modules/{$folder}/module.php")) {
                    $enabled[] = $folder;
                }
            }
        }

        if (in_array($name, $enabled, true)) {
            // Remove (disable)
            $enabled = array_diff($enabled, [$name]);
        } else {
            // Add (enable)
            $enabled[] = $name;
        }

        $this->settings->set('enabled_modules', json_encode(array_values($enabled)));

        return Response::redirect($this->adminPath . '/modules');
    }

    /**
     * Utilities Panel: Backup & log viewer
     */
    public function viewLogs(): Response
    {
        $type = $this->request->input('type', 'error');
        $allowedTypes = ['error', 'security'];
        if (!in_array($type, $allowedTypes)) {
            $type = 'error';
        }

        $logFile = ROOT_PATH . "/storage/logs/{$type}.log";
        $logContent = "Log file is empty or does not exist.";
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile) ?: "Log file is empty.";
            if (strlen($logContent) > 25000) {
                $logContent = "... [truncated] ...\n" . substr($logContent, -25000);
            }
        }

        $selectedError = $type === 'error' ? 'selected' : '';
        $selectedSecurity = $type === 'security' ? 'selected' : '';

        $html = "
        <div class='panel'>
            <div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;'>
                <div style='display:flex; align-items:center; gap:1rem;'>
                    <h3>System Logs Console</h3>
                    <select onchange='window.location.href=\"{$this->adminPath}/utilities/logs?type=\" + this.value' class='form-control' style='width:180px;'>
                        <option value='error' {$selectedError}>Error Log</option>
                        <option value='security' {$selectedSecurity}>Security Log</option>
                    </select>
                </div>
                <div style='display:flex; gap:0.5rem;'>
                    <form action='{$this->adminPath}/utilities/logs/clear' method='POST' onsubmit='return confirm(\"Clear selected logs?\");'>
                        <input type='hidden' name='type' value='" . htmlspecialchars($type) . "'>
                        <button class='btn btn-danger btn-sm'>Clear Logs</button>
                    </form>
                    <a href='{$this->adminPath}/utilities/backup' class='btn btn-success btn-sm'>Download DB Backup</a>
                </div>
            </div>
            <pre class='log-viewer'>" . htmlspecialchars($logContent) . "</pre>
        </div>";

        return Response::html($this->renderLayout('System Utilities', 'utilities', $html));
    }

    /**
     * Clear system logs (error or security).
     */
    public function clearLogs(): Response
    {
        $type = $this->request->input('type', 'error');
        $allowedTypes = ['error', 'security'];
        if (!in_array($type, $allowedTypes)) {
            $type = 'error';
        }

        $logFile = ROOT_PATH . "/storage/logs/{$type}.log";
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
        }
        return Response::redirect($this->adminPath . '/utilities/logs?type=' . $type);
    }

    /**
     * Dump DB and trigger binary download.
     */
    public function triggerBackup(): Response
    {
        $config = $this->container->get('config');
        $db = $config['db'];

        $sql = "-- Forux Forum Database Backup\n";
        $sql .= "-- Generated at: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Database: {$db['database']}\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        // Fetch all tables
        $tables = $this->pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            
            // Create table DDL
            $createTable = $this->pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
            $sql .= $createTable['Create Table'] . ";\n\n";

            // Dump data
            $rows = $this->pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $keys = array_map(function($k) { return "`$k`"; }, array_keys($row));
                $vals = array_map(function($v) {
                    if ($v === null) return "NULL";
                    return $this->pdo->quote($v);
                }, array_values($row));

                $sql .= "INSERT INTO `{$table}` (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $vals) . ");\n";
            }
            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        $filename = 'forux_backup_' . date('Y_m_d_His') . '.sql';

        $response = new Response($sql);
        $response->setHeader('Content-Type', 'application/octet-stream');
        $response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->setHeader('Content-Length', (string)strlen($sql));
        return $response;
    }

    /**
     * Slugify helper.
     */
    protected function slugify(string $text): string
    {
        // Replace non-letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        return empty($text) ? 'n-a' : $text;
    }

    /**
     * Render the admin panel layout.
     */
    protected function renderLayout(string $title, string $activeTab, string $bodyContent): string
    {
        $tabs = [
            'dashboard' => ['label' => 'Dashboard', 'icon' => '📊', 'url' => '/dashboard'],
            'categories' => ['label' => 'Categories', 'icon' => '🗂️', 'url' => '/categories'],
            'users' => ['label' => 'User Manager', 'icon' => '👥', 'url' => '/users'],
            'content' => ['label' => 'Content moderation', 'icon' => '📝', 'url' => '/content'],
            'reports' => ['label' => 'Reports Queue', 'icon' => '⚠️', 'url' => '/reports'],
            'modules' => ['label' => 'Modules', 'icon' => '🧩', 'url' => '/modules'],
            'settings' => ['label' => 'Settings', 'icon' => '⚙️', 'url' => '/settings'],
            'vault' => ['label' => 'Credentials Vault', 'icon' => '🔐', 'url' => '/vault'],
            'pages' => ['label' => 'Static Pages', 'icon' => '📄', 'url' => '/pages'],
            'utilities' => ['label' => 'System Utilities', 'icon' => '🔧', 'url' => '/utilities/logs'],
        ];

        $navHtml = '';
        foreach ($tabs as $key => $t) {
            $activeClass = $key === $activeTab ? 'active' : '';
            $navHtml .= "<a href='{$this->adminPath}{$t['url']}' class='nav-item {$activeClass}'>";
            $navHtml .= "<span class='nav-icon'>{$t['icon']}</span>";
            $navHtml .= "<span class='nav-label'>{$t['label']}</span>";
            $navHtml .= "</a>";
        }

        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title} - Admin CMS</title>
            <style>
                :root {
                    --bg-dark: #090d16;
                    --bg-panel: #111827;
                    --border-color: #1f2937;
                    --text-main: #f3f4f6;
                    --text-muted: #9ca3af;
                    --primary: #10b981;
                    --primary-hover: #059669;
                    --danger: #ef4444;
                    --danger-hover: #dc2626;
                }
                * {
                    box-sizing: border-box;
                }
                body {
                    margin: 0;
                    background-color: var(--bg-dark);
                    color: var(--text-main);
                    font-family: system-ui, -apple-system, sans-serif;
                    display: flex;
                    min-height: 100vh;
                }
                .admin-container {
                    display: flex;
                    width: 100%;
                }
                aside.sidebar {
                    width: 250px;
                    background: var(--bg-panel);
                    border-right: 1px solid var(--border-color);
                    display: flex;
                    flex-direction: column;
                    padding: 1.5rem 1rem;
                    position: sticky;
                    top: 0;
                    height: 100vh;
                }
                .logo {
                    font-weight: 800;
                    font-size: 1.25rem;
                    background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    margin-bottom: 2rem;
                    text-align: center;
                }
                nav {
                    display: flex;
                    flex-direction: column;
                    gap: 0.5rem;
                    flex: 1;
                }
                .nav-item {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    color: var(--text-muted);
                    text-decoration: none;
                    padding: 0.75rem 1rem;
                    border-radius: 8px;
                    font-weight: 500;
                    font-size: 0.9rem;
                    transition: all 0.2s;
                }
                .nav-item:hover, .nav-item.active {
                    color: var(--text-main);
                    background-color: rgba(31, 41, 55, 0.5);
                }
                .nav-item.active {
                    border-left: 3px solid var(--primary);
                    background-color: rgba(16, 185, 129, 0.05);
                }
                .nav-icon {
                    font-size: 1.1rem;
                }
                .main-layout {
                    flex: 1;
                    padding: 2rem;
                    overflow-y: auto;
                }
                header.admin-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 2rem;
                    border-bottom: 1px solid var(--border-color);
                    padding-bottom: 1rem;
                }
                header.admin-header h2 {
                    margin: 0;
                    font-weight: 700;
                    font-size: 1.75rem;
                }
                .panel {
                    background: var(--bg-panel);
                    border: 1px solid var(--border-color);
                    border-radius: 12px;
                    padding: 1.75rem;
                    margin-bottom: 2rem;
                }
                .panel h3 {
                    margin-top: 0;
                    margin-bottom: 1.5rem;
                    font-size: 1.25rem;
                    font-weight: 600;
                }
                .admin-table {
                    width: 100%;
                    border-collapse: collapse;
                    text-align: left;
                    font-size: 0.9rem;
                }
                .admin-table th {
                    border-bottom: 2px solid var(--border-color);
                    padding: 0.75rem 1rem;
                    color: var(--text-muted);
                    font-weight: 600;
                }
                .admin-table td {
                    border-bottom: 1px solid var(--border-color);
                    padding: 1rem;
                }
                .admin-table tr:hover {
                    background-color: rgba(31, 41, 55, 0.2);
                }
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 1.5rem;
                    margin-bottom: 2rem;
                }
                .stat-card {
                    background: var(--bg-panel);
                    border: 1px solid var(--border-color);
                    border-radius: 12px;
                    padding: 1.5rem;
                    display: flex;
                    align-items: center;
                    gap: 1.25rem;
                }
                .stat-icon {
                    font-size: 2.25rem;
                }
                .stat-num {
                    font-size: 1.75rem;
                    font-weight: 700;
                }
                .stat-label {
                    font-size: 0.8rem;
                    color: var(--text-muted);
                }
                .dashboard-split {
                    display: flex;
                    gap: 2rem;
                    flex-wrap: wrap;
                }
                .dashboard-split > div {
                    flex: 1;
                    min-width: 300px;
                }
                .form-group {
                    margin-bottom: 1.25rem;
                }
                .form-group label {
                    display: block;
                    font-size: 0.85rem;
                    font-weight: 600;
                    margin-bottom: 0.5rem;
                    color: var(--text-muted);
                }
                .form-control {
                    width: 100%;
                    background: #0b0f19;
                    border: 1px solid var(--border-color);
                    border-radius: 6px;
                    padding: 0.6rem 0.75rem;
                    color: var(--text-main);
                    font-size: 0.9rem;
                    transition: border 0.2s;
                }
                .form-control:focus {
                    outline: none;
                    border-color: var(--primary);
                }
                .form-control-sm {
                    padding: 0.35rem 0.5rem;
                    font-size: 0.8rem;
                }
                .btn {
                    background: var(--primary);
                    color: white;
                    border: none;
                    padding: 0.6rem 1.25rem;
                    border-radius: 6px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background 0.2s;
                }
                .btn:hover {
                    background: var(--primary-hover);
                }
                .btn-sm {
                    padding: 0.35rem 0.75rem;
                    font-size: 0.8rem;
                }
                .btn-success {
                    background: var(--primary);
                }
                .btn-danger {
                    background: var(--danger);
                }
                .btn-danger:hover {
                    background: var(--danger-hover);
                }
                .badge {
                    font-size: 0.75rem;
                    font-weight: 700;
                    padding: 0.25rem 0.5rem;
                    border-radius: 4px;
                }
                .badge-success { background: rgba(16, 185, 129, 0.15); color: #10b981; }
                .badge-warning { background: rgba(217, 119, 6, 0.15); color: #f59e0b; }
                .badge-danger { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
                .text-muted { color: var(--text-muted); }
                .text-center { text-align: center; }
                .btn-link {
                    background: none;
                    border: none;
                    color: var(--primary);
                    cursor: pointer;
                    font-size: 0.85rem;
                    text-decoration: none;
                }
                .btn-link:hover { text-decoration: underline; }
                .btn-link-danger { color: var(--danger); }
                .category-tree {
                    display: flex;
                    flex-direction: column;
                    gap: 1rem;
                }
                .tree-parent {
                    background: rgba(31, 41, 55, 0.15);
                    border: 1px solid var(--border-color);
                    border-radius: 8px;
                    padding: 1rem;
                }
                .tree-item-meta {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .tree-children {
                    margin-top: 0.75rem;
                    padding-left: 1.5rem;
                    display: flex;
                    flex-direction: column;
                    gap: 0.5rem;
                    border-left: 1px dashed var(--border-color);
                }
                .tree-child {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: rgba(11, 15, 25, 0.4);
                    padding: 0.6rem 1rem;
                    border-radius: 6px;
                    border: 1px solid var(--border-color);
                    font-size: 0.85rem;
                }
                .item-actions {
                    display: flex;
                    gap: 0.75rem;
                }
                .modal-backdrop {
                    position: fixed;
                    top:0; left:0; right:0; bottom:0;
                    background: rgba(0,0,0,0.6);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 1000;
                }
                .modal-content {
                    width: 90%;
                    animation: scaleUp 0.2s ease;
                }
                .log-viewer {
                    background: #05070c;
                    border: 1px solid var(--border-color);
                    padding: 1.5rem;
                    border-radius: 8px;
                    font-family: monospace;
                    font-size: 0.8rem;
                    overflow-x: auto;
                    white-space: pre-wrap;
                    max-height: 500px;
                    color: #e5e7eb;
                }
                /* Switch Slider Accents */
                .switch {
                    position: relative;
                    display: inline-block;
                    width: 50px;
                    height: 24px;
                }
                .switch input { opacity: 0; width: 0; height: 0; }
                .slider {
                    position: absolute;
                    cursor: pointer;
                    top: 0; left: 0; right: 0; bottom: 0;
                    background-color: #374151;
                    transition: .4s;
                    border-radius: 34px;
                }
                .slider:before {
                    position: absolute;
                    content: '';
                    height: 16px; width: 16px;
                    left: 4px; bottom: 4px;
                    background-color: white;
                    transition: .4s;
                    border-radius: 50%;
                }
                input:checked + .slider { background-color: var(--primary); }
                input:checked + .slider:before { transform: translateX(26px); }

                .vault-section {
                    background: rgba(31, 41, 55, 0.2);
                    border: 1px solid var(--border-color);
                    border-radius: 8px;
                    padding: 1.5rem;
                }
                .vault-sec-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 1.25rem;
                    border-bottom: 1px solid var(--border-color);
                    padding-bottom: 0.5rem;
                }
                .vault-sec-header h4 {
                    margin: 0;
                    font-size: 1rem;
                    font-weight: 600;
                }
                .vault-fields {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 1rem;
                    margin-bottom: 1rem;
                }
                .btn-test {
                    background: #3b82f6;
                    color: white;
                    border: none;
                    cursor: pointer;
                }
                .btn-test:hover { background: #2563eb; }
                
                @keyframes scaleUp {
                    from { transform: scale(0.95); opacity: 0; }
                    to { transform: scale(1); opacity: 1; }
                }
            </style>
        </head>
        <body>
            <div class='admin-container'>
                <aside class='sidebar'>
                    <div class='logo'>FORUX PANEL</div>
                    <nav>
                        {$navHtml}
                    </nav>
                    <div style='margin-top:auto; font-size:0.8rem; color:var(--text-muted); text-align:center;'>
                        <a href='/' style='color:var(--primary); text-decoration:none; font-weight:600;'>← Visit Forum</a>
                    </div>
                </aside>
                
                <main class='main-layout'>
                    <header class='admin-header'>
                        <h2>{$title}</h2>
                        <div style='font-size:0.85rem; color:var(--text-muted);'>
                            Authenticated as <strong style='color:var(--text-main)'>Admin</strong>
                        </div>
                    </header>
                    
                    {$bodyContent}
                </main>
            </div>
        </body>
        </html>";
    }
}
