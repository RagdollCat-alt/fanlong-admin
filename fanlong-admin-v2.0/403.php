<?php if (!defined('APP_NAME')) { require_once 'config.php'; checkLogin(); } ?>
<div class="d-flex flex-column align-items-center justify-content-center py-5 my-5 text-center">
  <div style="font-size:5rem;line-height:1;">🔒</div>
  <h2 class="fw-bold mt-4 mb-2">权限不足</h2>
  <p class="text-muted mb-4">您没有执行此操作的权限，请联系超级管理员。</p>
  <a href="index.php" class="btn btn-primary px-4">
    <i class="fas fa-house me-2"></i>返回仪表盘
  </a>
</div>
