# Network Site Stats – WordPress Multisite Plugin

## Sinh viên
- **Họ tên:** Nguyễn Công Sơn

## Mô tả
Plugin giúp Super Admin theo dõi thống kê các site con trong mạng lưới 
WordPress Multisite. Hiển thị ID, tên site, số bài viết, số trang, 
bình luận và ngày bài viết mới nhất.

## Cấu trúc thư mục
network-site-stats-submission/
├── plugin/          # Thư mục plugin cài vào WordPress
├── database/        # File SQL export từ phpMyAdmin
├── report/          # Báo cáo Word
└── README.md

## Cách cài đặt Plugin
1. Copy thư mục `plugin/network-site-stats/` vào `wp-content/plugins/`
2. Vào Network Admin → Plugins → Network Activate
3. Vào Network Admin → Site Stats

## Yêu cầu
- WordPress 5.0+
- Chế độ Multisite đã được kích hoạt
- Tài khoản Super Admin

## Công nghệ sử dụng
- WordPress Multisite
- PHP 7.4+
- Hàm: `get_sites()`, `switch_to_blog()`, `restore_current_blog()`

Bước 4 – Mở Command Prompt, chạy lệnh Git
bashcd Desktop\network-site-stats-submission

git init
git add .
git commit -m "feat: Network Site Stats - WordPress Multisite Plugin"
Vào https://github.com/new:

Repository name: network-site-stats
Description: WordPress Multisite Plugin 
Public ✅
Nhấn Create repository

GitHub sẽ hiện lệnh, copy phần "push an existing repository":
bashgit remote add origin https://github.com/MrRock-VN/network-site-stats.git
git branch -M main
git push -u origin main


Bạn đã có file .sql từ phpMyAdmin chưa? Nếu chưa export thì làm bước đó trước rồi mới gom file vào thư mục nhé!
