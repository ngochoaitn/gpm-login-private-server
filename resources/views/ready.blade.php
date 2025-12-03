<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Private server v12.2023</title>

    <style>
        .container {
            text-align: center;
            margin: 100px auto;
        }

        img {
            display: block;
            margin: 0 auto;
        }

        a {
            color: #0080ff;
            text-decoration: none;
            border: 1px solid #0080ff;
            background: #f1f7fd;
            border-radius: 5px;
            padding: 10px 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="assets/img/running.png">

        @php
            $adminUrl = url('admin');
        @endphp

        <a href="{{$adminUrl}}" style="font-size: 21px">Go to the admin site</a>
    </div>

</body>
</html>
