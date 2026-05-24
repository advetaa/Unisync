<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
include 'db.php';

$userId = (int)$_SESSION['user_id'];

$user         = $conn->query("SELECT * FROM users WHERE user_id=$userId")->fetch_assoc();
$pendingCount = $conn->query("SELECT COUNT(*) AS c FROM tasks WHERE user_id=$userId AND status='pending'")->fetch_assoc()['c'];
$doneCount    = $conn->query("SELECT COUNT(*) AS c FROM tasks WHERE user_id=$userId AND status='completed'")->fetch_assoc()['c'];
$totalTasks   = $pendingCount + $doneCount;
$budgetTotal  = $conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE user_id=$userId")->fetch_assoc()['t'];
$studyTotal   = $conn->query("SELECT COALESCE(SUM(hours_studied),0) AS t FROM study_sessions WHERE user_id=$userId")->fetch_assoc()['t'];
$tasks        = $conn->query("SELECT * FROM tasks WHERE user_id=$userId ORDER BY due_date ASC");
$allExpenses  = $conn->query("SELECT * FROM expenses WHERE user_id=$userId ORDER BY expense_date DESC");
$expenses     = $conn->query("SELECT * FROM expenses WHERE user_id=$userId ORDER BY expense_date DESC LIMIT 6");
$notifications= $conn->query("SELECT * FROM notifications WHERE user_id=$userId ORDER BY created_at DESC LIMIT 5");

$catData = [];
$catQ = $conn->query("SELECT category, SUM(amount) as total FROM expenses WHERE user_id=$userId GROUP BY category ORDER BY total DESC");
while($r = $catQ->fetch_assoc()) { $catData[$r['category']] = (float)$r['total']; }

$chartLabels = []; $chartHours = [];
$cq = $conn->query("SELECT subject, SUM(hours_studied) as h FROM study_sessions WHERE user_id=$userId GROUP BY subject ORDER BY h DESC LIMIT 7");
while($r = $cq->fetch_assoc()){ $chartLabels[] = $r['subject']; $chartHours[] = (float)$r['h']; }

$weekLabels = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
$weekHours  = [0,0,0,0,0,0,0];
$wq = $conn->query("SELECT DAYOFWEEK(session_date) as d, SUM(hours_studied) as h FROM study_sessions WHERE user_id=$userId AND session_date >= DATE_SUB(CURDATE(),INTERVAL 7 DAY) GROUP BY d");
while($r=$wq->fetch_assoc()){ $idx = ($r['d'] + 5) % 7; $weekHours[$idx] = (float)$r['h']; }

$monthData = [];
$mq = $conn->query("SELECT DATE_FORMAT(expense_date,'%b') as m, SUM(amount) as t FROM expenses WHERE user_id=$userId AND expense_date >= DATE_SUB(CURDATE(),INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(expense_date,'%Y-%m') ORDER BY expense_date ASC");
while($r=$mq->fetch_assoc()){ $monthData[$r['m']] = (float)$r['t']; }

$completionRate = $totalTasks > 0 ? round(($doneCount/$totalTasks)*100) : 0;
$firstName = htmlspecialchars(explode(' ', $user['full_name'])[0]);
$greeting = (date('H') < 12) ? 'Good morning' : ((date('H') < 17) ? 'Good afternoon' : 'Good evening');

function getCatIcon($cat) {
  $icons = ['Food'=>'🍔','Travel'=>'🚌','Books'=>'📚','Stationery'=>'✏️','Entertainment'=>'🎮','Health'=>'💊','Clothing'=>'👕','Other'=>'📦'];
  return $icons[$cat] ?? '📦';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>UniSync — Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="style.css">
</head>
<body>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon"><span>U</span></div>
    <div class="brand-name">UniSync</div>
  </div>
  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
    <div>
      <div class="user-name"><?= htmlspecialchars($user['full_name']) ?></div>
      <div class="user-sem">Sem <?= $user['semester'] ?> · <?= htmlspecialchars($user['course'] ?: 'Student') ?></div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <button class="nav-item active" onclick="showTab('home')" data-tab="home"><i class="fa fa-home"></i><span>Dashboard</span></button>
    <button class="nav-item" onclick="showTab('planner')" data-tab="planner"><i class="fa fa-list-check"></i><span>Planner</span></button>
    <button class="nav-item" onclick="showTab('review')" data-tab="review"><i class="fa fa-chart-line"></i><span>Statistics</span></button>
    <button class="nav-item" onclick="showTab('budget')" data-tab="budget"><i class="fa fa-wallet"></i><span>Budget</span></button>
    <button class="nav-item" onclick="showTab('pomodoro')" data-tab="pomodoro"><i class="fa fa-clock"></i><span>Focus Timer</span></button>
    <button class="nav-item" onclick="showTab('account')" data-tab="account"><i class="fa fa-user-circle"></i><span>Account</span></button>
    <button class="nav-item" onclick="showTab('settings')" data-tab="settings"><i class="fa fa-gear"></i><span>Settings</span></button>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="sidebar-footer-btn"><i class="fa fa-sign-out-alt"></i><span>Logout</span></a>
  </div>
</aside>

<div class="main-content" id="mainContent">

  <header class="topbar">
    <button class="topbar-menu-btn" onclick="toggleSidebar()" id="menuBtn"><i class="fa fa-bars"></i></button>
    <div class="topbar-search">
      <i class="fa fa-search"></i>
      <input type="text" placeholder="Search tasks, expenses…" id="globalSearch" oninput="handleSearch(this.value)" autocomplete="off">
    </div>
    <div class="topbar-right">
      <button class="topbar-icon-btn" onclick="showTab('pomodoro')" title="Focus Timer"><i class="fa fa-clock"></i></button>
      <button class="topbar-icon-btn pos-rel" onclick="toggleNotifPanel()" title="Notifications" id="notifBtn">
        <i class="fa fa-bell"></i>
        <?php if($notifications->num_rows > 0): ?><span class="notif-dot"></span><?php endif; ?>
      </button>
      <button class="topbar-icon-btn" onclick="showTab('settings')" title="Settings"><i class="fa fa-gear"></i></button>
    </div>
  </header>

  <div class="notif-panel" id="notifPanel">
    <div class="notif-panel-header"><span>Notifications</span><button onclick="toggleNotifPanel()"><i class="fa fa-times"></i></button></div>
    <?php $notifications->data_seek(0); $hasN=false; while($n=$notifications->fetch_assoc()): $hasN=true; ?>
      <div class="notif-item"><i class="fa fa-circle-dot"></i><?= htmlspecialchars($n['message']) ?></div>
    <?php endwhile; if(!$hasN): ?>
      <div class="notif-item empty"><i class="fa fa-check-circle"></i> All caught up!</div>
    <?php endif; ?>
  </div>

  <div class="search-results" id="searchResults"></div>

  <!-- HOME -->
  <section id="tab-home" class="section">
    <div class="section-hero">
      <div>
        <div class="page-title"><?= $greeting ?>, <?= $firstName ?> 👋</div>
        <div class="page-sub">Your overview for <?= date('l, F j') ?></div>
      </div>
      <button class="btn-primary" onclick="openModal('modalQuickAdd')"><i class="fa fa-plus"></i> Quick Add</button>
    </div>

    <div class="stats-row">
      <div class="stat-card clickable" onclick="showTab('planner')">
        <div class="stat-icon-wrap" style="background:var(--accent);color:#fff"><i class="fa fa-list-check"></i></div>
        <div class="stat-body">
          <div class="stat-value counter" data-target="<?= $pendingCount ?>"><?= $pendingCount ?></div>
          <div class="stat-label">Pending Tasks</div>
        </div>
        <div class="stat-trend <?= $pendingCount>5?'trend-warn':'trend-ok' ?>"><?= $pendingCount>5?'⚠ High':'✓ OK' ?></div>
      </div>
      <div class="stat-card clickable" onclick="showTab('budget')">
        <div class="stat-icon-wrap" style="background:var(--green-bg);color:var(--green)"><i class="fa fa-wallet"></i></div>
        <div class="stat-body">
          <div class="stat-value">₹<?= number_format($budgetTotal,0) ?></div>
          <div class="stat-label">Total Spent</div>
        </div>
        <div class="stat-trend trend-neutral">All time</div>
      </div>
      <div class="stat-card clickable" onclick="showTab('review')">
        <div class="stat-icon-wrap" style="background:var(--blue-bg);color:var(--blue)"><i class="fa fa-book-open"></i></div>
        <div class="stat-body">
          <div class="stat-value"><?= number_format($studyTotal,1) ?>h</div>
          <div class="stat-label">Study Hours</div>
        </div>
        <div class="stat-trend trend-ok">Logged</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap" style="background:var(--amber-bg);color:var(--amber)"><i class="fa fa-trophy"></i></div>
        <div class="stat-body">
          <div class="stat-value counter" data-target="<?= $completionRate ?>"><?= $completionRate ?>%</div>
          <div class="stat-label">Task Completion</div>
        </div>
        <div class="stat-trend trend-neutral"><?= $totalTasks ?> total</div>
      </div>
    </div>

    <div class="home-grid">
      <div class="widget">
        <div class="widget-header">
          <div class="widget-title"><i class="fa fa-tasks"></i> Task Progress</div>
          <button class="widget-action" onclick="showTab('planner')">View all <i class="fa fa-arrow-right fa-xs"></i></button>
        </div>
        <div class="progress-ring-wrap">
          <svg class="progress-ring" viewBox="0 0 120 120">
            <circle cx="60" cy="60" r="50" fill="none" stroke="var(--border)" stroke-width="10"/>
            <circle cx="60" cy="60" r="50" fill="none" stroke="var(--accent)" stroke-width="10"
              stroke-dasharray="<?= round(314 * $completionRate / 100) ?> 314"
              stroke-linecap="round" transform="rotate(-90 60 60)" class="ring-progress"/>
          </svg>
          <div class="ring-label">
            <div class="ring-val"><?= $completionRate ?>%</div>
            <div class="ring-sub">Done</div>
          </div>
        </div>
        <div class="progress-legend">
          <div class="pleg"><span class="pleg-dot" style="background:var(--accent)"></span><?= $doneCount ?> completed</div>
          <div class="pleg"><span class="pleg-dot" style="background:var(--border)"></span><?= $pendingCount ?> pending</div>
        </div>
        <?php $tasks->data_seek(0); $shown=0; while($t=$tasks->fetch_assoc()): if($t['status']==='completed'||$shown>=3) continue; $shown++; ?>
        <div class="mini-task-row">
          <span class="priority-pip pip-<?= $t['priority'] ?>"></span>
          <span class="mini-task-title"><?= htmlspecialchars($t['title']) ?></span>
          <span class="mini-task-date"><?= $t['due_date']?date('M j',strtotime($t['due_date'])):'—' ?></span>
        </div>
        <?php endwhile; ?>
      </div>

      <div class="widget">
        <div class="widget-header">
          <div class="widget-title"><i class="fa fa-chart-pie"></i> Budget Snapshot</div>
          <button class="widget-action" onclick="showTab('budget')">Manage <i class="fa fa-arrow-right fa-xs"></i></button>
        </div>
        <?php if(!empty($catData)): ?>
          <div class="chart-wrap" style="height:160px"><canvas id="homePieChart"></canvas></div>
        <?php else: ?>
          <div class="empty-state"><i class="fa fa-receipt"></i><p>No expenses logged yet</p></div>
        <?php endif; ?>
        <div class="exp-total" style="margin-top:12px">
          <span>Total Tracked</span>
          <span class="exp-total-val">₹<?= number_format($budgetTotal,2) ?></span>
        </div>
      </div>

      <div class="widget">
        <div class="widget-header"><div class="widget-title"><i class="fa fa-pen-to-square"></i> Log Study Session</div></div>
        <form action="add_study.php" method="POST" class="task-form">
          <input type="hidden" name="user_id" value="<?= $userId ?>">
          <div class="form-row">
            <div class="fg"><label>Subject</label><input type="text" name="subject" placeholder="e.g. Mathematics" required></div>
            <div class="fg"><label>Hours</label><input type="number" name="hours" min="0.25" max="12" step="0.25" placeholder="2.5" required></div>
          </div>
          <div class="form-row">
            <div class="fg"><label>Date</label><input type="date" name="session_date" required></div>
            <div class="fg"><label>Notes</label><input type="text" name="notes" placeholder="Optional"></div>
          </div>
          <button type="submit" class="submit-btn"><i class="fa fa-plus"></i> Log Session</button>
        </form>
      </div>

      <div class="widget">
        <div class="widget-header"><div class="widget-title"><i class="fa fa-bolt"></i> Recent Activity</div></div>
        <div class="activity-feed">
          <?php
          $acts = $conn->query("
            (SELECT 'task' AS type, title, created_at FROM tasks WHERE user_id=$userId ORDER BY created_at DESC LIMIT 3)
            UNION ALL
            (SELECT 'expense' AS type, title, created_at FROM expenses WHERE user_id=$userId ORDER BY created_at DESC LIMIT 3)
            UNION ALL
            (SELECT 'study' AS type, subject AS title, created_at FROM study_sessions WHERE user_id=$userId ORDER BY created_at DESC LIMIT 2)
            ORDER BY created_at DESC LIMIT 6
          ");
          $iconMap  = ['task'=>'fa-list-check','expense'=>'fa-receipt','study'=>'fa-book-open'];
          $colorMap = ['task'=>'var(--blue)','expense'=>'var(--amber)','study'=>'var(--green)'];
          $labelMap = ['task'=>'Task added','expense'=>'Expense logged','study'=>'Study session'];
          if($acts && $acts->num_rows>0): while($a=$acts->fetch_assoc()): ?>
          <div class="act-row">
            <div class="act-icon" style="color:<?= $colorMap[$a['type']] ?>"><i class="fa <?= $iconMap[$a['type']] ?>"></i></div>
            <div class="act-info">
              <div class="act-title"><?= htmlspecialchars($a['title']) ?></div>
              <div class="act-time"><?= $labelMap[$a['type']] ?> · <?= date('M j', strtotime($a['created_at'])) ?></div>
            </div>
          </div>
          <?php endwhile; else: ?>
          <div class="empty-state"><i class="fa fa-history"></i><p>No activity yet. Start adding tasks!</p></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- PLANNER -->
  <section id="tab-planner" class="section" style="display:none">
    <div class="section-hero">
      <div>
        <div class="page-title"><i class="fa fa-list-check"></i> Planner</div>
        <div class="page-sub"><?= $pendingCount ?> pending · <?= $doneCount ?> completed</div>
      </div>
    </div>
    <div class="planner-grid">
      <div class="widget">
        <div class="widget-header"><div class="widget-title">Add New Task</div></div>
        <form action="add_task.php" method="POST" class="task-form">
          <input type="hidden" name="user_id" value="<?= $userId ?>">
          <div class="fg"><label>Title</label><input type="text" name="title" placeholder="What needs to be done?" required></div>
          <div class="fg"><label>Description</label><input type="text" name="description" placeholder="Optional details…"></div>
          <div class="form-row">
            <div class="fg"><label>Due Date</label><input type="date" name="due_date"></div>
            <div class="fg"><label>Priority</label>
              <select name="priority">
                <option value="low">🟢 Low</option>
                <option value="medium" selected>🟡 Medium</option>
                <option value="high">🔴 High</option>
              </select>
            </div>
          </div>
          <button type="submit" class="submit-btn"><i class="fa fa-plus"></i> Add Task</button>
        </form>
      </div>
      <div class="widget">
        <div class="widget-header">
          <div class="widget-title">All Tasks</div>
          <div style="display:flex;gap:6px">
            <button class="filter-btn active" onclick="filterTasks('all',this)">All</button>
            <button class="filter-btn" onclick="filterTasks('pending',this)">Pending</button>
            <button class="filter-btn" onclick="filterTasks('completed',this)">Done</button>
          </div>
        </div>
        <div class="task-list" id="taskList">
          <?php $tasks->data_seek(0); $hasT=false; while($t=$tasks->fetch_assoc()): $hasT=true; $done=$t['status']==='completed'; ?>
          <div class="task-row <?= $done?'done':'' ?>" data-status="<?= $t['status'] ?>">
            <div class="task-check <?= $done?'checked':'' ?>" onclick="toggleTaskAjax(<?= $t['task_id'] ?>,'<?= $t['status'] ?>',this)">
              <?= $done?'<i class="fa fa-check"></i>':'' ?>
            </div>
            <div class="task-info">
              <div class="task-title-t"><?= htmlspecialchars($t['title']) ?></div>
              <div class="task-meta-t">
                <?php if($t['due_date']): ?>
                  <span class="<?= (strtotime($t['due_date'])<time()&&!$done)?'overdue':'' ?>"><i class="fa fa-calendar-days"></i> <?= date('M j, Y',strtotime($t['due_date'])) ?></span> ·
                <?php endif; ?>
                <span class="priority-tag priority-<?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?></span>
              </div>
            </div>
          </div>
          <?php endwhile; if(!$hasT): ?>
          <div class="empty-state"><i class="fa fa-clipboard-check"></i><p>No tasks yet. Add one above!</p></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php if(count($chartLabels)>0): ?>
    <div class="widget" style="margin-top:16px">
      <div class="widget-header"><div class="widget-title"><i class="fa fa-chart-bar"></i> Study Hours by Subject</div></div>
      <div class="chart-wrap"><canvas id="studyChart"></canvas></div>
    </div>
    <?php endif; ?>
  </section>

  <!-- STATISTICS -->
  <section id="tab-review" class="section" style="display:none">
    <div class="section-hero">
      <div>
        <div class="page-title"><i class="fa fa-chart-line"></i> Statistics</div>
        <div class="page-sub">Your academic &amp; financial performance overview</div>
      </div>
    </div>
    <div class="rv-row">
      <div class="rv-card"><div class="rv-icon rv-dark"><i class="fa fa-list-check"></i></div><div class="rv-val"><?= $pendingCount ?></div><div class="rv-lbl">Pending Tasks</div></div>
      <div class="rv-card"><div class="rv-icon rv-blue"><i class="fa fa-book-open"></i></div><div class="rv-val"><?= number_format($studyTotal,1) ?>h</div><div class="rv-lbl">Study Hours</div></div>
      <div class="rv-card"><div class="rv-icon rv-green"><i class="fa fa-wallet"></i></div><div class="rv-val">₹<?= number_format($budgetTotal,0) ?></div><div class="rv-lbl">Total Spent</div></div>
      <div class="rv-card"><div class="rv-icon rv-amber"><i class="fa fa-trophy"></i></div><div class="rv-val"><?= $completionRate ?>%</div><div class="rv-lbl">Completion Rate</div></div>
    </div>
    <div class="review-grid">
      <div class="widget">
        <div class="widget-header"><div class="widget-title"><i class="fa fa-chart-area"></i> Weekly Study Hours</div></div>
        <div class="chart-wrap"><canvas id="reviewChart"></canvas></div>
      </div>
      <div class="widget">
        <div class="widget-header"><div class="widget-title"><i class="fa fa-chart-pie"></i> Expense Categories</div></div>
        <div class="chart-wrap"><canvas id="catChart"></canvas></div>
      </div>
    </div>
    <div class="review-grid" style="margin-top:16px">
      <div class="widget">
        <div class="widget-header"><div class="widget-title"><i class="fa fa-chart-column"></i> Monthly Spending</div></div>
        <div class="chart-wrap"><canvas id="monthChart"></canvas></div>
      </div>
      <div class="widget">
        <div class="widget-header"><div class="widget-title"><i class="fa fa-receipt"></i> Recent Expenses</div></div>
        <?php $expenses->data_seek(0); while($e=$expenses->fetch_assoc()): ?>
        <div class="exp-row">
          <div class="exp-icon"><?= getCatIcon($e['category']) ?></div>
          <div class="exp-info">
            <div class="exp-name"><?= htmlspecialchars($e['title']) ?></div>
            <div class="exp-cat"><?= htmlspecialchars($e['category']) ?> · <?= date('M j', strtotime($e['expense_date'])) ?></div>
          </div>
          <div class="exp-amt">₹<?= number_format($e['amount'],2) ?></div>
        </div>
        <?php endwhile; ?>
        <div class="exp-total"><span>Total</span><span class="exp-total-val">₹<?= number_format($budgetTotal,2) ?></span></div>
      </div>
    </div>
  </section>

  <!-- BUDGET -->
  <section id="tab-budget" class="section" style="display:none">
    <div class="section-hero">
      <div>
        <div class="page-title"><i class="fa fa-wallet"></i> Budget</div>
        <div class="page-sub">Track and manage your spending</div>
      </div>
    </div>
    <div class="planner-grid">
      <div class="widget">
        <div class="widget-header"><div class="widget-title">Add Expense</div></div>
        <form action="add_expense.php" method="POST" class="task-form">
          <input type="hidden" name="user_id" value="<?= $userId ?>">
          <div class="fg"><label>Title</label><input type="text" name="title" placeholder="What did you spend on?" required></div>
          <div class="form-row">
            <div class="fg"><label>Amount (₹)</label><input type="number" name="amount" placeholder="0.00" step="0.01" min="0" required></div>
            <div class="fg"><label>Date</label><input type="date" name="date" required></div>
          </div>
          <div class="fg"><label>Category</label>
            <select name="category">
              <option value="Food">🍔 Food</option>
              <option value="Travel">🚌 Travel</option>
              <option value="Books">📚 Books</option>
              <option value="Stationery">✏️ Stationery</option>
              <option value="Entertainment">🎮 Entertainment</option>
              <option value="Health">💊 Health</option>
              <option value="Clothing">👕 Clothing</option>
              <option value="Other">📦 Other</option>
            </select>
          </div>
          <button type="submit" class="submit-btn"><i class="fa fa-plus"></i> Log Expense</button>
        </form>
      </div>
      <div class="widget">
        <div class="widget-header">
          <div class="widget-title">Expense History</div>
          <div class="exp-total-val" style="font-size:.9rem">₹<?= number_format($budgetTotal,2) ?> total</div>
        </div>
        <?php $allExpenses->data_seek(0); $hasE=false; while($e=$allExpenses->fetch_assoc()): $hasE=true; ?>
        <div class="exp-row">
          <div class="exp-icon"><?= getCatIcon($e['category']) ?></div>
          <div class="exp-info">
            <div class="exp-name"><?= htmlspecialchars($e['title']) ?></div>
            <div class="exp-cat"><?= htmlspecialchars($e['category']) ?> · <?= date('M j, Y', strtotime($e['expense_date'])) ?></div>
          </div>
          <div class="exp-amt">₹<?= number_format($e['amount'],2) ?></div>
        </div>
        <?php endwhile; if(!$hasE): ?>
        <div class="empty-state"><i class="fa fa-receipt"></i><p>No expenses yet!</p></div>
        <?php endif; ?>
        <div class="exp-total"><span>Total Spent</span><span class="exp-total-val">₹<?= number_format($budgetTotal,2) ?></span></div>
      </div>
    </div>
    <?php if(!empty($catData)): ?>
    <div class="home-grid" style="margin-top:16px">
      <div class="widget">
        <div class="widget-header"><div class="widget-title"><i class="fa fa-chart-pie"></i> Spending by Category</div></div>
        <div class="chart-wrap"><canvas id="budgetPieChart"></canvas></div>
      </div>
      <div class="widget">
        <div class="widget-header"><div class="widget-title"><i class="fa fa-bars"></i> Category Breakdown</div></div>
        <?php foreach($catData as $cat=>$amt): $pct = $budgetTotal>0?round($amt/$budgetTotal*100):0; ?>
        <div class="cat-bar-row">
          <div class="cat-bar-label"><?= getCatIcon($cat) ?> <?= htmlspecialchars($cat) ?></div>
          <div class="cat-bar-track"><div class="cat-bar-fill" style="width:<?= $pct ?>%"></div></div>
          <div class="cat-bar-val">₹<?= number_format($amt,0) ?> <span class="cat-pct"><?= $pct ?>%</span></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </section>

  <!-- POMODORO -->
  <section id="tab-pomodoro" class="section" style="display:none">
    <div class="section-hero">
      <div>
        <div class="page-title"><i class="fa fa-clock"></i> Focus Timer</div>
        <div class="page-sub">Stay in flow. Work deeply.</div>
      </div>
    </div>
    <div class="pomodoro-layout">
      <div class="widget pomodoro-card">
        <div class="pom-mode-tabs">
          <button class="pom-tab active" onclick="setPomMode('work',this)">Focus</button>
          <button class="pom-tab" onclick="setPomMode('short',this)">Short Break</button>
          <button class="pom-tab" onclick="setPomMode('long',this)">Long Break</button>
        </div>
        <div class="pom-ring-wrap">
          <svg viewBox="0 0 200 200" class="pom-ring-svg">
            <circle cx="100" cy="100" r="88" fill="none" stroke="var(--border)" stroke-width="8"/>
            <circle cx="100" cy="100" r="88" fill="none" stroke="var(--accent)" stroke-width="8"
              stroke-dasharray="553" stroke-dashoffset="0"
              stroke-linecap="round" transform="rotate(-90 100 100)" id="pomRingCircle"/>
          </svg>
          <div class="pom-time" id="pomTime">25:00</div>
        </div>
        <div class="pom-subject-row">
          <input type="text" class="pom-subject-input" id="pomSubject" placeholder="What are you focusing on?">
        </div>
        <div class="pom-controls">
          <button class="pom-btn-secondary" onclick="resetTimer()"><i class="fa fa-rotate-left"></i></button>
          <button class="pom-btn-primary" id="pomStartBtn" onclick="toggleTimer()"><i class="fa fa-play"></i> Start</button>
          <button class="pom-btn-secondary" onclick="skipTimer()"><i class="fa fa-forward-step"></i></button>
        </div>
        <div class="pom-sessions-row" id="pomSessionDots">🍅 Session 1</div>
      </div>
      <div class="widget pomodoro-info">
        <div class="widget-header"><div class="widget-title">Today's Focus Stats</div></div>
        <div class="pom-stats-grid">
          <div class="pom-stat"><div class="pom-stat-val" id="pomTodaySessions">0</div><div class="pom-stat-lbl">Sessions</div></div>
          <div class="pom-stat"><div class="pom-stat-val" id="pomTodayMins">0m</div><div class="pom-stat-lbl">Focused</div></div>
          <div class="pom-stat"><div class="pom-stat-val" id="pomStreak">0</div><div class="pom-stat-lbl">Streak</div></div>
        </div>
        <div class="widget-header" style="margin-top:20px"><div class="widget-title">How Pomodoro Works</div></div>
        <div class="pom-tip-list">
          <div class="pom-tip"><span class="pom-tip-num">1</span>Work for 25 minutes with full focus</div>
          <div class="pom-tip"><span class="pom-tip-num">2</span>Take a 5-minute short break</div>
          <div class="pom-tip"><span class="pom-tip-num">3</span>Every 4 sessions, take a 15-min break</div>
          <div class="pom-tip"><span class="pom-tip-num">4</span>Repeat until your task is done</div>
        </div>
      </div>
    </div>
  </section>

  <!-- ACCOUNT -->
  <section id="tab-account" class="section" style="display:none">
    <div class="page-title"><i class="fa fa-user-circle"></i> Account</div>
    <div class="account-grid">
      <div class="widget profile-card">
        <div class="profile-avatar-lg"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
        <div class="profile-name"><?= htmlspecialchars($user['full_name']) ?></div>
        <div class="profile-course"><?= htmlspecialchars($user['course']?:'Course not set') ?></div>
        <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
        <span class="profile-badge">Semester <?= $user['semester'] ?></span>
        <div class="profile-joined">Joined <?= date('M Y', strtotime($user['created_at'])) ?></div>
      </div>
      <div class="widget">
        <div class="widget-header"><div class="widget-title">Profile Details</div></div>
        <form action="update_profile.php" method="POST" class="task-form">
          <div class="fg"><label>Full Name</label><input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>"></div>
          <div class="fg"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>"></div>
          <div class="form-row">
            <div class="fg"><label>Course</label><input type="text" name="course" value="<?= htmlspecialchars($user['course']) ?>" placeholder="B.Tech CSE"></div>
            <div class="fg"><label>Semester</label><input type="number" name="semester" min="1" max="10" value="<?= $user['semester'] ?>"></div>
          </div>
          <button type="submit" class="submit-btn"><i class="fa fa-save"></i> Save Changes</button>
        </form>
        <a href="logout.php" class="logout-link"><i class="fa fa-sign-out-alt"></i> Logout</a>
      </div>
      <div class="widget">
        <div class="widget-header"><div class="widget-title">My Stats</div></div>
        <div class="acct-stats">
          <div class="acct-stat"><div class="acct-stat-val"><?= $totalTasks ?></div><div class="acct-stat-lbl">Total Tasks</div></div>
          <div class="acct-stat"><div class="acct-stat-val"><?= $doneCount ?></div><div class="acct-stat-lbl">Completed</div></div>
          <div class="acct-stat"><div class="acct-stat-val"><?= number_format($studyTotal,1) ?>h</div><div class="acct-stat-lbl">Study Hours</div></div>
          <div class="acct-stat"><div class="acct-stat-val">₹<?= number_format($budgetTotal,0) ?></div><div class="acct-stat-lbl">Total Spent</div></div>
          <div class="acct-stat" style="grid-column:span 2"><div class="acct-stat-val"><?= $completionRate ?>%</div><div class="acct-stat-lbl">Completion Rate</div></div>
        </div>
      </div>
    </div>
  </section>

  <!-- SETTINGS -->
  <section id="tab-settings" class="section" style="display:none">
    <div class="section-hero">
      <div>
        <div class="page-title"><i class="fa fa-gear"></i> Settings</div>
        <div class="page-sub">Customize your UniSync experience</div>
      </div>
    </div>
    <div class="settings-grid">
      <div class="widget">
        <div class="widget-header"><div class="widget-title"><i class="fa fa-palette"></i> Appearance</div></div>
        <div class="setting-row">
          <div class="setting-info"><div class="setting-label">Dark Mode</div><div class="setting-desc">Switch to a dark color scheme</div></div>
          <label class="toggle-switch"><input type="checkbox" id="darkModeToggle" onchange="toggleDarkMode(this.checked)"><span class="toggle-track"></span></label>
        </div>
        <div class="setting-row">
          <div class="setting-info"><div class="setting-label">Compact View</div><div class="setting-desc">Reduce spacing in lists</div></div>
          <label class="toggle-switch"><input type="checkbox" id="compactToggle" onchange="toggleCompact(this.checked)"><span class="toggle-track"></span></label>
        </div>
        <div class="setting-row">
          <div class="setting-info"><div class="setting-label">Accent Color</div><div class="setting-desc">Choose your theme color</div></div>
          <div class="color-swatches">
            <button class="swatch active" style="background:#111111" onclick="setAccent('#111111',this)" title="Default"></button>
            <button class="swatch" style="background:#1d4ed8" onclick="setAccent('#1d4ed8',this)" title="Blue"></button>
            <button class="swatch" style="background:#16a34a" onclick="setAccent('#16a34a',this)" title="Green"></button>
            <button class="swatch" style="background:#9333ea" onclick="setAccent('#9333ea',this)" title="Purple"></button>
            <button class="swatch" style="background:#dc2626" onclick="setAccent('#dc2626',this)" title="Red"></button>
            <button class="swatch" style="background:#ea580c" onclick="setAccent('#ea580c',this)" title="Orange"></button>
          </div>
        </div>
      </div>
      <div class="widget">
        <div class="widget-header"><div class="widget-title"><i class="fa fa-bell"></i> Notifications</div></div>
        <div class="setting-row">
          <div class="setting-info"><div class="setting-label">Due Date Reminders</div><div class="setting-desc">Alert 1 day before task due date</div></div>
          <label class="toggle-switch"><input type="checkbox" checked id="notifTasks"><span class="toggle-track"></span></label>
        </div>
        <div class="setting-row">
          <div class="setting-info"><div class="setting-label">Budget Alerts</div><div class="setting-desc">Notify on high spending days</div></div>
          <label class="toggle-switch"><input type="checkbox" checked id="notifBudget"><span class="toggle-track"></span></label>
        </div>
        <div class="setting-row">
          <div class="setting-info"><div class="setting-label">Pomodoro Sounds</div><div class="setting-desc">Play sound when timer ends</div></div>
          <label class="toggle-switch"><input type="checkbox" checked id="notifPomodoro"><span class="toggle-track"></span></label>
        </div>
      </div>
      <div class="widget">
        <div class="widget-header"><div class="widget-title"><i class="fa fa-clock"></i> Timer Settings</div></div>
        <div class="setting-row">
          <div class="setting-info"><div class="setting-label">Focus Duration</div><div class="setting-desc">Minutes per work session</div></div>
          <input type="number" class="setting-input" id="setPomWork" min="1" max="90" value="25" onchange="updatePomSettings()">
        </div>
        <div class="setting-row">
          <div class="setting-info"><div class="setting-label">Short Break</div><div class="setting-desc">Minutes for short break</div></div>
          <input type="number" class="setting-input" id="setPomShort" min="1" max="30" value="5" onchange="updatePomSettings()">
        </div>
        <div class="setting-row">
          <div class="setting-info"><div class="setting-label">Long Break</div><div class="setting-desc">Minutes for long break</div></div>
          <input type="number" class="setting-input" id="setPomLong" min="1" max="60" value="15" onchange="updatePomSettings()">
        </div>
      </div>
      <div class="widget">
        <div class="widget-header"><div class="widget-title"><i class="fa fa-shield-halved"></i> Data &amp; Account</div></div>
        <div class="setting-row">
          <div class="setting-info"><div class="setting-label">Export Tasks</div><div class="setting-desc">Download your tasks as CSV</div></div>
          <button class="setting-action-btn" onclick="exportData()"><i class="fa fa-download"></i> Export</button>
        </div>
        <div class="setting-row">
          <div class="setting-info"><div class="setting-label">Change Password</div><div class="setting-desc">Update your login password</div></div>
          <button class="setting-action-btn" onclick="openModal('modalPassword')"><i class="fa fa-key"></i> Change</button>
        </div>
        <div class="setting-row">
          <div class="setting-info"><div class="setting-label" style="color:var(--red)">Danger Zone</div><div class="setting-desc">Permanently delete all your data</div></div>
          <button class="setting-action-btn danger" onclick="if(confirm('Delete ALL data? Cannot be undone.'))alert('Coming soon.')"><i class="fa fa-trash"></i> Delete</button>
        </div>
      </div>
    </div>
  </section>

  <!-- MODALS -->
  <div id="modalQuickAdd" class="modal" onclick="if(event.target===this)closeModalById('modalQuickAdd')">
    <div class="modal-content">
      <button class="close-modal" onclick="closeModalById('modalQuickAdd')"><i class="fa fa-times"></i></button>
      <h2><i class="fa fa-plus"></i> Quick Add</h2>
      <div class="modal-tabs">
        <button class="modal-tab active" onclick="switchModalTab('qtask',this)">Task</button>
        <button class="modal-tab" onclick="switchModalTab('qexpense',this)">Expense</button>
      </div>
      <form action="add_task.php" method="POST" class="task-form" id="qtask">
        <input type="hidden" name="user_id" value="<?= $userId ?>">
        <div class="fg"><label>Task Title</label><input type="text" name="title" placeholder="e.g. Submit assignment" required></div>
        <div class="form-row">
          <div class="fg"><label>Due Date</label><input type="date" name="due_date"></div>
          <div class="fg"><label>Priority</label><select name="priority"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option></select></div>
        </div>
        <button type="submit" class="submit-btn">Add Task</button>
      </form>
      <form action="add_expense.php" method="POST" class="task-form" id="qexpense" style="display:none">
        <input type="hidden" name="user_id" value="<?= $userId ?>">
        <div class="fg"><label>Description</label><input type="text" name="title" placeholder="e.g. Lunch" required></div>
        <div class="form-row">
          <div class="fg"><label>Amount (₹)</label><input type="number" name="amount" step="0.01" placeholder="0.00" required></div>
          <div class="fg"><label>Date</label><input type="date" name="date" required></div>
        </div>
        <div class="fg"><label>Category</label><select name="category"><option>Food</option><option>Travel</option><option>Books</option><option>Other</option></select></div>
        <button type="submit" class="submit-btn">Log Expense</button>
      </form>
    </div>
  </div>

  <div id="modalPassword" class="modal" onclick="if(event.target===this)closeModalById('modalPassword')">
    <div class="modal-content">
      <button class="close-modal" onclick="closeModalById('modalPassword')"><i class="fa fa-times"></i></button>
      <h2><i class="fa fa-key"></i> Change Password</h2>
      <form action="change_password.php" method="POST" class="task-form">
        <div class="fg"><label>Current Password</label><input type="password" name="old_password" required></div>
        <div class="fg"><label>New Password</label><input type="password" name="new_password" required></div>
        <div class="fg"><label>Confirm New Password</label><input type="password" name="confirm_password" required></div>
        <button type="submit" class="submit-btn">Update Password</button>
      </form>
    </div>
  </div>

  <nav class="bottom-nav">
    <button class="bnav-btn active" onclick="showTab('home')"><i class="fa fa-home"></i><span>Home</span></button>
    <button class="bnav-btn" onclick="showTab('planner')"><i class="fa fa-list-check"></i><span>Planner</span></button>
    <button class="bnav-btn" onclick="showTab('review')"><i class="fa fa-chart-line"></i><span>Stats</span></button>
    <button class="bnav-btn" onclick="showTab('budget')"><i class="fa fa-wallet"></i><span>Budget</span></button>
    <button class="bnav-btn" onclick="showTab('account')"><i class="fa fa-user"></i><span>Account</span></button>
  </nav>
</div>

<script>
var _chartLabels = <?= json_encode($chartLabels) ?>;
var _chartHours  = <?= json_encode($chartHours) ?>;
var _weekHours   = <?= json_encode(array_values($weekHours)) ?>;
var _catKeys     = <?= json_encode(array_keys($catData)) ?>;
var _catVals     = <?= json_encode(array_values($catData)) ?>;
var _monthKeys   = <?= json_encode(array_keys($monthData)) ?>;
var _monthVals   = <?= json_encode(array_values($monthData)) ?>;

var charts = {};
var TABS = ['home','planner','review','budget','pomodoro','account','settings'];
var PALETTE = ['#111111','#4a90d9','#2db57a','#f5a623','#e74c3c','#9b59b6','#1abc9c','#e67e22'];

function showTab(name) {
  document.querySelectorAll('.section').forEach(s => s.style.display = 'none');
  document.querySelectorAll('.nav-item, .bnav-btn').forEach(b => b.classList.remove('active'));
  var el = document.getElementById('tab-'+name);
  if (el) { el.style.display='block'; requestAnimationFrame(()=>el.classList.add('tab-enter')); setTimeout(()=>el.classList.remove('tab-enter'),400); }
  document.querySelectorAll('.nav-item[data-tab="'+name+'"]').forEach(b=>b.classList.add('active'));
  var bIdx = ['home','planner','review','budget','account'].indexOf(name);
  var bBtns = document.querySelectorAll('.bnav-btn');
  if (bIdx>=0 && bBtns[bIdx]) bBtns[bIdx].classList.add('active');
  if (name==='planner' && _chartLabels.length) renderChart('studyChart','bar',_chartLabels,_chartHours,{backgroundColor:'var(--accent)',borderRadius:8});
  if (name==='review') renderReviewCharts();
  if (name==='budget') { if(_catKeys.length) renderChart('budgetPieChart','doughnut',_catKeys,_catVals,{borderWidth:2,borderColor:'var(--surface)'}); }
  if (name==='home') { animateCounters(); if(_catKeys.length) renderChart('homePieChart','doughnut',_catKeys,_catVals,{borderWidth:2,borderColor:'var(--surface)'}); }
  closeNotifPanel();
  history.replaceState(null,'','#'+name);
}

function renderChart(id, type, labels, data, extra) {
  if (charts[id]) charts[id].destroy();
  var ctx = document.getElementById(id); if(!ctx) return;
  var ds = Object.assign({label:'Value',data:data,
    backgroundColor: type==='line'?'rgba(17,17,17,0.07)':PALETTE,
    borderColor: type==='line'?'var(--accent)':PALETTE,
    borderWidth: type==='line'?2:0,
    tension:0.4, fill:type==='line', pointBackgroundColor:'var(--accent)', pointRadius:4,
    borderRadius: type==='bar'?7:0
  }, extra||{});
  charts[id] = new Chart(ctx, { type:type, data:{labels:labels,datasets:[ds]},
    options:{ responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{ display:type==='doughnut', position:'right', labels:{font:{family:'DM Sans',size:12},boxWidth:12,padding:14,color:'#4a4a4a'} } },
      scales: type!=='doughnut'?{ y:{beginAtZero:true,grid:{color:'#e8e7e0'},ticks:{color:'#9a9a8a',font:{family:'DM Sans'}}}, x:{grid:{display:false},ticks:{color:'#9a9a8a',font:{family:'DM Sans'}}} }:{}
    }
  });
}

function renderReviewCharts() {
  renderChart('reviewChart','line',['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],_weekHours);
  if (_catKeys.length) renderChart('catChart','doughnut',_catKeys,_catVals,{borderWidth:2,borderColor:'var(--surface)'});
  if (_monthKeys.length) renderChart('monthChart','bar',_monthKeys,_monthVals,{backgroundColor:'var(--accent)',borderRadius:8});
}

function animateCounters() {
  document.querySelectorAll('.counter[data-target]').forEach(el => {
    var target = parseFloat(el.dataset.target)||0, start=0, dur=800;
    var inc = target/(dur/16);
    var id = setInterval(()=>{ start+=inc; if(start>=target){el.textContent=el.dataset.target;clearInterval(id);}else el.textContent=Math.floor(start); },16);
  });
}

function toggleTaskAjax(id, status, el) {
  var row = el.closest('.task-row');
  row.style.opacity = '0.5';
  fetch('toggle_task.php?id='+id+'&status='+status+'&ajax=1')
    .then(r=>r.json()).then(d=>{
      row.style.opacity='1';
      if(d.ok){
        var ns = status==='completed'?'pending':'completed';
        row.dataset.status=ns;
        if(ns==='completed'){row.classList.add('done');el.classList.add('checked');el.innerHTML='<i class="fa fa-check"></i>';}
        else{row.classList.remove('done');el.classList.remove('checked');el.innerHTML='';}
      }
    }).catch(()=>{ row.style.opacity='1'; location.href='toggle_task.php?id='+id+'&status='+status; });
}

function filterTasks(filter, btn) {
  document.querySelectorAll('.filter-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.task-row').forEach(row=>{
    row.style.display = (filter==='all'||row.dataset.status===filter)?'flex':'none';
  });
}

function handleSearch(q) {
  var panel = document.getElementById('searchResults');
  if (!q.trim()) { panel.style.display='none'; return; }
  var results=[];
  document.querySelectorAll('.task-title-t').forEach(el=>{
    if(el.textContent.toLowerCase().includes(q.toLowerCase()))
      results.push({type:'Task',text:el.textContent,tab:'planner'});
  });
  document.querySelectorAll('.exp-name').forEach(el=>{
    if(el.textContent.toLowerCase().includes(q.toLowerCase()))
      results.push({type:'Expense',text:el.textContent,tab:'budget'});
  });
  panel.innerHTML = results.length ? results.map(r=>
    '<div class="sr-item" onclick="showTab(\''+r.tab+'\');document.getElementById(\'globalSearch\').value=\'\';document.getElementById(\'searchResults\').style.display=\'none\'">'+
    '<span class="sr-type">'+r.type+'</span>'+r.text+'</div>'
  ).join('') : '<div class="sr-item empty">No results for "'+q+'"</div>';
  panel.style.display='block';
}

document.addEventListener('click', e=>{
  if(!e.target.closest('#globalSearch')&&!e.target.closest('#searchResults'))
    document.getElementById('searchResults').style.display='none';
});

function toggleNotifPanel(){document.getElementById('notifPanel').classList.toggle('open');}
function closeNotifPanel(){document.getElementById('notifPanel').classList.remove('open');}
document.addEventListener('click',e=>{if(!e.target.closest('#notifPanel')&&!e.target.closest('#notifBtn'))closeNotifPanel();});

function openModal(id){var m=document.getElementById(id);if(m){m.style.display='flex';document.body.style.overflow='hidden';}}
function closeModalById(id){var m=document.getElementById(id);if(m){m.style.display='none';document.body.style.overflow='';}}
function switchModalTab(panel,btn){
  document.querySelectorAll('.modal-tab').forEach(b=>b.classList.remove('active')); btn.classList.add('active');
  document.querySelectorAll('#modalQuickAdd .task-form').forEach(p=>p.style.display='none');
  var el=document.getElementById(panel); if(el) el.style.display='flex';
}
document.addEventListener('keydown',e=>{if(e.key==='Escape'){document.querySelectorAll('.modal').forEach(m=>m.style.display='none');document.body.style.overflow='';}});

// POMODORO
var pomModes={work:25,short:5,long:15}, pomCurrentMode='work';
var pomSecondsLeft=1500, pomTotalSeconds=1500, pomInterval=null, pomRunning=false;
var pomSessionCount=0, pomTodaySessions=0, pomTodayMins=0;

function setPomMode(mode,btn){document.querySelectorAll('.pom-tab').forEach(b=>b.classList.remove('active'));btn.classList.add('active');pomCurrentMode=mode;resetTimer();}
function updatePomSettings(){pomModes.work=parseInt(document.getElementById('setPomWork').value)||25;pomModes.short=parseInt(document.getElementById('setPomShort').value)||5;pomModes.long=parseInt(document.getElementById('setPomLong').value)||15;if(!pomRunning)resetTimer();}
function updatePomDisplay(){
  var m=Math.floor(pomSecondsLeft/60),s=pomSecondsLeft%60;
  document.getElementById('pomTime').textContent=String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
  var progress=1-(pomSecondsLeft/pomTotalSeconds);
  document.getElementById('pomRingCircle').style.strokeDashoffset=553*progress;
  document.title=(pomRunning?String(m).padStart(2,'0')+':'+String(s).padStart(2,'0')+' · ':'')+'UniSync';
}
function toggleTimer(){
  if(pomRunning){clearInterval(pomInterval);pomRunning=false;document.getElementById('pomStartBtn').innerHTML='<i class="fa fa-play"></i> Resume';}
  else{pomRunning=true;document.getElementById('pomStartBtn').innerHTML='<i class="fa fa-pause"></i> Pause';
    pomInterval=setInterval(()=>{pomSecondsLeft--;updatePomDisplay();if(pomSecondsLeft<=0){clearInterval(pomInterval);pomRunning=false;timerComplete();}},1000);}
}
function timerComplete(){
  document.getElementById('pomStartBtn').innerHTML='<i class="fa fa-play"></i> Start';
  if(pomCurrentMode==='work'){
    pomSessionCount++;pomTodaySessions++;pomTodayMins+=pomModes.work;
    document.getElementById('pomTodaySessions').textContent=pomTodaySessions;
    document.getElementById('pomTodayMins').textContent=pomTodayMins+'m';
    document.getElementById('pomStreak').textContent=pomSessionCount;
    document.getElementById('pomSessionDots').textContent='🍅 Session '+(pomSessionCount+1);
    if(document.getElementById('notifPomodoro')&&document.getElementById('notifPomodoro').checked) playBeep();
    if(pomSessionCount%4===0) setPomMode('long',document.querySelector('.pom-tab:nth-child(3)'));
    else setPomMode('short',document.querySelector('.pom-tab:nth-child(2)'));
  } else { setPomMode('work',document.querySelector('.pom-tab:nth-child(1)')); }
}
function resetTimer(){clearInterval(pomInterval);pomRunning=false;pomSecondsLeft=pomModes[pomCurrentMode]*60;pomTotalSeconds=pomSecondsLeft;updatePomDisplay();document.getElementById('pomStartBtn').innerHTML='<i class="fa fa-play"></i> Start';}
function skipTimer(){pomSecondsLeft=0;updatePomDisplay();timerComplete();}
function playBeep(){try{var c=new(window.AudioContext||window.webkitAudioContext)();var o=c.createOscillator();var g=c.createGain();o.connect(g);g.connect(c.destination);o.frequency.value=880;g.gain.setValueAtTime(0.3,c.currentTime);g.gain.exponentialRampToValueAtTime(0.001,c.currentTime+0.8);o.start();o.stop(c.currentTime+0.8);}catch(e){}}

// SETTINGS
function toggleDarkMode(on){document.body.classList.toggle('dark-mode',on);localStorage.setItem('darkMode',on?'1':'0');}
function toggleCompact(on){document.body.classList.toggle('compact',on);localStorage.setItem('compact',on?'1':'0');}
function setAccent(color,btn){
  document.documentElement.style.setProperty('--accent',color);
  document.documentElement.style.setProperty('--accent-light',color+'18');
  document.querySelectorAll('.swatch').forEach(s=>s.classList.remove('active')); btn.classList.add('active');
  localStorage.setItem('accentColor',color);
  // Redraw charts with new color
  Object.keys(charts).forEach(k=>{ if(charts[k]) charts[k].destroy(); delete charts[k]; });
}
function exportData(){
  var rows=['"Title","Status","Priority"'];
  document.querySelectorAll('.task-row').forEach(r=>{
    var t=r.querySelector('.task-title-t'),pri=r.querySelector('.priority-tag');
    if(t) rows.push('"'+t.textContent.replace(/"/g,'""')+'","'+r.dataset.status+'",'+(pri?'"'+pri.textContent+'"':'""'));
  });
  var a=document.createElement('a');a.href='data:text/csv;charset=utf-8,'+encodeURIComponent(rows.join('\n'));a.download='unisync-tasks.csv';a.click();
}
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('mobile-open');}
document.addEventListener('click',e=>{if(!e.target.closest('#sidebar')&&!e.target.closest('#menuBtn'))document.getElementById('sidebar').classList.remove('mobile-open');});

document.addEventListener('DOMContentLoaded',()=>{
  if(localStorage.getItem('darkMode')==='1'){document.body.classList.add('dark-mode');document.getElementById('darkModeToggle').checked=true;}
  if(localStorage.getItem('compact')==='1'){document.body.classList.add('compact');document.getElementById('compactToggle').checked=true;}
  var acc=localStorage.getItem('accentColor');
  if(acc){document.documentElement.style.setProperty('--accent',acc);document.documentElement.style.setProperty('--accent-light',acc+'18');}
  var today=new Date().toISOString().split('T')[0];
  document.querySelectorAll('input[type="date"]').forEach(el=>{if(!el.value)el.value=today;});
  var hash=location.hash.replace('#','');
  showTab(TABS.includes(hash)?hash:'home');
  updatePomDisplay();
});
</script>
</body>
</html>
