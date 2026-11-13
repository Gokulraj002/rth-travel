<div class="card card-md">
  <div class="card-body">
    <h1 class="h2 text-center mb-1">Welcome back</h1>
    <p class="text-center text-secondary mb-4">Sign in to manage packages, destinations and enquiries.</p>
    <form method="post" action="<?= e(url('/login')) ?>" autocomplete="on" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label" for="email">Email</label>
        <input type="email" class="form-control" id="email" name="email" value="<?= e(old('email')) ?>" placeholder="you@example.com" autocomplete="username" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <input type="password" class="form-control" id="password" name="password" placeholder="Your password" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Sign in</button>
    </form>
  </div>
</div>
