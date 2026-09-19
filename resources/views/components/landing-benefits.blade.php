<section id="why" class="sp-wrap sp-section sp-why">
<div class="sp-section-heading"><h2>Why Shop with SHOPPICK?</h2></div>
<div class="sp-benefits">@foreach([
 ['cart','Easy Shopping','Browse products without complicated steps.'],
 ['grid','Many Choices','Find products from different approved shops.'],
 ['orders','Clear Order Updates','Check the progress of your orders.'],
 ['delivery','SHOPPICK Delivery','Your order moves through our delivery process.']
] as [$icon,$title,$description])<article><span class="sp-benefit-icon"><x-landing-icon :name="$icon"/></span><h3>{{ $title }}</h3><p>{{ $description }}</p></article>@endforeach</div></section>
