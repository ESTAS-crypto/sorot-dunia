<?php

$url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

$base_url = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '';

$site_title = 'evan';

$name_portfolio = 'evan';

$name_heading = 'evan';

$desc_portfolio = '1 years of experience in fullstack development, using Javascript for developing a website and web applications.';

$phone_wa = '62895385890629';

$avatar = 'avatar11.webp';



$urlAvatar = $base_url . $avatar;



?>



<!doctype html>

<html lang="en">



<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?php echo $site_title. ' - Portfolio'; ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="style.css">

    <style>
    body {

        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;

    }



    a {

        color: #040404;

        text-decoration: none;

    }
    </style>

</head>



<body>



    <header>

        <div class="bg-white shadow-sm py-3">

            <div class="container">

                <nav class="navbar navbar-expand-sm navbar-light">

                    <a class="navbar-brand fw-bold fs-2 text-dark d-inline-flex align-items-center d-sm-none" href="#">

                        <?php echo $name_portfolio; ?>

                    </a>

                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                        data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                        aria-expanded="false" aria-label="Toggle navigation">

                        <span class="navbar-toggler-icon"></span>

                    </button>

                    <div class="collapse navbar-collapse" id="navbarSupportedContent">

                        <ul class="navbar-nav ms-auto me-auto gap-sm-5">

                            <li class="nav-item">

                                <a class="nav-link active" aria-current="page" href="<?php echo $url; ?>">Home</a>

                            </li>

                            <li class="nav-item">

                                <a class="nav-link" href="#">About</a>

                            </li>

                            <li class="nav-item">

                                <a class="nav-link " href="#">Project</a>

                            </li>

                        </ul>

                    </div>

                </nav>

            </div>

        </div>

    </header>



    <div class="main py-5">

        <div class="container text-center">

            <div class="row">

                <div class="col-md-6 offset-md-3">

                    <p class="mb-5"><img src="<?php echo $urlAvatar; ?>" class="rounded-pill img-fluid"
                            style="width: 200px; height: 200px;" alt=""></p>

                    <h4 class="fw-bold mb-4"><?php echo $name_heading; ?></h4>

                    <p><?php echo $desc_portfolio; ?></p>

                    <div class="d-inline-flex flex-column flex-lg-row gap-3 w-100 mt-5">

                        <a href="https://wa.me/<?php echo $phone_wa; ?>"
                            class="btn btn-dark py-3 px-4 w-100 text-nowrap rounded-pill">Contact me</a>

                        <a href="#" class="btn btn-outline-dark py-3 px-4 w-100 text-nowrap rounded-pill">Download

                            CV</a>

                    </div>

                </div>

            </div>

        </div>

    </div>





    <footer>

        <div class="container">

            <div class="text-center py-4">

                © 2025 <?php echo $name_portfolio; ?>. All rights reserved.

            </div>

        </div>

    </footer>







    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>



</body>



</html>