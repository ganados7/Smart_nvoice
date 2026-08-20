<?php /* close layout, flash toasts, modal, scripts */ ?>
        </main>
    </div>
</div>

<div class="toast-wrap">
    <?php foreach (get_flash() as $f): ?>
        <div class="toast <?= $f['type'] === 'error' ? 'error' : 'success' ?>">
            <span><?= e($f['message']) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<div class="modal-backdrop">
    <div class="modal">
        <h3>Confirm action</h3>
        <p></p>
        <div class="modal-actions">
            <button class="btn btn-ghost" data-confirm-cancel>Cancel</button>
            <button class="btn btn-primary" data-confirm-ok>Confirm</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>