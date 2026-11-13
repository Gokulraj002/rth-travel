<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Gate;
use App\Core\License;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\AdminRepository;

/**
 * Team & roles — owner only (enforced by the 'owner' route middleware).
 * Roles come from config/permissions.php; on Basic/Pro only Gate::assignableRoles() (owner/editor) may be given.
 */
final class UserController extends Controller
{
    public function index(): never
    {
        $this->view('admin/users/index', [
            'title' => 'Team & roles',
            'pretitle' => 'Who can sign in to this panel',
            'users' => AdminRepository::all(),
            'meId' => Auth::id(),
            'rolesUnlocked' => License::has('roles'),
            'pageActions' => '<a href="' . e(url('/users/roles')) . '" class="btn">' . icon('shield') . ' What each role can do</a>'
                . '<a href="' . e(url('/users/create')) . '" class="btn btn-primary">' . icon('plus') . ' Add team member</a>',
        ]);
    }

    /** Read-only permission matrix: roles × permissions. */
    public function roles(): never
    {
        $this->view('admin/users/roles', [
            'title' => 'What each role can do',
            'pretitle' => 'Team & roles',
            'roles' => Gate::roles(),
            'permissions' => config('permissions')['permissions'],
            'assignable' => Gate::assignableRoles(),
            'counts' => AdminRepository::countByRole(),
            'rolesUnlocked' => License::has('roles'),
        ]);
    }

    public function create(): never
    {
        $this->view('admin/users/form', [
            'title' => 'Add team member',
            'pretitle' => 'Team & roles',
            'user' => null,
            'isSelf' => false,
            'roles' => Gate::roles(),
            'assignable' => Gate::assignableRoles(),
        ]);
    }

    public function store(): never
    {
        $input = $this->input();
        $v = Validator::make($input, $this->rules(), $this->labels());
        $errors = $v->errors();
        $data = $v->validated();
        $errors += $this->passwordErrors(true);
        $errors += $this->roleErrors($data['role'] ?? null, null);
        if (!isset($errors['email']) && AdminRepository::emailTaken((string) $data['email'])) {
            $errors['email'] = 'Another team member already uses this email.';
        }
        if ($errors !== []) {
            $this->back('/users/create', $errors);
        }

        $id = AdminRepository::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'role' => $data['role'],
            'is_active' => (int) $data['is_active'],
        ], (string) $_POST['password']);
        Activity::log('created', 'admin', $id, sprintf('Added team member “%s” as %s', $data['name'], Gate::roleLabel($data['role'])));
        Flash::success(sprintf('%s can now sign in with %s.', $data['name'], $data['email']));
        $this->redirect('/users');
    }

    public function edit(string $id): never
    {
        $user = $this->findOr404($id);
        $this->view('admin/users/form', [
            'title' => 'Edit ' . $user['name'],
            'pretitle' => 'Team & roles',
            'user' => $user,
            'isSelf' => (int) $user['id'] === Auth::id(),
            'roles' => Gate::roles(),
            'assignable' => Gate::assignableRoles(),
        ]);
    }

    public function update(string $id): never
    {
        $user = $this->findOr404($id);
        $uid = (int) $user['id'];
        $isSelf = $uid === Auth::id();

        $v = Validator::make($this->input(), $this->rules(), $this->labels());
        $errors = $v->errors();
        $data = $v->validated();
        $errors += $this->passwordErrors(false);
        $errors += $this->roleErrors($data['role'] ?? null, (string) $user['role']);
        if (!isset($errors['email']) && AdminRepository::emailTaken((string) $data['email'], $uid)) {
            $errors['email'] = 'Another team member already uses this email.';
        }

        if ($errors === []) {
            $active = (int) $data['is_active'];
            if ($isSelf && $active !== 1) {
                $errors['is_active'] = 'You cannot deactivate your own account.';
            }
            $wasActiveOwner = $user['role'] === 'owner' && (int) $user['is_active'] === 1;
            $staysActiveOwner = $data['role'] === 'owner' && $active === 1;
            if ($wasActiveOwner && !$staysActiveOwner && AdminRepository::activeOwnerCount($uid) === 0) {
                $errors['role'] = 'This is the only active owner. Make someone else an owner first.';
            }
        }
        if ($errors !== []) {
            $this->back('/users/' . $uid . '/edit', $errors);
        }

        AdminRepository::update($uid, [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'role' => $data['role'],
            'is_active' => (int) $data['is_active'],
        ]);
        $password = (string) ($_POST['password'] ?? '');
        if ($password !== '') {
            AdminRepository::setPassword($uid, $password);
        }

        $changes = [];
        if ($data['role'] !== $user['role']) {
            $changes[] = 'role ' . Gate::roleLabel((string) $user['role']) . ' → ' . Gate::roleLabel($data['role']);
        }
        if ((int) $data['is_active'] !== (int) $user['is_active']) {
            $changes[] = (int) $data['is_active'] === 1 ? 'activated' : 'deactivated';
        }
        if ($password !== '') {
            $changes[] = 'new password';
        }
        Activity::log('updated', 'admin', $uid, sprintf('Updated team member “%s”%s', $data['name'], $changes ? ' (' . implode(', ', $changes) . ')' : ''));
        Flash::success('Team member saved.');
        // Owners who demote themselves can no longer open /users.
        $this->redirect($isSelf && $data['role'] !== 'owner' ? '/' : '/users');
    }

    public function destroy(string $id): never
    {
        $user = $this->findOr404($id);
        $uid = (int) $user['id'];
        if ($uid === Auth::id()) {
            Flash::error('You cannot delete your own account.');
            $this->redirect('/users');
        }
        if ($user['role'] === 'owner' && (int) $user['is_active'] === 1 && AdminRepository::activeOwnerCount($uid) === 0) {
            Flash::error('You cannot delete the only active owner.');
            $this->redirect('/users');
        }
        AdminRepository::delete($uid);
        Activity::log('deleted', 'admin', $uid, sprintf('Removed team member “%s”', $user['name']));
        Flash::success(sprintf('%s can no longer sign in.', $user['name']));
        $this->redirect('/users');
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function input(): array
    {
        return [
            'name' => $this->request->input('name', ''),
            'email' => mb_strtolower((string) $this->request->input('email', '')),
            'phone' => $this->request->input('phone', ''),
            'role' => $this->request->input('role', ''),
            'is_active' => $this->request->input('is_active', '0'),
        ];
    }

    /** @return array<string,string> */
    private function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'phone' => 'nullable|phone|max:30',
            'role' => 'required|in:' . implode(',', array_keys(Gate::roles())),
            'is_active' => 'bool',
        ];
    }

    /**
     * The role must be assignable on the current plan. Keeping a person's existing role is allowed
     * (so a Sales user can still be edited after a downgrade), but no one can be newly given a locked role.
     * @return array<string,string>
     */
    private function roleErrors(mixed $role, ?string $currentRole): array
    {
        if (!is_string($role) || $role === '') {
            return [];
        }
        if (in_array($role, Gate::assignableRoles(), true) || $role === $currentRole) {
            return [];
        }
        return ['role' => sprintf('The %s role needs the Premium plan (Staff roles & permissions). Choose Owner or Editor, or upgrade.', Gate::roleLabel($role))];
    }

    /** @return array<string,string> */
    private function labels(): array
    {
        return ['is_active' => 'Active', 'phone' => 'Mobile number'];
    }

    /** Password checks (raw $_POST: passwords are never trimmed). @return array<string,string> */
    private function passwordErrors(bool $required): array
    {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirmation'] ?? '';
        if (!is_string($password) || !is_string($confirm)) {
            return ['password' => 'Password is invalid.'];
        }
        if ($password === '') {
            return $required ? ['password' => 'Password is required.'] : [];
        }
        if (mb_strlen($password) < 10) {
            return ['password' => 'Password must be at least 10 characters.'];
        }
        if (mb_strlen($password) > 200) {
            return ['password' => 'Password may not be greater than 200 characters.'];
        }
        if (!hash_equals($password, $confirm)) {
            return ['password_confirmation' => 'The two passwords do not match.'];
        }
        return [];
    }

    /** @return array<string,mixed> */
    private function findOr404(string $rawId): array
    {
        $user = AdminRepository::find($this->id($rawId));
        if ($user === null) {
            Response::abort(404);
        }
        return $user;
    }
}
