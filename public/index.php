<?php

use Mt2Cms\Model\Database;
use Mt2Cms\Repository\AccountRepository;

define('BASE_DIR', dirname(__DIR__));

$autoloadPath = BASE_DIR . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    throw new \Exception('Autoload file not found');
}

require_once $autoloadPath;

$error = null;
$username = '';
$email = '';
$socialId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $socialId = $_POST['social_id'] ?? '';
    $success = false;

    $accounts = new AccountRepository(new Database());

    try {
        $accounts->create($username, $email, $password, $socialId);
        $success = true;

        $username = '';
        $email = '';
        $socialId = '';
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <title>Mt2 CMS - Registration</title>
</head>

<body class="min-h-screen flex items-center justify-center bg-slate-900/90">
    <form method="post" class="w-full max-w-md rounded-2xl border border-slate-700 p-8 shadow-2xl">

        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-white">
                Create Account
            </h1>
        </div>

        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-2">
                <label for="username" class="text-sm font-medium text-slate-300">
                    Username
                </label>
                <input id="username" type="text" name="username" placeholder="Enter your username"
                    value="<?= htmlspecialchars($username) ?>" required
                    class="rounded-lg border border-slate-700 bg-slate-800 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div class="flex flex-col gap-2">
                <label for="email" class="text-sm font-medium text-slate-300">
                    Email
                </label>
                <input id="email" type="email" name="email" placeholder="Enter your email"
                    value="<?= htmlspecialchars($email) ?>" required
                    class="rounded-lg border border-slate-700 bg-slate-800 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div class="flex flex-col gap-2">
                <label for="social_id" class="text-sm font-medium text-slate-300">
                    Delete Character PIN
                </label>

                <input id="social_id" type="text" name="social_id" placeholder="Enter your PIN"
                    value="<?= htmlspecialchars($socialId) ?>" required
                    class="rounded-lg border border-slate-700 bg-slate-800 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div class="flex flex-col gap-2">
                <label for="password" class="text-sm font-medium text-slate-300">
                    Password
                </label>

                <input id="password" type="password" name="password" placeholder="Enter your password" required
                    class="rounded-lg border border-slate-700 bg-slate-800 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
            </div>

            <?php if ($error): ?>
                <p class="text-center text-red-500">
                    <?= htmlspecialchars($error) ?>
                </p>
            <?php endif; ?>

            <?php if ($success): ?>
                <p class="text-center text-green-500">Account created</p>
            <?php endif; ?>

            <button type="submit"
                class="mt-2 cursor-pointer rounded-lg bg-blue-600 px-4 py-3 font-semibold text-white transition hover:bg-blue-500 active:scale-[0.98]">
                Create Account
            </button>
        </div>
    </form>
</body>

</html>