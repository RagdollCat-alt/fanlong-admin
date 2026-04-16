        <!-- 页面内容结束 -->
    </div><!-- /#content -->

    <!-- 脚本文件 -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <!-- Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom Scripts -->
    <script>
        // 侧边栏折叠/展开
        $('#sidebarToggle').click(function() {
            $('#sidebar').toggleClass('collapsed');
            $('#content').toggleClass('expanded');
            $(this).find('i').toggleClass('fa-bars fa-times');
        });

        // 自动初始化所有DataTables
        $(document).ready(function() {
            $('.datatable').DataTable({
                language: {
                    "decimal": "",
                    "emptyTable": "暂无数据",
                    "info": "显示第 _START_ 至 _END_ 条，共 _TOTAL_ 条",
                    "infoEmpty": "显示第 0 至 0 条，共 0 条",
                    "infoFiltered": "(从 _MAX_ 条记录中筛选)",
                    "infoPostFix": "",
                    "thousands": ",",
                    "lengthMenu": "每页 _MENU_ 条",
                    "loadingRecords": "加载中...",
                    "processing": "处理中...",
                    "search": "搜索:",
                    "zeroRecords": "没有找到匹配的记录",
                    "paginate": {
                        "first": "首页",
                        "last": "末页",
                        "next": "下一页",
                        "previous": "上一页"
                    },
                    "aria": {
                        "sortAscending": ": 升序排列",
                        "sortDescending": ": 降序排列"
                    }
                },
                pageLength: 25,
                responsive: true,
                order: []
            });

            // 初始化Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });

            // 确认删除对话框
            $('.btn-delete').click(function(e) {
                if (!confirm('确定要删除这条记录吗？此操作不可恢复！')) {
                    e.preventDefault();
                }
            });

            // 自动隐藏提示消息
            setTimeout(function() {
                $('.alert-auto-hide').fadeOut('slow');
            }, 3000);
        });

        // 显示加载动画
        function showLoading() {
            $('#loadingOverlay').removeClass('d-none');
        }

        // 隐藏加载动画
        function hideLoading() {
            $('#loadingOverlay').addClass('d-none');
        }

        // 全局 AJAX 错误处理
        $(document).ajaxError(function(event, jqxhr, settings, thrownError) {
            console.error('AJAX 错误:', settings.url, thrownError);
            alert('请求失败: ' + thrownError);
        });
    </script>

    <!-- 加载遮罩 -->
    <div id="loadingOverlay" class="d-none position-fixed top-0 left-0 w-100 h-100 d-flex align-items-center justify-content-center" style="background: rgba(255,255,255,0.8); z-index: 9999;">
        <div class="text-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">加载中...</span>
            </div>
            <p class="mt-3 text-muted">处理中，请稍候...</p>
        </div>
    </div>

    <!-- 页脚信息 -->
    <footer class="mt-5 text-center text-muted small">
        <hr>
        <p>© 2026 繁笼帝国 · 机器人后台管理系统 | 版本 1.0.0 | 最后更新: <?php echo date('Y-m-d'); ?></p>
        <p class="mb-0">本系统仅供管理员使用，请妥善保管您的登录凭证。</p>
    </footer>
</body>
</html>