<!DOCTYPE html>
<html>
<head>
	<title></title>
	<style type="text/css">
		body{
			font-family: 'verdana';
		}
		h1,h2,h4,p{
			text-align: center;
		}
		p{
			font-size: 12px;
		}
		.tech-spec{
			font-weight: normal;
		}
		table.specs{
			width: 70%;
			margin-left: 100px;
			font-size: 12px;
		}
		td.tech-spec-title{
			text-align: left;
		}
		td.tech-spec-details{
			text-align: right;
		} 
		.page-break{
			page-break-after: always;
		}
	</style>
</head>
<body>
	@foreach($products as $product)
		<img src="{{asset('images/warranty/header-image.png')}}">
		<h1>WARRANTY CERTIFICATE</h1>
		<h2>{{$product['product']->collection_code}} {{$product['product']->colour_code}}</h2>
		<h4>{{$product['duration']}} LIMITED WARRANTY</h4>
		<p>{{$product['term']}}</p>
		<p>{{$product['description']}}</p>
		<h2 class="tech-spec">TECHNICAL SPECIFICATION</h2>
		<table class="specs">
			@foreach($product['techspecs'] as $techspec)
				<tr>
					<td class="tech-spec-title">{{$techspec->title}} : </td>
					<td class="tech-spec-details">{{$techspec->value}}</td>
				</tr>
			@endforeach
		</table>
		<br>
		<p style="text-align:left">Authorized Date : {{$product['authorizedDate']}}</p>
		<p style="text-align:left">Reference Number : {{$product['invoiceNumber']}}</p>
		<p style="text-align:left">Dealer : {{$product['dealerName']}}</p>
		@if (!$loop->last)
			<div class="page-break"></div>
		@endif
		<br>
		
	@endforeach	
</body>
</html>