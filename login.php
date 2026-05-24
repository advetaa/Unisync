<?php
session_start();
if (isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }
include 'db.php';

$error = ''; $activeTab = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'login') {
        $email = trim($_POST['email'] ?? ''); $password = $_POST['password'] ?? '';
        if ($email && $password) {
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->bind_param("s", $email); $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_name'] = $user['full_name'];
                header("Location: index.php"); exit;
            } else { $error = "Incorrect email or password."; }
        } else { $error = "Please fill in both fields."; }
    }

    if ($action === 'register') {
        $activeTab = 'register';
        $fullName = trim($_POST['full_name'] ?? ''); $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? ''; $course = trim($_POST['course'] ?? '');
        $semester = intval($_POST['semester'] ?? 1);
        if ($fullName && $email && $password) {
            $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $chk->bind_param("s", $email); $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = "Email already registered. Please log in.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $conn->prepare("INSERT INTO users (full_name,email,password,course,semester) VALUES (?,?,?,?,?)");
                $ins->bind_param("ssssi", $fullName, $email, $hash, $course, $semester);
                if ($ins->execute()) {
                    $_SESSION['user_id'] = $conn->insert_id;
                    $_SESSION['user_name'] = $fullName;
                    header("Location: index.php"); exit;
                } else { $error = "Registration failed. Try again."; }
            }
        } else { $error = "Name, email and password are required."; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>UniSync — Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <style>
    body{display:flex;min-height:100vh;margin:0;background:#f7f6f3}
    .auth-page{display:flex;min-height:100vh;width:100%}
    .err{background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;padding:9px 13px;border-radius:8px;font-size:.83rem;margin-bottom:14px;width:100%}
  </style>
</head>
<body>
<div class="auth-page">
  <div class="auth-left">
    <div class="auth-brand-lg">UniSync</div>
    <p class="auth-tagline">Unified planner for study, exams, and budgets.</p>
    <div class="auth-stats-row">
      <div class="auth-stat"><div class="auth-stat-num">28h</div><div class="auth-stat-label">Study Hours</div></div>
      <div class="auth-stat"><div class="auth-stat-num">12</div><div class="auth-stat-label">Sessions</div></div>
      <div class="auth-stat"><div class="auth-stat-num">7</div><div class="auth-stat-label">Day Streak</div></div>
    </div>
  </div>
  <div class="auth-right">
    <div class="auth-form-title" id="authTitle"><?= $activeTab==='register'?'Create account':'Welcome back' ?></div>
    <div class="auth-form-sub" id="authSub"><?= $activeTab==='register'?'Join UniSync today':'Sign in to your account' ?></div>
    <div class="auth-tabs">
      <button class="auth-tab <?= $activeTab==='login'?'active':'' ?>"    onclick="switchAuthTab('login')"    id="loginTabBtn">Login</button>
      <button class="auth-tab <?= $activeTab==='register'?'active':'' ?>" onclick="switchAuthTab('register')" id="registerTabBtn">Join</button>
    </div>
    <?php if($error): ?><div class="err"><i class="fa fa-exclamation-circle"></i> <?=htmlspecialchars($error)?></div><?php endif; ?>

    <!-- LOGIN FORM -->
    <form method="POST" id="loginForm" style="width:100%;<?= $activeTab==='register'?'display:none':'' ?>">
      <input type="hidden" name="action" value="login">
      <div class="form-group" style="margin-bottom:13px">
        <label class="form-label-auth">Email</label>
        <input class="form-input-auth" type="email" name="email" placeholder="you@university.edu" required>
      </div>
      <div class="form-group" style="margin-bottom:13px">
        <label class="form-label-auth">Password</label>
        <input class="form-input-auth" type="password" name="password" placeholder="Your password" required>
      </div>
      <button type="submit" class="btn-auth-primary">Sign In <i class="fa fa-arrow-right" style="margin-left:6px"></i></button>
    </form>

    <!-- REGISTER FORM -->
    <form method="POST" id="registerForm" style="width:100%;<?= $activeTab==='login'?'display:none':'' ?>">
      <input type="hidden" name="action" value="register">
      <div class="form-group" style="margin-bottom:11px">
        <label class="form-label-auth">Full Name *</label>
        <input class="form-input-auth" type="text" name="full_name" placeholder="Your full name" required>
      </div>
      <div class="form-group" style="margin-bottom:11px">
        <label class="form-label-auth">Email *</label>
        <input class="form-input-auth" type="email" name="email" placeholder="you@university.edu" required>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:11px">
        <div class="form-group"><label class="form-label-auth">Course</label>
          <input class="form-input-auth" type="text" name="course" placeholder="B.Tech CSE"></div>
        <div class="form-group"><label class="form-label-auth">Semester</label>
          <input class="form-input-auth" type="number" name="semester" min="1" max="10" value="1"></div>
      </div>
      <div class="form-group" style="margin-bottom:13px">
        <label class="form-label-auth">Password *</label>
        <input class="form-input-auth" type="password" name="password" placeholder="Create a password" required>
      </div>
      <button type="submit" class="btn-auth-primary">Create Account <i class="fa fa-arrow-right" style="margin-left:6px"></i></button>
    </form>

    <div class="auth-divider" style="margin-top:16px">or</div>
    <button class="auth-social-btn" onclick="alert('Google OAuth requires Google Cloud Console setup.')">
      <svg width="16" height="16" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
      Continue with Google
    </button>
    <div class="auth-social-grid">
      <button class="auth-social-btn-sm"><i class="fa fa-graduation-cap"></i> University SSO</button>
      <button class="auth-social-btn-sm">
        <svg width="14" height="14" viewBox="0 0 21 21"><rect x="1" y="1" width="9" height="9" fill="#f25022"/><rect x="11" y="1" width="9" height="9" fill="#7fba00"/><rect x="1" y="11" width="9" height="9" fill="#00a4ef"/><rect x="11" y="11" width="9" height="9" fill="#ffb900"/></svg>
        Microsoft
      </button>
    </div>
    <p class="auth-footer-text" id="authFooterText">
      <?= $activeTab==='register' ? "Already have an account? <a href='#' onclick=\"switchAuthTab('login')\">Sign in</a>" : "Don't have an account? <a href='#' onclick=\"switchAuthTab('register')\">Join UniSync</a>" ?>
    </p>
  </div>
</div>
<script>
function switchAuthTab(t){
  var lf=document.getElementById('loginForm'),rf=document.getElementById('registerForm');
  var lb=document.getElementById('loginTabBtn'),rb=document.getElementById('registerTabBtn');
  if(t==='login'){
    lf.style.display='block';rf.style.display='none';
    lb.classList.add('active');rb.classList.remove('active');
    document.getElementById('authTitle').textContent='Welcome back';
    document.getElementById('authSub').textContent='Sign in to your account';
    document.getElementById('authFooterText').innerHTML="Don't have an account? <a href='#' onclick=\"switchAuthTab('register')\">Join UniSync</a>";
  }else{
    lf.style.display='none';rf.style.display='block';
    lb.classList.remove('active');rb.classList.add('active');
    document.getElementById('authTitle').textContent='Create account';
    document.getElementById('authSub').textContent='Join UniSync today';
    document.getElementById('authFooterText').innerHTML="Already have an account? <a href='#' onclick=\"switchAuthTab('login')\">Sign in</a>";
  }
}
</script>
</body>
</html>
