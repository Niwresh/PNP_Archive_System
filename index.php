<?php
session_start();

define('ADMIN_EMAIL', 'admin@phnational.gov.ph');
define('ADMIN_PASSWORD', 'PNADMIN2024');
define('ADMIN_HINT', 'Starts with PN and ends with 2024'); // 👈 your hint

$error_email = '';
$error_password = '';
$password_hint = '';

// initialize attempt counter
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($email !== ADMIN_EMAIL) {

        $error_email = 'Unknown email';

    } elseif ($password !== ADMIN_PASSWORD) {

        $_SESSION['login_attempts']++; // increase attempts
        $error_password = 'Incorrect password';

        // show hint after 3 tries
        if ($_SESSION['login_attempts'] >= 3) {
            $password_hint = ADMIN_HINT;
        }

    } else {

        // reset attempts on success
        $_SESSION['login_attempts'] = 0;

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_email'] = $email;
        $_SESSION['login_time'] = time();

        header('Location: homepage.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PNP Admin Login</title>
<link rel="stylesheet" href="css/style.css">
</head>

<body>

<div class="login-container">
<div class="login-box">

<div class="philippine-seal">
<img src="images/PNP logo.png" alt="Philippine National Police Logo">
</div>

<h1>PNP ADMIN PORTAL</h1>

<form method="POST" action="">

<!-- EMAIL -->
<div class="input-group">
<label for="email">EMAIL</label>
<input
type="email"
id="email"
name="email"
placeholder="Enter admin email"
value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
class="<?php echo $error_email ? 'error' : ''; ?>"
required
autofocus
>
<?php if ($error_email): ?>
<small class="hint"><?php echo $error_email; ?></small>
<?php endif; ?>
</div>

<!-- PASSWORD -->
<div class="input-group">
<label for="password">PASSWORD</label>
<input
type="password"
id="password"
name="password"
placeholder="Enter admin password"
class="<?php echo $error_password ? 'error' : ''; ?>"
required
>

<?php if ($error_password): ?>
<small class="hint"><?php echo $error_password; ?></small>
<?php endif; ?>

<?php if ($password_hint): ?>
<small class="hint" style="color:#0038a8;">
💡 Hint: <?php echo $password_hint; ?>
</small>
<?php endif; ?>

</div>

<button type="submit" class="login-btn">LOGIN</button>

</form>
</div>
</div>

<script src="js/main.js"></script>
</body>
</html>