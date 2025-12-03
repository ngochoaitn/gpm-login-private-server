<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Login admin site</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-GLhlTQ8iRABdZLl6O3oVMWSktQOp6b7In1Zl3/Jr59b6EGGoI1aFkw7cmDA6j6gD" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js" integrity="sha384-w76AqPfDkMBDXo30jS1Sgez6pr3x5MlQ1ZAGC+nuZB+EYdgRZgiwxhTBTkF7CXvN" crossorigin="anonymous"></script>

    <style>
        .container {
            max-width: 400px;
            margin: 100px auto;
            padding: 15px;
        }

        input {
            display: block;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container">
        <h3>Login to admin site</h3>
        <br/>
        <form method="post">
            @csrf
            <label>Admin username</label><br/>
            <input type="text" name="username" class="form-control">
            <br/>
            <label>Password</label><br/>
            <input type="password" name="password" class="form-control"><br/>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>

        @if (Session::has('error'))
            <br/>
            <span style="color: red">{{ Session::get('error') }}</span>
        @endif
    </div>

</body>
</html>
