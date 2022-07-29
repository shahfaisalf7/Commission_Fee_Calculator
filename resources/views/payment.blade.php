<!DOCTYPE html>
<html>

<head>
    <title>Payment Commission Calculator</title>
    <meta name="author" content="">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>

<body>
    <div class="container mt-4">
        <div class="card">
            <div class="card-header text-center font-weight-bold">
            <h1> Commission Calculator</h1>

            </div>
            @if(Session::has('success'))
            <div class="alert alert-success">
                {{ Session::get('success') }}
                @php
                    Session::forget('success');
                @endphp
            </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Whoops!</strong> There were some problems with your input.<br><br>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="card-body">
                <form method="post" action="{{ url('payments') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label for="exampleInputEmail1">Please select your CSV file.</label>
                        <input class="form-control" type="file" id="resume_file" name="file">
                    </div>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </form>
            </div>
            <div class="card-body">
                @if (session('commissions'))
                    <p><b>Results:</b></p>
                    <div class="alert alert-'success'">
                        @foreach (session('commissions') as $commission)
                            <span>{{ $commission }}</span><br>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</body>

</html>
