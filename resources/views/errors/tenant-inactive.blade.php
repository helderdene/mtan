<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Inactive</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f7fafc;
            color: #2d3748;
        }
        .container {
            text-align: center;
            max-width: 500px;
            padding: 2rem;
        }
        h1 {
            font-size: 3rem;
            margin: 0;
            color: #ed8936;
        }
        p {
            font-size: 1.125rem;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>403</h1>
        <p>Account Inactive</p>
        <p style="font-size: 0.875rem; color: #718096;">
            @if(isset($company_name))
                The account for {{ $company_name }} is currently inactive.
            @else
                This account is currently inactive.
            @endif
        </p>
        <p style="font-size: 0.875rem; color: #718096;">Please contact support for assistance.</p>
    </div>
</body>
</html>
