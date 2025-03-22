<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POSO Digital Ticket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .sidebar {
            height: 100vh;
            background: #fff;
            padding: 20px;
        }
        .navbar {
            background: #136f0e;
        }
        .sidebar a {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: black;
            padding: 10px 0;
            font-weight: bold;
        }
        .sidebar a i {
            margin-right: 10px;
        }
        .sidebar-logo {
            max-width: 100px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark px-3">
        <a class="navbar-brand text-white" href="#">POSO Digital Ticket</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link text-white" href="#">Profile</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="#">Log out</a></li>
            </ul>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar border-end">
                <img src="logo.png" alt="POSO Logo" class="sidebar-logo d-block mx-auto">
                <h6>Main menu</h6>
                <a href="#"><i class="bi bi-grid"></i> Dashboard</a>
                <a href="#"><i class="bi bi-exclamation-circle"></i> Violation</a>
                <a href="#"><i class="bi bi-person-badge"></i> Enforcer</a>
                <a href="#"><i class="bi bi-person"></i> Violator</a>
                <a href="#"><i class="bi bi-credit-card"></i> Payments</a>
                <a href="#"><i class="bi bi-bar-chart"></i> Analytics</a>
                <a href="#"><i class="bi bi-chat"></i> Dispute</a>
                <hr>
                <h6>Settings</h6>
                <a href="#"><i class="bi bi-question-circle"></i> Help & Support</a>
            </nav>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="content pt-4">
                    <h4>Welcome to the POSO Digital Ticket System</h4>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
