<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\AdminRepository;

/** "My profile" — any signed-in admin can change their own name, email and password. */
final class ProfileController extends Controller
{
    public function edit(): never
    {
        $this->view('admin/profile/edit', [
            'title' => 'My profile',
            'pretitle' => 'Your account',
            'user' => Auth::user(),
        ]);
    }

    public function update(): never
    {
        $me = (int) Auth::id();
        $v = Validator::make([
            'name' => $this->request->input('name', ''),
            'email' => mb_strtolower((string) $this->request->input('email', '')),
        ], [
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
        ]);
        $errors = $v->errors();
        $data = $v->validated();
        if (!isset($errors['email']) && AdminRepository::emailTaken((string) $data['email'], $me)) {
            $errors['email'] = 'Another admin already uses this email.';
        }
        if ($errors !== []) {
            $this->back('/profile', $errors);
        }

        AdminRepository::update($me, ['name' => $data['name'], 'email' => $data['email']]);
        Activity::log('updated', 'admin', $me, 'Updated own profile');
        Flash::success('Your profile has been saved.');
        $this->redirect('/profile');
    }

    public function password(): never
    {
        $me = (int) Auth::id();
        $current = $_POST['current_password'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirmation'] ?? '';
        $errors = [];

        if (!is_string($current) || !is_string($password) || !is_string($confirm)) {
            $errors['password'] = 'Password is invalid.';
        } else {
            $hash = AdminRepository::passwordHash($me);
            if ($current === '') {
                $errors['current_password'] = 'Enter your current password.';
            } elseif ($hash === null || !password_verify($current, $hash)) {
                $errors['current_password'] = 'Your current password is not correct.';
            }
            if ($password === '') {
                $errors['password'] = 'Enter a new password.';
            } elseif (mb_strlen($password) < 10) {
                $errors['password'] = 'New password must be at least 10 characters.';
            } elseif (mb_strlen($password) > 200) {
                $errors['password'] = 'New password may not be greater than 200 characters.';
            } elseif (!hash_equals($password, $confirm)) {
                $errors['password_confirmation'] = 'The two new passwords do not match.';
            }
        }
        if ($errors !== []) {
            $this->back('/profile', $errors, 'Your password was not changed. Please check the highlighted fields.');
        }

        AdminRepository::setPassword($me, (string) $password);
        Session::regenerate();
        Activity::log('updated', 'admin', $me, 'Changed own password');
        Flash::success('Your password has been changed.');
        $this->redirect('/profile');
    }
}
