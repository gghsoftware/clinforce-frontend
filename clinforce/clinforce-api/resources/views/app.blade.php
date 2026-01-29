<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  @vite('resources/js/app.js')
</head>
<body>
  
  <div id="app"></div>
</body>

<script src="https://js.stripe.com/v3/"></script>
<script>
  window.STRIPE_PUBLISHABLE_KEY = "pk_test_REPLACE_WITH_YOUR_PUBLISHABLE_KEY";
</script>

</html>