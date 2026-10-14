@php
    $logisticsCta = route('login');
    if (auth()->check() && auth()->user()->hasRole('logistics')) {
        $logisticsCta = route('logistics.dashboard');
    }
@endphp

<section class="sp-logistics" aria-labelledby="logistics-title">
    <div class="sp-logistics-shell">
        <header class="sp-logistics-hero">
            <div class="sp-logistics-wordmark">
                <x-shoppick.logo class="sp-logistics-logo"/>
                <span>SHOPPICK <small>LOGISTICS</small></span>
            </div>
            <div class="sp-logistics-hero-copy">
                <p class="sp-logistics-eyebrow">Connected marketplace operations</p>
                <h2 id="logistics-title"><span>LOGISTICS</span><strong>MADE EASY</strong></h2>
                <p class="sp-logistics-ghost" aria-hidden="true">DISCOVER HOW SHOPPICK LOGISTICS<br>CONNECTS ORDERS, SHIPMENTS,<br>RIDERS, HUBS, AND DELIVERY<br>OPERATIONS MORE EFFICIENTLY.</p>
            </div>
            <div class="sp-logistics-hero-image">
                <img src="{{ asset('images/logistics/logistics-fulfillment.png') }}" alt="SHOPPICK Logistics fulfillment system with branded shipping packages" width="1448" height="1086" loading="lazy" decoding="async">
            </div>
        </header>

        <section class="sp-logistics-split sp-logistics-challenges" aria-labelledby="logistics-challenges-title">
            <div class="sp-logistics-copy">
                <p class="sp-logistics-eyebrow">Marketplace delivery support</p>
                <h3 id="logistics-challenges-title">TIRED OF FREQUENT<br>SHIPPING CHALLENGES?</h3>
                <p>Managing deliveries across sellers, customers, riders, hubs, and different locations can become difficult as marketplace activity grows. SHOPPICK Logistics helps keep shipment operations connected and organized.</p>
            </div>
            <div class="sp-logistics-feature-list">
                <article class="sp-logistics-feature-card">
                    <span class="sp-logistics-feature-icon sp-logistics-icon-teal" aria-hidden="true">
                        <svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="17" stroke="currentColor" stroke-width="2.2"/><path d="M7 24h34M24 7c5 5.3 7.5 11 7.5 17S29 35.7 24 41c-5-5.3-7.5-11-7.5-17S19 12.3 24 7Z" stroke="currentColor" stroke-width="2.2"/></svg>
                    </span>
                    <div><h4>SEAMLESS DELIVERY COORDINATION</h4><p>Coordinate shipments across sellers, hubs, riders, and delivery destinations through one connected logistics workflow.</p></div>
                </article>
                <article class="sp-logistics-feature-card">
                    <span class="sp-logistics-feature-icon sp-logistics-icon-orange" aria-hidden="true">
                        <svg viewBox="0 0 48 48" fill="none"><path d="m8 16 16-8 16 8v17L24 41 8 33V16Z" stroke="currentColor" stroke-width="2.2"/><path d="m8 16 16 8 16-8M24 24v17" stroke="currentColor" stroke-width="2.2"/></svg>
                    </span>
                    <div><h4>CONVENIENT DOOR-TO-DOOR DELIVERY</h4><p>Support the delivery journey from seller pickup through transportation and final customer delivery.</p></div>
                </article>
            </div>
        </section>

        <section class="sp-logistics-split sp-logistics-commitment" aria-labelledby="logistics-commitment-title">
            <div class="sp-logistics-copy">
                <p class="sp-logistics-eyebrow">Built around every order</p>
                <h3 id="logistics-commitment-title">OUR COMMITMENT</h3>
                <p>SHOPPICK Logistics supports marketplace fulfillment by connecting shipment management, dispatch, riders, hubs, tracking, and delivery operations through one integrated system.</p>
                <ul class="sp-logistics-values">
                    <li><span>+</span> EFFICIENCY</li>
                    <li><span>+</span> SAFETY</li>
                    <li><span>+</span> RELIABILITY</li>
                    <li><span>+</span> COORDINATION</li>
                </ul>
            </div>
            <div class="sp-logistics-truck">
                <img src="{{ asset('images/logistics/logistics-truck.png') }}" alt="SHOPPICK Logistics delivery truck" width="1448" height="1086" loading="lazy" decoding="async">
            </div>
        </section>

        <section class="sp-logistics-contact" aria-labelledby="logistics-contact-title">
            <div class="sp-logistics-contact-display" aria-hidden="true">
                <span>CONTACT</span>
                <strong>US</strong>
                <i class="sp-logistics-accent sp-logistics-accent-teal"></i>
                <i class="sp-logistics-accent sp-logistics-accent-orange"></i>
            </div>
            <div class="sp-logistics-contact-copy">
                <p class="sp-logistics-eyebrow">A connected next step</p>
                <h3 id="logistics-contact-title">READY TO SIMPLIFY LOGISTICS?</h3>
                <p>SHOPPICK connects marketplace orders, shipments, riders, hubs, and delivery operations in one integrated logistics workflow.</p>
                <a class="sp-button sp-button-light" href="{{ $logisticsCta }}">EXPLORE LOGISTICS <span aria-hidden="true">&rarr;</span></a>
            </div>
        </section>
    </div>
</section>