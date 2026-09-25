<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $product->name }}</title>
    
    <!-- Open Graph Meta Tags for Sharing -->
    <meta property="og:title" content="{{ $product->name }}">
    <meta property="og:description" content="{{ Str::limit($product->description, 150) }}">
    @if($product->image_url)
        <meta property="og:image" content="{{ $product->image_url }}">
    @endif
    <meta property="og:url" content="{{ $targetUrl }}">
    <meta property="og:type" content="product">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $product->name }}">
    <meta name="twitter:description" content="{{ Str::limit($product->description, 150) }}">
    @if($product->image_url)
        <meta name="twitter:image" content="{{ $product->image_url }}">
    @endif

    <meta http-equiv="refresh" content="0;url={{ $targetUrl }}">
    
    <script>
        window.location.replace("{{ $targetUrl }}");
    </script>
</head>
<body>
    <p>Redirecting to <a href="{{ $targetUrl }}">{{ $product->name }}</a>...</p>
</body>
</html>
