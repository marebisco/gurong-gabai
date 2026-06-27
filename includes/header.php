<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page_title ?? 'Gurong GabAI' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/gurong-gabai/assets/css/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>
  (function() {
    const theme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', theme);
  })();
  function toggleDark() {
    const html = document.documentElement;
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
    const lbl = document.getElementById('theme-label');
    if (lbl) lbl.textContent = next === 'dark' ? 'Light Mode' : 'Dark Mode';
  }
  document.addEventListener('DOMContentLoaded', function() {
    const lbl = document.getElementById('theme-label');
    if (lbl) lbl.textContent = (localStorage.getItem('theme')||'light') === 'dark' ? 'Light Mode' : 'Dark Mode';
  });
</script>
</head>
<body>
