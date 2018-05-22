<!doctype html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Laravel</title>

        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css?family=Raleway:100,600" rel="stylesheet" type="text/css">
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css" integrity="sha384-WskhaSGFgHYWDcbwN70/dfYBj47jz9qbsMId/iRN3ewGhXQFZCSftd1LZCfmhktB" crossorigin="anonymous">

        <!-- Styles -->
        <style>
            html, body {
                background-color: #fff;
                color: #636b6f;
                font-family: 'Raleway', sans-serif;
                font-weight: 100;
                height: 100vh;
                margin: 0;
            }

            .full-height {
                height: 100vh;
            }

            .flex-center {
                align-items: center;
                display: flex;
                justify-content: center;
            }

            .position-ref {
                position: relative;
            }

            .top-right {
                position: absolute;
                right: 10px;
                top: 18px;
            }

            .content {
                text-align: center;
            }

            .title {
                font-size: 84px;
            }

            .links > a {
                color: #636b6f;
                padding: 0 25px;
                font-size: 12px;
                font-weight: 600;
                letter-spacing: .1rem;
                text-decoration: none;
                text-transform: uppercase;
            }

            .m-b-md {
                margin-bottom: 30px;
            }
        </style>
    </head>
    <body>
        <div class="flex-center position-ref full-height">
            @if (Route::has('login'))
                <div class="top-right links">
                    @auth
                        <a href="{{ url('/home') }}">Home</a>
                    @else
                        <a href="{{ route('login') }}">Login</a>
                        <a href="{{ route('register') }}">Register</a>
                    @endauth
                </div>
            @endif

            <div class="content">
                <div class="title m-b-md">
                    Laravel
                </div>

                <div class="links">
                    <a href="https://laravel.com/docs">Documentation</a>
                    <a href="https://laracasts.com">Laracasts</a>
                    <a href="https://laravel-news.com">News</a>
                    <a href="https://forge.laravel.com">Forge</a>
                    <a href="https://github.com/laravel/laravel">GitHub</a>
                </div>

                <div class="data-list"></div>

                <div>
                    <form action="{{ route('test') }}" method="post" class="frm">
                        @csrf
                        <div>
                            <label>Title 1: </label>
                            <input type="hidden" name="fieldsRequired[]" value="per_booking_fields[0][respone][0].REQUIRED_ON_BOOKING>This is title 1">
                            <input type="hidden" name="per_booking_fields[0][unit_id]" value="17000">
                            <input type="text" name="per_booking_fields[0][respone][0]">
                        </div>
                        <div>
                            <label>Title 2: </label>
                            <input type="hidden" name="fieldsRequired[]" value="per_participants_booking_fields[0][respone][0].SELECT_ONE.YES,NO>This is title 2">
                            <input type="hidden" name="per_participants_booking_fields[0][responses][booking_fields_id]" value="1200">
                            <input type="radio" name="per_participants_booking_fields[0][responses][respone][0]" value="YES" checked> YES<br>
                            <input type="radio" name="per_participants_booking_fields[0][responses][respone][0]" value="NO"> NO
                        </div>
                        <div>
                            <button onclick="save(event)">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.min.js" integrity="sha384-smHYKdLADwkXOn1EmN1qk/HfnUcbVRZyYmZ4qpPea6sjB/pTJ0euyQp0Mk8ck+5T" crossorigin="anonymous"></script>
        <script>
            $(function () {
                $.ajax({
                    url: '/?page=1',
                    dataType: 'html',
                    method: 'get',
                    success: function(data) {
                        if (data) {
                            $('.data-list').html(data);
                        }
                    },
                    error: function (err) {
                        console.log(err);
                    }
                });
            });

            $(document).on('click', 'ul.pagination li a', function (e) {
                e.preventDefault();

                $.ajax({
                    url: $(e.target).attr('href'),
                    dataType: 'html',
                    method: 'get',
                    success: function(data) {
                        if (data) {
                            $('.data-list').html(data);
                        }
                    },
                    error: function (err) {
                        console.log(err);
                    }
                });
            });

            function save(event) {
                event.preventDefault();

                var form = $(event.target).closest('form');
                var data = form.serialize();
                console.log(data);
                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                    },
                    url: form.attr('action'),
                    type: 'post',
                    data: data,
                    dataType: 'json',
                    success: function (data) {
                        console.log(data);
                    },
                    error: function (err) {
                        console.log(err);
                    }
                })
            }
        </script>
    </body>
</html>
