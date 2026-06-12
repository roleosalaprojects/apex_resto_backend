<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('header')</title>
    <style href="{{asset("assets/plugins/global/plugins.bundle.css")}}"></style>
    <style href="{{asset("assets/css/style.bundle.css")}}"></style>
</head>
<body>
    @yield('content')
    <script src="{{asset("assets/plugins/global/plugins.bundle.js")}}"></script>
    <script src="{{asset("assets/js/scripts.bundle.js")}}"></script>
</body>
</html>