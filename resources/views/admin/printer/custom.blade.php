<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <link rel="stylesheet" href="style.css">
        <title>@yield('title')</title>
        <style>
            * {
                font-size: 16px;
                font-family: 'Times New Roman';
            }

            .text-center {
                text-align: center;
                align-content: center;
            }

            .ticket {
                width: 360px;
            }

            img {
                max-width: inherit;
                width: inherit;
            }
            .title{
                font-size: 16px;
                font-weight: 900;
            }

            @media print {
                .hidden-print,
                .hidden-print * {
                    display: none !important;
                }
            }
        </style>
    </head>
    <body>
        @yield('content')
</html>