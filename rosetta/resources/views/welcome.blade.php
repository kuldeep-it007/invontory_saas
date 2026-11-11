<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>{{config('brand.brand')}}</title>
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
  <link rel="stylesheet" href="{{ asset('vendors/bootstrap/css/bootstrap.min.css')}}">
  <link rel="stylesheet" href="{{ asset('vendors/font-awesome/css/fontawesome-all.min.css')}}">
  <link rel="stylesheet" href="{{ asset('vendors/AdminLTE.min.css')}}">
  <link rel="stylesheet" href="{{ asset('vendors/skins/_all-skins.min.css')}}">
  <link rel="stylesheet" href="{{ asset('vendors/rosetta/custom.css')}}">

  <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
  <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
  <!--[if lt IE 9]>
  <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
  <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
  <![endif]-->

  <!-- Google Font -->
  <link rel="stylesheet"
        href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">
  <script type="text/javascript">
    var config = {
      brand : '{{config('brand.brand')}}',
      brand_short : '{{config('brand.brand_short')}}',
      api_url : '{{config('app.url').'api/'}}',
      prefix: '{{config('brand.queue_prefix')}}',
      theme:'{{config('brand.theme')}}',
      tnc: '{{config('brand.tnc')}}',
      enable_token:  '{{config('rosetta.enable_token')}}'  == 1 ? true: false,
      mobile_app_url:  '{{config('rosetta.mobile_app_url')}}',
      pusher: {
        key: '{{config('broadcasting.connections.pusher.key')}}',
        cluster:'{{config('broadcasting.connections.pusher.options.cluster')}}'
      },
      new_approach: {{config('brand.new_approach')}},
    }
  </script>
</head>
<body class="hold-transition {{config('brand.theme')}} sidebar-mini">
    <div id="app"></div>
    <script src="{{ mix('/js/manifest.js')}}"></script>
    <script src="{{ mix('/js/vendor.js')}}"></script>
    <script src="{{ mix('/js/app.js')}}"></script>
    <script src="{{ asset('vendors/jquery/dist/jquery.min.js')}}"></script>
    <script src="{{ asset('vendors/bootstrap/js/bootstrap.min.js')}}"></script>
    <script src="{{ asset('vendors/fastclick/lib/fastclick.js')}}"></script>
    <script src="{{ asset('vendors/adminlte.min.js')}}"></script>
    <script src="{{ asset('vendors/PushMenu.js')}}"></script>
    <script src="{{ asset('vendors/jquery-slimscroll/jquery.slimscroll.min.js')}}"></script>
</body>
</html>
