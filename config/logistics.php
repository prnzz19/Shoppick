<?php
return ['live_tracking_enabled'=>env('LOGISTICS_LIVE_TRACKING_ENABLED',true),'tracking_interval_seconds'=>(int)env('LOGISTICS_TRACKING_INTERVAL_SECONDS',20),'map_provider'=>env('LOGISTICS_MAP_PROVIDER','leaflet_osm'),'routing_provider'=>env('LOGISTICS_ROUTING_PROVIDER'),'routing_api_key'=>env('LOGISTICS_ROUTING_API_KEY'),'buyer_live_tracking'=>env('LOGISTICS_BUYER_LIVE_TRACKING',true)];
