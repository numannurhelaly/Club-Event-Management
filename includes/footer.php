  </main>
  <?php foreach (($extraScripts ?? []) as $src): ?>
    <script src="<?= e($src) ?>" defer></script>
  <?php endforeach; ?>
</body>
</html>
