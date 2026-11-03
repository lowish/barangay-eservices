<?php
// Generic error page. Never shows SQL, file paths or credentials unless debug mode is on.
$base = function_exists('base_path') ? base_path() : '';
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Something went wrong</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/app.css">
</head>
<body>
<main class="container-xl page">
  <div class="page-head"><h1>Something went wrong</h1></div>
  <p>The system could not complete your request. Please try again in a moment. If the problem continues, contact the barangay hall.</p>
  <?php if (!empty($detail)): ?>
    <pre class="small text-danger">Debug: <?= htmlspecialchars($detail) ?></pre>
  <?php endif; ?>
  <p><a class="btn btn-primary" href="<?= htmlspecialchars($base) ?>/index.php">Go to home page</a></p>
</main>
</body>
</html>
