<h1 class="rth-auth-title">Welcome back</h1>
<p class="rth-auth-sub">Sign in to manage your trips, enquiries and website.</p>
<div class="rth-auth-card">
  <form method="post" action="<?= e(url('/login')) ?>" autocomplete="on" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label" for="email">Email</label>
      <input type="email" class="form-control" id="email" name="email" value="<?= e(old('email')) ?>" placeholder="you@example.com" autocomplete="username" required autofocus>
    </div>
    <div class="mb-4">
      <label class="form-label" for="password">Password</label>
      <input type="password" class="form-control" id="password" name="password" placeholder="Your password" autocomplete="current-password" required>
    </div>
    <button type="submit" class="btn btn-primary w-100">Sign in <?= icon('arrow-right', 'icon ms-1') ?></button>
  </form>
</div>
