<?php
// Opening layout for the login and register pages. Set $pageTitle first.
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#1D1646">
  <title><?= e($pageTitle ?? 'Welcome') ?> | AIUB Arts Club</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=Young+Serif&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth">
<div class="auth-wrap">
  <aside class="auth-stage">
    <a class="auth-brand" href="login.php">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#FFB627" d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a3 3 0 0 0 0 6v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a3 3 0 0 0 0-6z"/></svg>
      AIUB Arts Club
    </a>
    <div class="auth-copy">
      <h1>Find a show.<br>Take a seat.</h1>
      <p>See what the AIUB Performing Arts Club has coming up and reserve your place in a few clicks.</p>
    </div>
    <div class="auth-ticket" aria-hidden="true">
      <article class="ticket is-registered">
        <div class="ticket-main">
          <div class="ticket-top"><span class="badge">Opening night</span><span class="stamp">You're in</span></div>
          <h3 class="ticket-title"><a>Your seat is waiting</a></h3>
          <p class="ticket-meta">Reserved for one guest</p>
        </div>
        <div class="ticket-stub">
          <span class="stub-dow">Admit</span><span class="stub-day">1</span><span class="stub-month">seat</span>
        </div>
      </article>
    </div>
  </aside>
  <main class="auth-panel"><div class="auth-form">
