<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('pageTitle')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>
<body>
    
    
     <header>
         <nav>
            <h1>POSO MANAGEMENT</h1>
        </nav>
    </header>
    
    <main>
        @yield('body')
    </main>

    
    

</body>
</html>