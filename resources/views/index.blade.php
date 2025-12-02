<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Admin site v13.2025</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-GLhlTQ8iRABdZLl6O3oVMWSktQOp6b7In1Zl3/Jr59b6EGGoI1aFkw7cmDA6j6gD" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-w76AqPfDkMBDXo30jS1Sgez6pr3x5MlQ1ZAGC+nuZB+EYdgRZgiwxhTBTkF7CXvN" crossorigin="anonymous">
    </script>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
    .container {
        max-width: 1200px;
        margin: 50px auto;
        padding: 15px;
    }

    input {
        display: block;
        width: 100%;
    }

    a {
        text-decoration: none;
    }

    select {
        max-width: 300px !important;
    }

    .btn {
        font-size: 13px;
    }
    </style>
</head>

<body>
    <div class="container">
        <h3 style="color: #0080C0">Admin site
            <small>(v{{ \App\Services\SettingService::$server_version }})</small>
        </h3>
        @if (Session::has('msg'))
        <div class="alert alert-success">
            {{ Session::get('msg')}}
        </div>
        @endif
        <a class="badge bg-danger" href="{{ url('admin/auth/logout') }}">Logout</a>
        &nbsp;<a href="{{ url('admin/reset-profile-status') }}" class="badge bg-success">Reset profile status</a>
        &nbsp;<a href="{{ url('admin/migration') }}" class="badge bg-secondary">Migration database</a>
        &nbsp;<a href="{{ url('auto-update') }}" class="badge bg-secondary">Update private server</a>
        <br /><br /><br />

        <h3 style="color: #0080C0">Storage setting</h3><br />
        <form action="admin/save-setting">
            <select name="type" class="form-control mb-3" onchange="handleStorageTypeChange(this)">
                <option value="s3" @if ($storageType=='s3' ) selected @endif>S3 (lưu trữ trong database)</option>
                <option value="local" @if ($storageType=='local' ) selected @endif>Local Storage (Recommended for LAN)
                </option>
            </select>

            <!-- S3 config -->
            <div id="s3Config" @if($storageType !='s3' ) style="display: none;" @endif>
                <div class="row">
                    <div class="mb-3 col-md-6">
                        <label class="form-label" for="S3_KEY">S3_KEY</label>
                        <input name="S3_KEY" class="form-control" id="S3_KEY" placeholder="S3 key"
                            value="{{ $s3Config->S3_KEY  }}" />
                    </div>
                    <div class="mb-3 col-md-6">
                        <label class="form-label" for="S3_PASSWORD">S3_PASSWORD</label>
                        <input name="S3_PASSWORD" class="form-control" id="S3_PASSWORD" placeholder="S3 secret"
                            value="{{ $s3Config->S3_PASSWORD  }}" />
                    </div>
                </div>
                <div class="row">
                    <div class="mb-3 col-md-6">
                        <label class="form-label" for="S3_BUCKET">S3_BUCKET</label>
                        <input name="S3_BUCKET" class="form-control" id="S3_BUCKET" placeholder="S3 bucket"
                            value="{{ $s3Config->S3_BUCKET  }}" />
                    </div>
                    <div class="mb-3 col-md-6">
                        <label class="form-label" for="S3_REGION">S3_REGION</label>
                        @php
                        $standardRegions = [
                            "APEast1", "AFSouth1", "APNortheast1", "APNortheast2", "APNortheast3",
                            "APSouth1", "APSoutheast1", "APSoutheast2", "CACentral1", "CNNorth1",
                            "CNNorthWest1", "EUCentral1", "EUNorth1", "EUSouth1", "EUWest1",
                            "EUWest2", "EUWest3", "MESouth1", "SAEast1", "USEast1",
                            "USEast2", "USGovCloudEast1", "USGovCloudWest1", "USIsobEast1",
                            "USIsoEast1", "USWest1", "USWest2"
                        ];
                        $isCustomRegion = !in_array($s3Config->S3_REGION, $standardRegions);
                        @endphp
                        <input name="S3_REGION" class="form-control" id="S3_REGION_CUSTOM" placeholder="S3 region"
                            value="{{ $s3Config->S3_REGION  }}" style="display: {{ $isCustomRegion ? '' : 'none' }};" />

                        <select name="S3_REGION" class="form-control" id="S3_REGION_SELECT"
                            style="display: {{ $isCustomRegion ? 'none' : '' }};">
                        @foreach($standardRegions as $region)
                            <option value="{{ $region }}" {{ $s3Config->S3_REGION == $region ? 'selected' : '' }}>
                                {{ $region }}
                            </option>
                        @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <!-- cache extension -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="d-flex">
                        <input type="checkbox" class="form-check-input me-2" name="cache_extension" id="cache_extension"
                            {{ $cache_extension_setting == 'on' ? 'checked' : '' }} />
                        <label class="form-check-label" for="cache_extension">Enable cache extension (<a href="#"
                                onclick="event.preventDefault(); document.getElementById('detail_cache_extension').style.display = 'block'">Details</a>)</label>
                    </div>
                    <div id="detail_cache_extension" style="display: none;">
                        Cache extension applicable to profiles created from <b>September 26, 2024</b><br />
                        Extensions will be uploaded and stored on a private server <i>(files with the prefix cache_)</i> instead
                        of being stored in the profile<br />
                        This helps <b>save storage space, reduce load times, and improve profile opening speed</b><br />
                        <span style="color:red"><b>The private server administrator is responsible for enabling or disabling
                                this feature</b></span>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="d-flex">
                        <input type="checkbox" class="form-check-input me-2" name="cbo_custom_region" id="cbo_custom_region"
                        {{ $isCustomRegion ? 'checked' : '' }}/>
                        <label class="form-check-label" for="cbo_custom_region">Custom region</label>
                    </div>
                </div>
            </div>
            <br>
            <button class="btn btn-primary" type="submit">Apply</button>
        </form>

        <br /><br />
        <h3 style="color: #0080C0">User manager</h3><br />
        <div class="overflow-hidden border  md:rounded-lg">
            <table class="table table-hover">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Display name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Active status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Action</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reset
                            Password</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach ($users as $user)
                    <tr class="hover:bg-gray-50 transition-colors duration-150">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $user->email }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $user->display_name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($user->is_active)
                            <span
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 border border-green-200">
                                <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                Active
                            </span>
                            @else
                            <span
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 border border-red-200">
                                <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                Deactivated
                            </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            @php
                            $activeUrl = url('admin/active-user').'/'.$user->id;
                            @endphp
                            @if($user->is_active)
                            <a href="{{ $activeUrl }}"
                                class="inline-flex items-center px-3 py-1.5 border border-red-300 shadow-sm text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728">
                                    </path>
                                </svg>
                                Deactivate
                            </a>
                            @else
                            <a href="{{ $activeUrl }}"
                                class="inline-flex items-center px-3 py-1.5 border border-green-300 shadow-sm text-sm font-medium rounded-md text-green-700 bg-white hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Activate
                            </a>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            @php
                            $resetPasswordUrl = url('admin/reset-user-password').'/'.$user->id;
                            @endphp
                            <a href="{{ $resetPasswordUrl }}"
                                onclick="return confirm('Are you sure you want to reset password for {{ $user->display_name }}? The new password will be displayed after reset.')"
                                class="inline-flex items-center px-3 py-1.5 border border-orange-300 shadow-sm text-sm font-medium rounded-md text-orange-700 bg-white hover:bg-orange-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-colors duration-200">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z">
                                    </path>
                                </svg>
                                Reset Password
                            </a>
                        </td>
                    </tr>
                    @endforeach

                </tbody>
            </table>
        </div>
    </div>
</body>
<script>
function handleStorageTypeChange(select) {
    var s3Config = document.getElementById("s3Config");
    if (select.value === "s3") {
        s3Config.style.display = "block";
    } else {
        s3Config.style.display = "none";
    }
}

document.addEventListener("DOMContentLoaded", function() {
    const checkbox = document.getElementById("cbo_custom_region");
    const selectBox = document.getElementById("S3_REGION_SELECT");
    const customInput = document.getElementById("S3_REGION_CUSTOM");

    function toggleRegionInput() {
        if (checkbox.checked) {
            customInput.style.display = "";
            customInput.setAttribute("name", "S3_REGION");

            selectBox.style.display = "none";
            selectBox.removeAttribute("name");
        } else {
            customInput.style.display = "none";
            customInput.removeAttribute("name");

            selectBox.style.display = "";
            selectBox.setAttribute("name", "S3_REGION");
        }
    }

    checkbox.addEventListener("change", toggleRegionInput);
    toggleRegionInput(); // chạy lần đầu để đảm bảo đúng trạng thái
});
</script>

</html>
