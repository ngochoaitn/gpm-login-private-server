## Time
GET /api/time

## Users

GET /api/users

GET /api/users/login
    - Params: user_name, password

POST /api/users/register
    - Form data: user_name, display_name, password

POST /api/users/update
    - Form data: password, display_name, new_password, role

GET /api/users/curent-user


## Groups

GET /api/groups

POST /api/groups/create
    - Params: name, sort

GET /api/groups/delete/{id}

POST /api/groups/update/{id}
    - Params: name, sort

## Profiles

GET /api/profiles
    - Params: sort (created, created_desc, name, name_desc), search, group_id, page, per_page, share_mode (1 = no, 2 = yes), tags

POST /api/profiles/create
    - Params: user_name, password, name, s3_path, json_data, group_id

POST /api/profiles/update/{id}
    - Params: name, json_data, cookie_data, group_id

GET /api/profiles/update-status/{id}
    - Params: status

GET /api/profiles/share/{id}
    - Params: user_id, role (0 - Delete share, 1 - Readonly, 2- Full control)

GET /api/profiles/roles/{id}

GET /api/profiles/delete/{id}

GET /api/profiles/{id}

GET /api/profiles/total

## Settings

GET /api/settings/get-s3-api
GET /api/settings/get-storage-type

## Uploads

POST /api/file/upload
    - Params: file (FormData)
    - Params: file_name
    - Return data: [path, fileName]

GET /api/file/delete
    - Params: fileName
