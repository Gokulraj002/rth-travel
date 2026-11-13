<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\AdminRepository;

/** Admin user management — owner only (enforced by the 'owner' route middleware). */
final class UserController extends Controller
{
    public function index(): never
    {
        $this->view('admin/users/index', [
            'title' => 'Admin users',
            'pretitle' => 'Who can sign in to this panel',
            'users' => AdminRepository::all(),
            'meId' => Auth::id(),
        ]);
    }

    public function create(): never
    {
        $this->view('admin/users/form', [
            'title' => 'Add admin user',
            'pretitle' => 'Admin users',
            'user' => null,
            'isSelf' => false,
        ]);
    }

    public function store(): never
    {
        $input = $this->input();
        $v = Validator::make($input, $this->rules(true), $this->labels());
        $errors = $v->errors();
        $data = $v->validated();
        $errors += $this->passwordErrors(true);
        if (!isset($errors['email']) && AdminRepository::emailTaken((string) $data['email'])) {
            $errors['email'] = 'Another admin already uses this email.';
        }
        if ($errors !== []) {
            $this->back('/users/create', $errors);
        }

        $id = AdminRepository::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'is_active' => (int) $data['is_active'],
        ], (string) $_POST['password']);
        Activity::log('created', 'admin', $id, sprintf('Added admin user “%s” (%s)', $data['name'], AdminRepository::ROLES[$data['role']]));
        Flash::success(sprintf('%s can now sign in with %s.', $data['name'], $data['email']));
        $this->redirect('/users');
    }

    public function edit(string $id): never
    {
        $user = $this->findOr404($id);
        $this->view('admin/users/form', [
            'title' => 'Edit ' . $user['name'],
            'pretitle' => 'Admin users',
            'user' => $user,
            'isSelf' => (int) $user['id'] === Auth::id(),
        ]);
    }

    public function update(string $id): never
    {
        $user = $this->findOr404($id);
        $uid = (int) $user['id'];
        $isSelf = $uid === Auth::id();

        $v = Validator::make($this->input(), $this->rules(false), $this->labels());
        $errors = $v->errors();
        $data = $v->validated();
        $errors += $this->passwordErrors(false);
        if (!isset($errors['email']) && AdminRepository::emailTaken((string) $data['email'], $uid)) {
            $errors['email'] = 'Another admin already uses this email.';
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
            'role' => $data['role'],
            'is_active' => (int) $data['is_active'],
        ]);
        $password = (string) ($_POST['password'] ?? '');
        if ($password !== '') {
            AdminRepository::setPassword($uid, $password);
        }

        Activity::log('updated', 'admin', $uid, sprintf('Updated admin user “%s”%s', $data['name'], $password !== '' ? ' (new password)' : ''));
        Flash::success('Admin user saved.');
        // If owners demoted themselves they can no longer see /users.
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
        Activity::log('deleted', 'admin', $uid, sprintf('Removed admin user “%s”', $user['name']));
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
            'role' => $this->request->input('role', ''),
            'is_active' => $this->request->input('is_active', '0'),
        ];
    }

    /** @return array<string,string> */
    private function rules(bool $creating): array
    {
        return [
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'role' => 'required|in:' . implode(',', array_keys(AdminRepository::ROLES)),
            'is_active' => 'bool',
        ];
    }

    /** @return array<string,string> */
    private function labels(): array
    {
        return ['is_active' => 'Active'];
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
