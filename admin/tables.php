<?php
require_once 'config.php';
checkLogin();

$db = getDB();

// 获取所有表
$stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取表详细信息
$table_details = [];
foreach ($tables as $table) {
    $table_name = $table['name'];
    
    // 获取表结构
    $stmt = $db->query("PRAGMA table_info(`$table_name`)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 获取行数
    $stmt = $db->query("SELECT COUNT(*) as count FROM `$table_name`");
    $row_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // 获取表大小（估算）
    $stmt = $db->query("SELECT SUM(pgsize) as size FROM dbstat WHERE name = ?");
    $stmt->execute([$table_name]);
    $size_info = $stmt->fetch(PDO::FETCH_ASSOC);
    $table_size = $size_info['size'] ?? 0;
    
    $table_details[] = [
        'name' => $table_name,
        'columns' => $columns,
        'row_count' => $row_count,
        'size' => $table_size
    ];
}

$page_title = '所有数据表';
$page_icon = 'fas fa-database';
$page_subtitle = '数据库结构概览';
require_once 'header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-database me-2"></i>数据库表清单
        </div>
        <div>
            <span class="badge bg-info"><?php echo count($tables); ?> 个表</span>
            <a href="tables.php" class="btn btn-outline-secondary btn-sm ms-2">
                <i class="fas fa-sync-alt me-1"></i>刷新
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($tables)): ?>
        <div class="text-center text-muted py-5">
            <i class="fas fa-database fa-3x mb-3 d-block"></i>
            <p class="fs-5">数据库中没有用户表</p>
        </div>
        <?php else: ?>
        <div class="accordion" id="tablesAccordion">
            <?php foreach ($table_details as $index => $table): ?>
            <div class="accordion-item mb-3">
                <h2 class="accordion-header">
                    <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button" 
                            data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>"
                            aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" 
                            aria-controls="collapse<?php echo $index; ?>">
                        <div class="d-flex justify-content-between align-items-center w-100 pe-3">
                            <div>
                                <strong class="me-3"><?php echo htmlspecialchars($table['name']); ?></strong>
                                <span class="badge bg-primary"><?php echo $table['row_count']; ?> 行</span>
                                <?php if ($table['size'] > 0): ?>
                                <span class="badge bg-secondary ms-1"><?php echo round($table['size'] / 1024, 2); ?> KB</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <span class="text-muted small">
                                    <?php echo count($table['columns']); ?> 个字段
                                </span>
                            </div>
                        </div>
                    </button>
                </h2>
                <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" 
                     data-bs-parent="#tablesAccordion">
                    <div class="accordion-body p-0">
                        <!-- 表结构 -->
                        <div class="p-3 border-bottom">
                            <h6 class="mb-3">
                                <i class="fas fa-table me-2"></i>表结构
                                <small class="text-muted ms-2"><?php echo htmlspecialchars($table['name']); ?></small>
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th width="5%">#</th>
                                            <th width="20%">字段名</th>
                                            <th width="15%">数据类型</th>
                                            <th width="10%">允许NULL</th>
                                            <th width="15%">默认值</th>
                                            <th width="10%">主键</th>
                                            <th width="25%">说明</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($table['columns'] as $col_index => $column): ?>
                                        <tr>
                                            <td><?php echo $col_index + 1; ?></td>
                                            <td>
                                                <code><?php echo htmlspecialchars($column['name']); ?></code>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($column['type']); ?></span>
                                            </td>
                                            <td>
                                                <?php if ($column['notnull'] == 0): ?>
                                                <span class="badge bg-success">是</span>
                                                <?php else: ?>
                                                <span class="badge bg-secondary">否</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($column['dflt_value'] !== null): ?>
                                                <code class="text-primary"><?php echo htmlspecialchars($column['dflt_value']); ?></code>
                                                <?php else: ?>
                                                <span class="text-muted">NULL</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($column['pk'] == 1): ?>
                                                <span class="badge bg-warning">主键</span>
                                                <?php else: ?>
                                                <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                // 根据字段名猜测用途
                                                $field_hint = '';
                                                if (strpos($column['name'], 'id') !== false) {
                                                    $field_hint = '标识符';
                                                } elseif (strpos($column['name'], 'name') !== false) {
                                                    $field_hint = '名称';
                                                } elseif (strpos($column['name'], 'time') !== false || strpos($column['name'], 'date') !== false || strpos($column['name'], 'at') !== false) {
                                                    $field_hint = '时间戳';
                                                } elseif (strpos($column['name'], 'json') !== false || strpos($column['name'], 'data') !== false) {
                                                    $field_hint = 'JSON数据';
                                                } elseif ($column['type'] === 'INTEGER') {
                                                    $field_hint = '整数';
                                                } elseif ($column['type'] === 'TEXT') {
                                                    $field_hint = '文本';
                                                } elseif ($column['type'] === 'REAL') {
                                                    $field_hint = '浮点数';
                                                }
                                                echo '<span class="text-muted small">' . $field_hint . '</span>';
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- 表数据预览 -->
                        <div class="p-3">
                            <h6 class="mb-3">
                                <i class="fas fa-list me-2"></i>数据预览
                                <small class="text-muted ms-2">最多显示10行</small>
                            </h6>
                            <?php
                            try {
                                $stmt = $db->query("SELECT * FROM `{$table['name']}` LIMIT 10");
                                $sample_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (count($sample_rows) > 0):
                            ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <?php foreach (array_keys($sample_rows[0]) as $header): ?>
                                            <th><?php echo htmlspecialchars($header); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sample_rows as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $cell): ?>
                                            <td>
                                                <?php 
                                                if ($cell === null) {
                                                    echo '<span class="text-muted">NULL</span>';
                                                } elseif (is_string($cell) && strlen($cell) > 50) {
                                                    echo '<span class="text-truncate d-inline-block" style="max-width: 200px;" title="' . htmlspecialchars($cell) . '">' . htmlspecialchars(substr($cell, 0, 50)) . '...</span>';
                                                } elseif (is_string($cell) && json_decode($cell) !== null) {
                                                    echo '<span class="badge bg-info">JSON</span>';
                                                } else {
                                                    echo htmlspecialchars($cell);
                                                }
                                                ?>
                                            </td>
                                            <?php endforeach; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>表中暂无数据
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($table['row_count'] > 10): ?>
                            <div class="mt-3 text-end">
                                <a href="javascript:void(0)" class="btn btn-sm btn-outline-primary" 
                                   onclick="viewTableData('<?php echo htmlspecialchars($table['name']); ?>')">
                                    <i class="fas fa-search me-1"></i>查看更多数据
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php } catch (Exception $e): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>无法读取表数据: <?php echo htmlspecialchars($e->getMessage()); ?>
                            </div>
                            <?php endtry; ?>
                        </div>
                        
                        <!-- 表操作 -->
                        <div class="p-3 bg-light border-top">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <a href="javascript:void(0)" class="btn btn-sm btn-outline-info" 
                                       onclick="copyTableName('<?php echo htmlspecialchars($table['name']); ?>')">
                                        <i class="fas fa-copy me-1"></i>复制表名
                                    </a>
                                    <a href="javascript:void(0)" class="btn btn-sm btn-outline-secondary ms-1" 
                                       onclick="showTableSQL('<?php echo htmlspecialchars($table['name']); ?>')">
                                        <i class="fas fa-code me-1"></i>查看SQL
                                    </a>
                                </div>
                                <div>
                                    <?php if (in_array($table['name'], ['users', 'items', 'user_stats', 'game_config'])): ?>
                                    <a href="<?php echo ($table['name'] === 'users' ? 'users.php' : ($table['name'] === 'items' ? 'items.php' : ($table['name'] === 'user_stats' ? 'stats.php' : 'game_config.php'))); ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-external-link-alt me-1"></i>管理此表
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 数据库统计卡片 -->
<div class="row mt-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <?php
                $stmt = $db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                $total_tables = $stmt->fetchColumn();
                ?>
                <div class="text-muted small mb-2">总表数</div>
                <div class="fs-3 fw-bold text-primary"><?php echo $total_tables; ?></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <?php
                $stmt = $db->query("SELECT SUM(pgsize) FROM dbstat WHERE name NOT LIKE 'sqlite_%'");
                $total_size = $stmt->fetchColumn();
                $total_size_mb = $total_size ? round($total_size / (1024 * 1024), 2) : 0;
                ?>
                <div class="text-muted small mb-2">数据库大小</div>
                <div class="fs-3 fw-bold text-success"><?php echo $total_size_mb; ?> MB</div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <?php
                $stmt = $db->query("SELECT SUM(cnt) FROM (SELECT COUNT(*) as cnt FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' GROUP BY name)");
                $total_rows = $stmt->fetchColumn();
                $total_rows = $total_rows ?: 0;
                ?>
                <div class="text-muted small mb-2">总行数估算</div>
                <div class="fs-3 fw-bold text-warning"><?php echo $total_rows; ?></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <?php
                $stmt = $db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name LIKE '%log%'");
                $log_tables = $stmt->fetchColumn();
                ?>
                <div class="text-muted small mb-2">日志表</div>
                <div class="fs-3 fw-bold text-info"><?php echo $log_tables; ?></div>
            </div>
        </div>
    </div>
</div>

<!-- 数据库信息 -->
<div class="card mt-4">
    <div class="card-header">
        <i class="fas fa-info-circle me-2"></i>数据库信息
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <dl class="mb-0">
                    <dt>数据库文件</dt>
                    <dd><code><?php echo htmlspecialchars(DB_PATH); ?></code></dd>
                    
                    <dt>SQLite版本</dt>
                    <dd><?php echo $db->query('SELECT sqlite_version()')->fetchColumn(); ?></dd>
                </dl>
            </div>
            <div class="col-md-4">
                <dl class="mb-0">
                    <dt>数据库编码</dt>
                    <dd>UTF-8</dd>
                    
                    <dt>外键约束</dt>
                    <dd>
                        <?php 
                        $fk_enabled = $db->query('PRAGMA foreign_keys')->fetchColumn();
                        echo $fk_enabled ? '<span class="badge bg-success">已启用</span>' : '<span class="badge bg-warning">未启用</span>';
                        ?>
                    </dd>
                </dl>
            </div>
            <div class="col-md-4">
                <dl class="mb-0">
                    <dt>最后修改时间</dt>
                    <dd>
                        <?php 
                        if (file_exists(DB_PATH)) {
                            echo date('Y-m-d H:i:s', filemtime(DB_PATH));
                        } else {
                            echo '<span class="text-danger">文件不存在</span>';
                        }
                        ?>
                    </dd>
                    
                    <dt>文件大小</dt>
                    <dd>
                        <?php 
                        if (file_exists(DB_PATH)) {
                            $size = filesize(DB_PATH);
                            echo round($size / 1024 / 1024, 2) . ' MB';
                        } else {
                            echo '<span class="text-danger">未知</span>';
                        }
                        ?>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<script>
function viewTableData(tableName) {
    if (confirm('即将在新窗口查看表 "' + tableName + '" 的完整数据，是否继续？')) {
        window.open('tables_view.php?table=' + encodeURIComponent(tableName), '_blank');
    }
}

function copyTableName(tableName) {
    navigator.clipboard.writeText(tableName).then(function() {
        alert('已复制表名: ' + tableName);
    }).catch(function() {
        // 兼容性方案
        var tempInput = document.createElement('input');
        tempInput.value = tableName;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        alert('已复制表名: ' + tableName);
    });
}

function showTableSQL(tableName) {
    fetch('tables_sql.php?table=' + encodeURIComponent(tableName))
        .then(response => response.text())
        .then(data => {
            alert('表 ' + tableName + ' 的 SQL 定义:\n\n' + data);
        })
        .catch(error => {
            alert('获取SQL失败: ' + error);
        });
}
</script>

<?php
require_once 'footer.php';
?>