<?php
/**
 * Plugin Name:  Network Site Stats
 * Plugin URI:   https://github.com/example/network-site-stats
 * Description:  Hiển thị thống kê tổng quan các site con trong mạng lưới WordPress Multisite. Dành cho Super Admin.
 * Version:      1.0.0
 * Author:       Nguyễn Công Sơn
 * Network:      true
 * Text Domain:  network-site-stats
 * Domain Path:  /languages
 */

// Ngăn truy cập trực tiếp vào file PHP
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ============================================================
// 1. KIỂM TRA MULTISITE
// Chỉ hoạt động khi WordPress đang chạy ở chế độ Multisite
// ============================================================
if ( ! is_multisite() ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p>'
            . __( 'Network Site Stats yêu cầu WordPress Multisite.', 'network-site-stats' )
            . '</p></div>';
    } );
    return;
}

// ============================================================
// 2. ĐĂNG KÝ MENU TRONG NETWORK ADMIN
// Thêm mục menu vào thanh điều hướng của Network Admin Dashboard
// ============================================================
add_action( 'network_admin_menu', 'nss_register_network_menu' );

function nss_register_network_menu() {
    add_menu_page(
        __( 'Network Site Stats', 'network-site-stats' ),   // Tiêu đề trang
        __( 'Site Stats', 'network-site-stats' ),           // Nhãn menu
        'manage_network',                                    // Capability: chỉ Super Admin
        'network-site-stats',                               // Slug (định danh menu)
        'nss_render_stats_page',                            // Callback render trang
        'dashicons-chart-bar',                              // Biểu tượng
        3                                                   // Vị trí trong menu
    );
}

// ============================================================
// 3. ĐĂNG KÝ ASSETS (CSS & JS)
// Chỉ tải trên trang plugin trong Network Admin
// ============================================================
add_action( 'network_admin_enqueue_scripts', 'nss_enqueue_assets' );

function nss_enqueue_assets( $hook_suffix ) {
    // Chỉ tải khi đang ở trang plugin
    if ( strpos( $hook_suffix, 'network-site-stats' ) === false ) {
        return;
    }

    // Inline CSS – không cần file riêng để đơn giản hóa
    $css = '
        .nss-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .nss-header { display:flex; align-items:center; gap:12px; margin-bottom:24px; }
        .nss-header h1 { margin:0; }
        .nss-badge { background:#2271b1; color:#fff; border-radius:20px;
                     padding:2px 12px; font-size:13px; }
        .nss-stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
                          gap:16px; margin-bottom:28px; }
        .nss-card { background:#fff; border:1px solid #dcdcde; border-radius:8px;
                    padding:20px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,.06); }
        .nss-card-number { font-size:32px; font-weight:700; color:#2271b1; display:block; }
        .nss-card-label { font-size:12px; color:#646970; margin-top:4px; display:block; }
        .nss-table-wrap { background:#fff; border:1px solid #dcdcde; border-radius:8px;
                          overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.06); }
        .nss-table { width:100%; border-collapse:collapse; }
        .nss-table thead th { background:#f0f6fc; color:#1d2327; font-weight:600;
                              padding:12px 16px; text-align:left; border-bottom:2px solid #c3c4c7; }
        .nss-table tbody tr { border-bottom:1px solid #f0f0f1; transition:background .15s; }
        .nss-table tbody tr:last-child { border-bottom:none; }
        .nss-table tbody tr:hover { background:#f6f7f7; }
        .nss-table td { padding:12px 16px; vertical-align:middle; font-size:13px; }
        .nss-site-name a { color:#2271b1; font-weight:600; text-decoration:none; }
        .nss-site-name a:hover { color:#135e96; text-decoration:underline; }
        .nss-status-active { color:#1a7f37; font-weight:600; }
        .nss-status-inactive { color:#d63638; font-weight:600; }
        .nss-post-count { background:#f0f6fc; border-radius:12px; padding:2px 10px;
                          font-weight:700; color:#2271b1; }
        .nss-action-links a { margin-right:8px; font-size:12px; color:#2271b1; text-decoration:none; }
        .nss-action-links a:hover { color:#135e96; }
        .nss-refresh { margin-bottom:16px; }
    ';
    wp_add_inline_style( 'common', $css );
}

// ============================================================
// 4. HÀM LẤY DỮ LIỆU THỐNG KÊ TỪNG SITE CON
// Sử dụng switch_to_blog() để chuyển ngữ cảnh, lấy dữ liệu
// chính xác của từng site, rồi restore_current_blog()
// ============================================================

/**
 * Lấy thống kê chi tiết của một site dựa theo blog_id.
 *
 * @param  int   $blog_id  ID của site con.
 * @return array           Mảng dữ liệu thống kê.
 */
function nss_get_site_stats( int $blog_id ): array {
    // Chuyển ngữ cảnh sang site con cần lấy dữ liệu
    switch_to_blog( $blog_id );

    // --- Số bài viết đã xuất bản ---
    $post_count = (int) wp_count_posts( 'post' )->publish;

    // --- Số trang (page) đã xuất bản ---
    $page_count = (int) wp_count_posts( 'page' )->publish;

    // --- Số bình luận đã duyệt ---
    $comment_count = (int) get_comments( [ 'count' => true, 'status' => 'approve' ] );

    // --- Bài viết mới nhất ---
    $latest_posts = get_posts( [
        'numberposts' => 1,
        'post_status' => 'publish',
        'orderby'     => 'date',
        'order'       => 'DESC',
    ] );
    $latest_post_date = ! empty( $latest_posts )
        ? get_post_field( 'post_date', $latest_posts[0]->ID )
        : null;

    // --- Số thành viên ---
    $user_count = (int) get_users( [ 'count_total' => true, 'number' => 1 ] )
                  + (int) count_users()['total_users'];
    // Cách đơn giản hơn:
    $user_count = (int) count_users()['total_users'];

    // Khôi phục lại ngữ cảnh site gốc (rất quan trọng!)
    restore_current_blog();

    return [
        'post_count'       => $post_count,
        'page_count'       => $page_count,
        'comment_count'    => $comment_count,
        'latest_post_date' => $latest_post_date,
        'user_count'       => $user_count,
    ];
}

// ============================================================
// 5. RENDER TRANG THỐNG KÊ TRONG NETWORK ADMIN
// ============================================================
function nss_render_stats_page() {
    // Kiểm tra quyền – chỉ Super Admin
    if ( ! current_user_can( 'manage_network' ) ) {
        wp_die( __( 'Bạn không có quyền truy cập trang này.', 'network-site-stats' ) );
    }

    // Lấy danh sách tất cả các site trong mạng lưới
    $sites = get_sites( [
        'number'  => 500,   // Giới hạn tối đa 500 site
        'orderby' => 'id',
        'order'   => 'ASC',
    ] );

    // Tổng hợp số liệu toàn mạng
    $total_posts    = 0;
    $total_pages    = 0;
    $total_comments = 0;
    $total_users    = count_users()['total_users']; // Users dùng chung toàn mạng

    // Lấy thống kê từng site và cộng dồn
    $site_stats = [];
    foreach ( $sites as $site ) {
        $stats                       = nss_get_site_stats( (int) $site->blog_id );
        $site_stats[ $site->blog_id ] = $stats;
        $total_posts                 += $stats['post_count'];
        $total_pages                 += $stats['page_count'];
        $total_comments              += $stats['comment_count'];
    }

    $total_sites = count( $sites );
    ?>
    <div class="wrap nss-wrap">

        <!-- ===== HEADER ===== -->
        <div class="nss-header">
            <span class="dashicons dashicons-chart-bar" style="font-size:28px;color:#2271b1;"></span>
            <h1><?php esc_html_e( 'Network Site Stats', 'network-site-stats' ); ?></h1>
            <span class="nss-badge"><?php echo esc_html( $total_sites ); ?> sites</span>
        </div>

        <!-- ===== TỔNG QUAN (CARDS) ===== -->
        <div class="nss-stats-grid">
            <div class="nss-card">
                <span class="nss-card-number"><?php echo esc_html( $total_sites ); ?></span>
                <span class="nss-card-label"><?php esc_html_e( 'Tổng số Site', 'network-site-stats' ); ?></span>
            </div>
            <div class="nss-card">
                <span class="nss-card-number"><?php echo esc_html( number_format( $total_posts ) ); ?></span>
                <span class="nss-card-label"><?php esc_html_e( 'Tổng Bài Viết', 'network-site-stats' ); ?></span>
            </div>
            <div class="nss-card">
                <span class="nss-card-number"><?php echo esc_html( number_format( $total_pages ) ); ?></span>
                <span class="nss-card-label"><?php esc_html_e( 'Tổng Trang', 'network-site-stats' ); ?></span>
            </div>
            <div class="nss-card">
                <span class="nss-card-number"><?php echo esc_html( number_format( $total_comments ) ); ?></span>
                <span class="nss-card-label"><?php esc_html_e( 'Tổng Bình Luận', 'network-site-stats' ); ?></span>
            </div>
            <div class="nss-card">
                <span class="nss-card-number"><?php echo esc_html( number_format( $total_users ) ); ?></span>
                <span class="nss-card-label"><?php esc_html_e( 'Tổng Người Dùng', 'network-site-stats' ); ?></span>
            </div>
        </div>

        <!-- ===== NÚT LÀM MỚI ===== -->
        <div class="nss-refresh">
            <a href="<?php echo esc_url( network_admin_url( 'admin.php?page=network-site-stats' ) ); ?>"
               class="button button-secondary">
                <span class="dashicons dashicons-update" style="vertical-align:middle;margin-top:-2px;"></span>
                <?php esc_html_e( 'Làm mới dữ liệu', 'network-site-stats' ); ?>
            </a>
            <small style="color:#646970;margin-left:10px;">
                <?php printf(
                    /* translators: %s: time */
                    esc_html__( 'Cập nhật lúc: %s', 'network-site-stats' ),
                    esc_html( wp_date( 'd/m/Y H:i:s' ) )
                ); ?>
            </small>
        </div>

        <!-- ===== BẢNG CHI TIẾT CÁC SITE ===== -->
        <div class="nss-table-wrap">
            <table class="nss-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'network-site-stats' ); ?></th>
                        <th><?php esc_html_e( 'Tên Site (Blog Name)', 'network-site-stats' ); ?></th>
                        <th><?php esc_html_e( 'Domain / Path', 'network-site-stats' ); ?></th>
                        <th><?php esc_html_e( 'Trạng thái', 'network-site-stats' ); ?></th>
                        <th><?php esc_html_e( 'Bài Viết', 'network-site-stats' ); ?></th>
                        <th><?php esc_html_e( 'Trang', 'network-site-stats' ); ?></th>
                        <th><?php esc_html_e( 'Bình Luận', 'network-site-stats' ); ?></th>
                        <th><?php esc_html_e( 'Bài mới nhất', 'network-site-stats' ); ?></th>
                        <th><?php esc_html_e( 'Thao tác', 'network-site-stats' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $sites as $site ) :
                        $blog_id    = (int) $site->blog_id;
                        $details    = get_blog_details( $blog_id );
                        $stats      = $site_stats[ $blog_id ];
                        $is_public  = (int) $details->public === 1;
                        $is_deleted = (int) $details->deleted === 1;

                        // Xác định trạng thái site
                        if ( $is_deleted ) {
                            $status_label = __( 'Đã xoá', 'network-site-stats' );
                            $status_class = 'nss-status-inactive';
                        } elseif ( (int) $details->archived === 1 ) {
                            $status_label = __( 'Lưu trữ', 'network-site-stats' );
                            $status_class = 'nss-status-inactive';
                        } else {
                            $status_label = __( 'Hoạt động', 'network-site-stats' );
                            $status_class = 'nss-status-active';
                        }

                        // Ngày bài mới nhất
                        $latest_date = $stats['latest_post_date']
                            ? date_i18n( 'd/m/Y', strtotime( $stats['latest_post_date'] ) )
                            : '—';
                    ?>
                    <tr>
                        <!-- ID Site -->
                        <td><strong>#<?php echo esc_html( $blog_id ); ?></strong></td>

                        <!-- Tên site với link đến Dashboard của site đó -->
                        <td class="nss-site-name">
                            <a href="<?php echo esc_url( get_admin_url( $blog_id ) ); ?>" target="_blank">
                                <?php echo esc_html( $details->blogname ?: '(không có tên)' ); ?>
                            </a>
                        </td>

                        <!-- Domain + Path -->
                        <td>
                            <a href="<?php echo esc_url( $details->siteurl ); ?>" target="_blank" style="color:#646970;font-size:12px;">
                                <?php echo esc_html( $details->domain . $details->path ); ?>
                            </a>
                        </td>

                        <!-- Trạng thái -->
                        <td>
                            <span class="<?php echo esc_attr( $status_class ); ?>">
                                <?php echo esc_html( $status_label ); ?>
                            </span>
                        </td>

                        <!-- Số bài viết -->
                        <td>
                            <span class="nss-post-count"><?php echo esc_html( number_format( $stats['post_count'] ) ); ?></span>
                        </td>

                        <!-- Số trang -->
                        <td><?php echo esc_html( number_format( $stats['page_count'] ) ); ?></td>

                        <!-- Số bình luận -->
                        <td><?php echo esc_html( number_format( $stats['comment_count'] ) ); ?></td>

                        <!-- Bài mới nhất -->
                        <td><?php echo esc_html( $latest_date ); ?></td>

                        <!-- Thao tác nhanh -->
                        <td class="nss-action-links">
                            <a href="<?php echo esc_url( get_admin_url( $blog_id ) ); ?>" target="_blank">
                                Dashboard
                            </a>
                            <a href="<?php echo esc_url( get_admin_url( $blog_id, 'edit.php' ) ); ?>" target="_blank">
                                Bài viết
                            </a>
                            <a href="<?php echo esc_url( network_admin_url( 'site-info.php?id=' . $blog_id ) ); ?>">
                                Cài đặt
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div><!-- /.nss-table-wrap -->

        <p style="margin-top:16px;color:#646970;font-size:12px;">
            <?php esc_html_e( '* Dữ liệu được tính theo thời gian thực. Plugin sử dụng switch_to_blog() để truy vấn chính xác từng site con.', 'network-site-stats' ); ?>
        </p>

    </div><!-- /.wrap -->
    <?php
}

// ============================================================
// 6. THÊM LIÊN KẾT "Network Admin" VÀO TRANG PLUGINS
// Cho phép vào thẳng trang thống kê từ danh sách plugin
// ============================================================
add_filter( 'network_admin_plugin_action_links_network-site-stats/network-site-stats.php', 'nss_add_action_links' );

function nss_add_action_links( array $links ): array {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        esc_url( network_admin_url( 'admin.php?page=network-site-stats' ) ),
        __( 'Xem thống kê', 'network-site-stats' )
    );
    array_unshift( $links, $settings_link );
    return $links;
}
