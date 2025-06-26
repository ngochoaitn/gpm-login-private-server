# API Documentation

## Time

GET /api/time - Lấy thời gian hệ thống

## Authentication

GET /api/users/login - Đăng nhập
- Params: user_name (hoặc email), password

POST /api/users/login - Đăng nhập
- Params: user_name (hoặc email), password

GET /api/users/logout - Đăng xuất (yêu cầu auth)

## Users

GET /api/users - Lấy danh sách users (yêu cầu auth)
- Params: search, per_page (default: 10), page (default: 1)

POST /api/users/register - Đăng ký user mới
- Params: user_name (hoặc email), display_name, password

POST /api/users/update - Cập nhật thông tin user (yêu cầu auth)
- Params: display_name, system_role, new_password

GET /api/users/current-user - Lấy thông tin user hiện tại (yêu cầu auth)

## Groups

GET /api/groups - Lấy danh sách groups (yêu cầu auth)
- Params: search, per_page (default: 30), page (default: 1)

GET /api/groups/count - Lấy tổng số groups (yêu cầu auth)

POST /api/groups/create - Tạo group mới (yêu cầu auth)
- Params: name, order

GET /api/groups/{id} - Lấy thông tin group (yêu cầu auth)
- Params: include_share_users (optional)

POST /api/groups/update/{id} - Cập nhật group (yêu cầu auth)
- Params: name, order

POST /api/groups/delete/{id} - Xóa group (yêu cầu auth)

POST /api/groups/share/{id} - Chia sẻ group (yêu cầu auth)
- Params: user_id, role

POST /api/groups/remove-share/{id} - Xóa chia sẻ group (yêu cầu auth)
- Params: user_id

GET /api/groups/get-share-users/{id} - Lấy danh sách users được chia sẻ group (yêu cầu auth)

## Profiles

GET /api/profiles - Lấy danh sách profiles (yêu cầu auth)
- Params: group_id, search, share_mode (1->no share), tags (hoặc tag_id), sort, per_page (default: 30), page (default: 1), is_deleted (default: 0), extensive_fields

GET /api/profiles/count - Lấy tổng số profiles (yêu cầu auth)

GET /api/profiles/{id} - Lấy thông tin profile (yêu cầu auth)

POST /api/profiles/create - Tạo profile mới (yêu cầu auth)
- Params: name, storage_path, fingerprint_data, dynamic_data, meta_data, group_id, storage_type (default: S3)

POST /api/profiles/update/{id} - Cập nhật profile (yêu cầu auth)
- Params: name, storage_path, fingerprint_data, dynamic_data, meta_data, group_id

POST /api/profiles/update-status/{id} - Cập nhật trạng thái profile (yêu cầu auth)
- Params: status

POST /api/profiles/delete/{id} - Xóa profile (yêu cầu auth)
- Params: mode (default: soft)

POST /api/profiles/bulk-delete - Xóa nhiều profiles (yêu cầu auth)
- Params: profile_ids (hoặc ids), mode (default: soft)

POST /api/profiles/share/{id} - Chia sẻ profile (yêu cầu auth)
- Params: user_id, role

POST /api/profiles/bulk-share - Chia sẻ nhiều profiles (yêu cầu auth)
- Params: profile_ids (hoặc ids), user_id, role

POST /api/profiles/bulk-remove-share - Xóa chia sẻ nhiều profiles (yêu cầu auth)
- Params: profile_ids (hoặc ids), user_id

POST /api/profiles/start-using/{id} - Bắt đầu sử dụng profile (yêu cầu auth)

POST /api/profiles/stop-using/{id} - Dừng sử dụng profile (yêu cầu auth)

POST /api/profiles/add-tags/{id} - Thêm tags cho profile (yêu cầu auth)
- Params: tags (mảng chứa tag id ví dụ ['1', '2'] hoặc mảng chứa thông tin tag đầy đủ [{name, color, category}] )

POST /api/profiles/remove-tags/{id} - Xóa tags khỏi profile (yêu cầu auth)
- Params: tag_ids hoặc ids (mảng tag id ví dụ ['1', '2']) hoặc chỉ truyền lên 1 mảng không cần tên thuộc tính trong json

POST /api/profiles/remove-all-tags/{id} - Xóa tất cả tags khỏi profile (yêu cầu auth)

POST /api/profiles/restore/{id} - Khôi phục profile đã xóa (yêu cầu auth)

POST /api/profiles/bulk-restore - Khôi phục nhiều profiles (yêu cầu auth)
- Params: profile_ids (hoặc ids)

POST /api/profiles/bulk-edit-property - Chỉnh sửa thuộc tính hàng loạt (yêu cầu auth)
- Params: profile_ids (hoặc ids), field_name, new_value (nếu là string chứa nhiều dòng thì mỗi dòng sẽ áp dụng cho 1 profile)

GET /api/profiles/get-share-users/{id} - Lấy danh sách users được chia sẻ profile (yêu cầu auth)

## Settings

GET /api/settings/get-version - Lấy phiên bản private server

GET /api/settings/get-s3-api - Lấy S3 API settings (yêu cầu auth)
- Params: type (get/post), session_id

GET /api/settings/get-storage-type - Lấy loại storage (yêu cầu auth)

GET /api/settings/get-setting - Lấy tất cả settings (yêu cầu auth)

## Uploads

POST /api/file/upload - Upload file (yêu cầu auth)
- Params: file (FormData), file_name
- Return: {path, fileName}

POST /api/file/upload-s3 - Tạo presigned URL cho S3 upload (yêu cầu auth)
- Params: file_name, expires (default: +10 minutes), mime_type (default: application/octet-stream)
- Return: {upload_url}

POST /api/file/delete - Xóa file (yêu cầu auth)
- Params: storage_path

POST /api/file/create-download-url - Tạo URL download (yêu cầu auth)
- Params: storage_path (hoặc file_key), check_file_exists (optional)

## Tags

GET /api/tags - Lấy danh sách tags (yêu cầu auth)
- Params: search, per_page (default: 30), page (default: 1), tag_id, category

GET /api/tags/get-by-name - Lấy tag theo tên (yêu cầu auth)
- Params: name, category

GET /api/tags/{id} - Lấy thông tin tag (yêu cầu auth)

POST /api/tags/create - Tạo tag mới (yêu cầu auth)
- Params: name, color (default: #007bff), category

POST /api/tags/update/{id} - Cập nhật tag (yêu cầu auth)
- Params: name, color, category

GET /api/tags/delete/{id} - Xóa tag (yêu cầu auth)

## Proxies

GET /api/proxies - Lấy danh sách proxies (yêu cầu auth)
- Params: search, tags, status, per_page (default: 30), page (default: 1), tag_id, sort (default: created_desc)

GET /api/proxies/{id} - Lấy thông tin proxy (yêu cầu auth)

POST /api/proxies/bulk-create - Tạo nhiều proxies (yêu cầu auth)
- Params: proxies (array)

POST /api/proxies/update/{id} - Cập nhật proxy (yêu cầu auth)
- Params: raw_proxy, status

POST /api/proxies/delete/{id} - Xóa proxy (yêu cầu auth)

POST /api/proxies/bulk-delete - Xóa nhiều proxies (yêu cầu auth)
- Params: proxy_ids (hoặc ids)

POST /api/proxies/add-tags/{id} - Thêm tags cho proxy (yêu cầu auth)
- Params: tags (mảng chứa tag id ví dụ ['1', '2'] hoặc mảng chứa thông tin tag đầy đủ [{name, color, category}] )

POST /api/proxies/remove-tags/{id} - Xóa tags khỏi proxy (yêu cầu auth)
- Params: tags (hoặc tag_ids hoặc ids hoặc chỉ truyền lên 1 mảng)

POST /api/proxies/remove-all-tags/{id} - Xóa tất cả tags khỏi proxy (yêu cầu auth)

POST /api/proxies/bulk-share - Chia sẻ nhiều proxies (yêu cầu auth)
- Params: proxy_ids (hoặc ids), user_id, role

POST /api/proxies/bulk-remove-share - Xóa chia sẻ nhiều proxies (yêu cầu auth)
- Params: proxy_ids hoặc ids hoặc mảng không cần tên thuộc tính, user_id

GET /api/proxies/get-share-users/{id} - Lấy danh sách users được chia sẻ proxy (yêu cầu auth)
