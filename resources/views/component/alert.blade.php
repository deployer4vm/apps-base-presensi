@if(Session::has('alert') || $errors->any())
<?php 
$alert = Session::get('alert');
Session::forget('alert');
if(!$alert)return false;
Session::save();
$availbleAlert = ['success','info','warning','danger'];
$alertType = in_array($alert['type'],$availbleAlert)?$alert['type']:'info';
?>
<div class="alert alert-dark-{{ $alertType }} alert-dismissible fade show my-4">
	<button type="button" class="close" data-dismiss="alert">×</button>
	{!! $alert['message'] !!}
	@if($alert['errors'] && !isset($alert['errors'][0]))
	<ul>
	@foreach ($alert['errors'] as $key => $error)
	@foreach ($error as $errorMessage)
		<li>{{ $errorMessage }}</li>
	@endforeach
	@endforeach
	</ul>
	@endif
</div>
@endif