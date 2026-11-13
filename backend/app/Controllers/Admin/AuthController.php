<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Env;
use App\Core\Flash;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

final class AuthController extends Controller
{
    public function showLogin(): never
    {
        $this->view('auth/login', ['title' => 'Sign in'], 'layouts/auth');
    }

    public function login(): never
    {
        $email = (string) $this->request->input('email', '');
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            Flash::withInput(['email' => $email]);
            Flash::error('Enter your email and password.');
            $this->redirect('/login');
        }

        $result = Auth::attempt($email, $password, $this->request->ip());
        if ($result !== 'ok') {
            Flash::withInput(['email' => $email]);
            Flash::error(match ($result) {
                'throttled' => 'Too many failed attempts. Please wait 15 minutes and try again.',
                'inactive' => 'This account has been disabled.',
                default => 'Incorrect email or password.',
            });
            $this->redirect('/login');
        }

        $intended = (string) Session::get('intended', '/');
        Session::forget('intended');
        // Only redirect to local paths.
        if (!str_starts_with($intended, '/') || str_starts_with($intended, '//')) {
            $intended = '/';
        }
        $this->redirect($intended);
    }

    public function logout(): never
    {
        Auth::logout();
        Session::start();
        Flash::success('You have been signed out.');
        $this->redirect('/login');
    }

    /**
     * First-run setup: create the owner account from the browser (no SSH needed).
     * Allowed only when the admins table is EMPTY and ?token= matches SETUP_TOKEN in .env.
     */
    public function showSetup(): never
    {
        $this->guardSetup((string) $this->request->query('token', ''));
        $this->view('auth/setup', ['title' => 'Create owner account', 'token' => (string) $this->request->query('token', '')], 'layouts/auth');
    }

    public function setup(): never
    {
        $token = (string) $this->request->input('token', '');
        $this->guardSetup($token);

        $data = [
            'name' => $this->request->input('name', ''),
            'email' => mb_strtolower((string) $this->request->input('email', '')),
            'password' => (string) ($_POST['password'] ?? ''),
        ];
        $v = Validator::make($data, [
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'password' => 'required|string|min:10|max:200',
        ]);
        if ($v->fails() || $data['password'] !== (string) ($_POST['password_confirmation'] ?? '')) {
            $errors = $v->errors();
            if (!isset($errors['password']) && $data['password'] !== (string) ($_POST['password_confirmation'] ?? '')) {
                $errors['password'] = 'Passwords do not match.';
            }
            Flash::withInput($data, $errors);
            Flash::error('Please fix the highlighted fields.');
            Response::redirect(url('/setup?token=' . rawurlencode($token)));
        }

        Database::insert('admins', [
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role' => 'owner',
        ]);
        Flash::success('Owner account created. Sign in below — and remove SETUP_TOKEN from .env.');
        $this->redirect('/login');
    }

    private function guardSetup(string $token): void
    {
        $expected = (string) Env::get('SETUP_TOKEN', '');
        $hasAdmins = (int) Database::value('SELECT COUNT(*) FROM admins') > 0;
        if ($hasAdmins || $expected === '' || strlen($expected) < 16 || !hash_equals($expected, $token)) {
            Response::abort(404);
        }
    }
}
