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

            
            
            @auth
                <p>Hi there, {{Auth::user()->name}}.</p>
                <a href="">Settings</a>
                <form action="" method="POST">
                    @csrf 
                    <a href="">Logout</a>
                </form>
            @endauth
        </nav>
    </header>
    
    <main>
        @yield('body')
    </main>

    
        

</body>
</html>