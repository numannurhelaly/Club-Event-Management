  </main>
  <footer class="site-foot">Event desk for the AIUB Performing Arts Club</footer>
  <?php foreach (($extraScripts ?? []) as $src): ?>
    <script src="<?= e($src) ?>" defer></script>
  <?php endforeach; ?>
</body>
</html>
