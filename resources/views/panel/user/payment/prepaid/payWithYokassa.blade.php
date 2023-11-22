@extends('panel.layout.app')
@section('title', __('Token Packs'))

@section('content')
<!-- Page header -->
<div class="page-header">
    <div class="container-xl">
        <div class="row g-2 items-center">
            <div class="col">
				<a href="{{ LaravelLocalization::localizeUrl(route('dashboard.index')) }}" class="page-pretitle flex items-center">
					<svg class="!me-2 rtl:-scale-x-100" width="8" height="10" viewBox="0 0 6 10" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
						<path d="M4.45536 9.45539C4.52679 9.45539 4.60714 9.41968 4.66071 9.36611L5.10714 8.91968C5.16071 8.86611 5.19643 8.78575 5.19643 8.71432C5.19643 8.64289 5.16071 8.56254 5.10714 8.50896L1.59821 5.00004L5.10714 1.49111C5.16071 1.43753 5.19643 1.35718 5.19643 1.28575C5.19643 1.20539 5.16071 1.13396 5.10714 1.08039L4.66071 0.633963C4.60714 0.580392 4.52679 0.544678 4.45536 0.544678C4.38393 0.544678 4.30357 0.580392 4.25 0.633963L0.0892856 4.79468C0.0357141 4.84825 0 4.92861 0 5.00004C0 5.07146 0.0357141 5.15182 0.0892856 5.20539L4.25 9.36611C4.30357 9.41968 4.38393 9.45539 4.45536 9.45539Z"/>
					</svg>
					{{__('Back to dashboard')}}
				</a>
                <h2 class="page-title mb-2">
                    {{__('Token Packs')}}
                </h2>
            </div>
        </div>
    </div>
</div>


<!-- Page body -->
<div class="page-body pt-6">
    <div class="container-xl">
        <div class="row row-cards">
            <div class="col-sm-8 col-lg-8">
                <div id="payment-form"></div>
				<p>{{__('By purchase you confirm our')}} <a href="{{ url('/').'/terms' }}">{{__('Terms and Conditions')}}</a> </p>
            </div>
            <div class="col-sm-4 col-lg-4">
                <div class="card card-md w-full bg-[#f3f5f8] text-center border-0 text-heading group-[.theme-dark]/body:!bg-[rgba(255,255,255,0.02)]">
                    @if($plan->is_featured == 1)
                    <div class="ribbon ribbon-top ribbon-bookmark bg-green">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-filled" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873z" /></svg>
                    </div>
                    @endif
                    <div class="card-body flex flex-col !p-[45px_50px_50px] text-center">
                        <div class="text-heading flex items-end justify-center mt-0 mb-[15px] w-full text-[60px] leading-none">
							@if(currencyShouldDisplayOnRight(currency()->symbol))
                                {{$plan->price}}
                                <small class="inline-flex mb-[0.3em] font-normal text-[0.35em]">{{currency()->symbol}}</small>
                            @else
                                <small class="inline-flex mb-[0.3em] font-normal text-[0.35em]">{{currency()->symbol}}</small>
                                {{$plan->price}}
                            @endif
                            
							<small class="inline-flex mb-[0.3em] font-normal text-[0.35em]">/ {{__('One time')}}</small>
						</div>
						<div class="inline-flex mx-auto p-[0.85em_1.2em] bg-white rounded-full font-medium text-[15px] leading-none text-[#2D3136]">{{ __($plan->name) }}</div>

                        <ul class="list-unstyled mt-[35px] text-[15px] mb-[25px]">
                            <li class="mb-[0.625em]">
								<span class="inline-flex items-center justify-center w-[19px] h-[19px] mr-1 bg-[rgba(28,166,133,0.15)] text-green-500 rounded-xl align-middle">
									<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
								</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1 text-success" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
                                {{__('Access')}} <strong>{{__($plan->plan_type)}}</strong> {{__('Templates')}}
                            </li>
                            @foreach(explode(',', $plan->features) as $item)
                            <li class="mb-[0.625em]">
								<span class="inline-flex items-center justify-center w-[19px] h-[19px] mr-1 bg-[rgba(28,166,133,0.15)] text-green-500 rounded-xl align-middle">
									<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
								</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1 text-success" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
                                {{$item}}
                            </li>
                            @endforeach

                            <li class="mb-[0.625em]">
								<span class="inline-flex items-center justify-center w-[19px] h-[19px] mr-1 bg-[rgba(28,166,133,0.15)] text-green-500 rounded-xl align-middle">
									<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
								</span>
                                @if((int)$plan->total_words >= 0)
                                <strong>{{number_format($plan->total_words)}}</strong> {{__('Word Tokens')}}
                            @else
                                <strong>{{__('Unlimited')}}</strong> {{__('Word Tokens')}}
                            @endif
                            </li>
                            <li class="mb-[0.625em]">
								<span class="inline-flex items-center justify-center w-[19px] h-[19px] mr-1 bg-[rgba(28,166,133,0.15)] text-green-500 rounded-xl align-middle">
									<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
								</span>
                                @if((int)$plan->total_images >= 0)
                                    <strong>{{number_format($plan->total_images)}}</strong> {{__('Image Tokens')}}
                                @else
                                    <strong>{{__('Unlimited')}}</strong> {{__('Image Tokens')}}
                                @endif
                            </li>

                        </ul>
                        <div class="text-center mt-auto">
                            <a class="btn rounded-md p-[1.15em_2.1em] w-full text-[15px] group-[.theme-dark]/body:!bg-[rgba(255,255,255,1)] group-[.theme-dark]/body:!text-[rgba(0,0,0,0.9)]" href="{{ LaravelLocalization::localizeUrl( route('dashboard.user.payment.subscription') ) }}">{{__('Change Plan')}}</a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
@section('script')
	<script src="https://yookassa.ru/checkout-widget/v1/checkout-widget.js"></script>
    <script>
        const checkout = new window.YooMoneyCheckoutWidget({
            confirmation_token: '{{$confirmation_token}}', //Token that must be obtained from YooMoney before the payment process
            //return_url: 'https://stagingmagicai.liquid-themes.com/dashboard/user/', //Link to the payment completion page, it could be any page on your website
            error_callback: function(error) {
                console.log(error)
            }
        });

        checkout.on('success', () => {
            var formData = new FormData();
			
            formData.append( 'payment_id', '{{$payment_id}}');
            formData.append('plan', '{{$plan->id}}')
			$.ajax( {
				type: "post",
                headers: {
                    'X-CSRF-TOKEN': "{{ csrf_token() }}",
                },
				url: "/dashboard/user/payment/yokassa/prepaidPay",
				data: formData,
				contentType: false,
				processData: false,
				success: function ( data ) {
					toastr.success( 'Thanks for your purchase...' );
					setTimeout( function () {
						location.href = '/dashboard/user';
					}, 1000 );
				},
				error: function ( data ) {
					var err = data.responseJSON.errors;
					toastr.error( err );
				}
			} );

            checkout.destory();
        })

        checkout.on('fail', () => {
            toastr.error('There is a mistake for you purchaes. Please try again.' );
            checkout.destroy();
        });
        //Display of payment form in the container
        checkout.render('payment-form');
    </script>
@endsection
