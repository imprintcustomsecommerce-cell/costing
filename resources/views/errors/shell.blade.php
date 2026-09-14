{{--
    The shell every error page sits in.

    Deliberately standalone rather than extending layouts.app: that layout
    reads auth()->user()->name unguarded, which would fatal inside an error
    page on the one occasion the session is already gone - a second error
    raised while rendering the first is the hardest kind to diagnose.

    Only the stylesheet is pulled in. The app's JavaScript has nothing to do
    on a page whose whole purpose is a message and a way out.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102238">
    <title>@yield('error-title') &middot; Imprint Customs Costing</title>
    @vite(['resources/css/app.css'])
    <script>
        try {
            var saved = localStorage.getItem('theme');
            if (saved === 'dark' || saved === 'light') {
                document.documentElement.dataset.theme = saved;
            }
        } catch (e) { /* private mode: fall back to the OS preference */ }
    </script>
</head>
<body class="app-body">

<main class="error-page">
    <div class="error-card">
        <div class="error-code">@yield('error-code')</div>
        <h1>@yield('error-heading')</h1>
        @yield('error-body')
    </div>
</main>

</body>
</html>
