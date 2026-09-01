<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Order DDD API - Swagger UI</title>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@3/swagger-ui.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #fafafa;
        }
        .topbar {
            background-color: #333;
            padding: 10px;
            color: white;
            text-align: center;
        }
        .topbar a {
            color: #fff;
            text-decoration: none;
            margin: 0 15px;
        }
        .topbar a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <h1>Sales Order DDD API</h1>
        <p>Domain-Driven Design with Hexagonal Architecture</p>
        <a href="{{ url('/api/documentation') }}">API Docs</a>
        <a href="{{ url('/docs/api-docs.yaml') }}">OpenAPI YAML</a>
    </div>

    <div id="swagger-ui"></div>

    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@3/swagger-ui-bundle.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@3/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: "{{ $specUrl }}",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout"
            });
            window.ui = ui;
        };
    </script>
</body>
</html>
