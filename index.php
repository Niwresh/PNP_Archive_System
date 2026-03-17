<?php
session_start();

define('ADMIN_EMAIL', 'admin@phnational.gov.ph');
define('ADMIN_PASSWORD', 'PNADMIN2024');

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if ($email === ADMIN_EMAIL && $password === ADMIN_PASSWORD) {

$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_email'] = $email;
$_SESSION['login_time'] = time();

header('Location: homepage.php');
exit();

}else{

$error = 'This is for Admin Only';

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

<?php if ($error): ?>
<div class="error-message">
<?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<form method="POST" action="">

<div class="input-group">

<label for="email">EMAIL</label>

<input
type="email"
id="email"
name="email"
placeholder="Enter admin email"
value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
required
autofocus
>

</div>

<div class="input-group">

<label for="password">PASSWORD</label>

<input
type="password"
id="password"
name="password"
placeholder="Enter admin password"
required
>

</div>

<button type="submit" class="login-btn">
LOGIN
</button>

</form>

</div>
</div>

<script src="js/main.js"></script>

</body>
</html>