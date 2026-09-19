<?php
return ['pickup_wait_hours'=>(int)env('LOGISTICS_PICKUP_WAIT_HOURS',4),'hub_dwell_hours'=>(int)env('LOGISTICS_HUB_DWELL_HOURS',8),'delivery_delay_hours'=>(int)env('LOGISTICS_DELIVERY_DELAY_HOURS',24),'buyer_tracking_visibility'=>env('LOGISTICS_BUYER_TRACKING_VISIBILITY',true)];
