<?php $appLayout = $appLayout ?? false; ?>
</main>
<footer class="<?= $appLayout ? 'app-footer' : 'site-footer' ?>">
  <div class="<?= $appLayout ? 'app-footer-inner' : 'container-xl' ?>">
    <span><strong><?= e(app_name()) ?></strong></span>
    <span>Document requests and community concern reporting</span>
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
