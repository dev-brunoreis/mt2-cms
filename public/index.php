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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $accounts = new AccountRepository(new Database());

    try {
        $accounts->create($username, $password);

        echo 'Success';
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Mt2 CMS - Registration</title>
</head>

<body>
    <?php if ($error): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post">
        <input type="text" name="username" placeholder="Username" value="<?= htmlspecialchars($username) ?>">
        <input type="text" name="email" id="email" placeholder="Email" value="<?= htmlspecialchars($email) ?>">
        <input type="password" name="password" placeholder="Password">
        <input type="submit" value="Sign up">
    </form>
</body>

</html>