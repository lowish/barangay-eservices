<?php $appLayout = $appLayout ?? false; ?>
</main>
<footer class="<?= $appLayout ? 'app-footer' : 'site-footer' ?>">
  <div class="<?= $appLayout ? 'app-footer-inner' : 'container-xl' ?>">
    <span><strong><?= e(barangay_name()) ?></strong>, <?= e(barangay_location()) ?></span>
    <span>Barangay hall office hours: <?= e(barangay()['office_hours']) ?></span>
  </div>
</footer>
<?php if ($appLayout): ?>
  </div><!-- /.app-main -->
</div><!-- /.app-shell -->
<?php endif; ?>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
