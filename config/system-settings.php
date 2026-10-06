<?php

return [
'groups' => [
'general' => [
'label' => 'General',
'description' => 'Save business preferences. These values do not yet change public contact details, payment currency, or timestamp handling.',
'fields' => [
'marketplace_name' => [
'label' => 'Marketplace Name',
'type' => 'text',
'default' => config('app.name') === 'Laravel' ? 'SHOPPICK' : config('app.name', 'SHOPPICK'),
'rules' => ['required', 'string', 'max:100'],
'note' => ''
],
'support_email' => [
'label' => 'Support Email',
'type' => 'email',
'default' => config('shop.support_email', ''),
'rules' => ['nullable', 'email:rfc', 'max:255'],
'note' => ''
],
'support_phone' => [
'label' => 'Support Phone',
'type' => 'text',
'default' => '',
'rules' => ['nullable', 'string', 'max:40'],
'note' => ''
],
'address' => [
'label' => 'Business Address',
'type' => 'textarea',
'default' => '',
'rules' => ['nullable', 'string', 'max:1000'],
'note' => ''
],
'timezone' => [
'label' => 'Timezone',
'type' => 'select',
'default' => config('app.timezone', 'UTC'),
'rules' => ['required', 'timezone'],
'note' => 'Saved preference; existing order timestamps are unchanged.',
'options' => array_combine(timezone_identifiers_list(), timezone_identifiers_list())
],
'currency' => [
'label' => 'Currency',
'type' => 'select',
'default' => 'PHP',
'rules' => ['required', 'in:PHP,USD,EUR'],
'note' => 'Saved preference only. Payments continue using the existing currency; no conversion is performed.',
'options' => [
'PHP' => 'PHP (₱)',
'USD' => 'USD ($)',
'EUR' => 'EUR (€)'
]
]
]
],
'branding' => [
'label' => 'Branding',
'description' => 'Save and preview branding here. Public pages retain their current SHOPPICK branding until a separate rollout.',
'fields' => [
'logo' => [
'label' => 'Marketplace Logo',
'type' => 'file',
'default' => null,
'rules' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
'note' => 'PNG, JPEG or WEBP. Maximum 2 MB. The original mascot remains the fallback.'
],
'favicon' => [
'label' => 'Favicon',
'type' => 'file',
'default' => null,
'rules' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:512', 'dimensions:max_width=512,max_height=512'],
'note' => 'PNG, JPEG or WEBP. Maximum 512 KB and 512 × 512 pixels.'
],
'primary_color' => [
'label' => 'Primary Color',
'type' => 'color',
'default' => '#14b8a6',
'rules' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
'note' => ''
],
'accent_color' => [
'label' => 'Accent Color',
'type' => 'color',
'default' => '#f97316',
'rules' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
'note' => ''
]
]
],
'marketplace' => [
'label' => 'Marketplace',
'description' => 'Controls the web marketplace. Maintenance preserves Admin, Seller, Logistics, Rider and sign-in access.',
'fields' => [
'status' => [
'label' => 'Marketplace Status',
'type' => 'select',
'default' => 'active',
'rules' => ['required', 'in:active,maintenance'],
'note' => 'Maintenance temporarily returns a maintenance page for public shopping and Buyer routes.',
'options' => [
'active' => 'Active',
'maintenance' => 'Maintenance'
]
],
'allow_registration' => [
'label' => 'Allow New Buyer Registration',
'type' => 'boolean',
'default' => true,
'rules' => ['required', 'boolean'],
'note' => 'Applies to email registration and creation of new Google accounts. Existing accounts can still sign in.'
],
'allow_seller_applications' => [
'label' => 'Allow Seller Applications',
'type' => 'boolean',
'default' => true,
'rules' => ['required', 'boolean'],
'note' => 'Blocks new submissions and resubmissions. Existing application status remains available.'
],
'allow_reviews' => [
'label' => 'Allow Product Reviews',
'type' => 'boolean',
'default' => true,
'rules' => ['required', 'boolean'],
'note' => 'Controls new review submissions. Existing reviews remain visible.'
]
]
],
'orders' => [
'label' => 'Orders',
'description' => 'Cancellation settings apply to the existing cancellation service. Automatic completion preferences are saved only; no scheduler is introduced.',
'fields' => [
'allow_cancellation' => [
'label' => 'Order Cancellation Enabled',
'type' => 'boolean',
'default' => true,
'rules' => ['required', 'boolean'],
'note' => ''
],
'cancellation_limit' => [
'label' => 'Cancellation Time Limit (hours)',
'type' => 'number',
'default' => 0,
'rules' => ['required', 'integer', 'min:0', 'max:720'],
'note' => '0 means no additional time limit. Existing order-status restrictions always apply.'
],
'auto_complete' => [
'label' => 'Automatic Order Completion',
'type' => 'boolean',
'default' => false,
'rules' => ['required', 'boolean'],
'note' => 'Saved only. Orders are not automatically completed.'
],
'auto_complete_days' => [
'label' => 'Auto Complete After Delivery (days)',
'type' => 'number',
'default' => 7,
'rules' => ['required', 'integer', 'min:1', 'max:90'],
'note' => 'Saved for future integration only.'
]
]
],
'seller' => [
'label' => 'Seller',
'description' => 'Buyer → Seller application → Admin approval → Buyer + Seller remains unchanged.',
'fields' => [
'applications_enabled' => [
'label' => 'Seller Applications Enabled',
'type' => 'boolean',
'default' => true,
'rules' => ['required', 'boolean'],
'note' => 'Shared with the Marketplace setting.',
'key' => 'marketplace.allow_seller_applications'
],
'require_approval' => [
'label' => 'Require Admin Approval',
'type' => 'boolean',
'default' => true,
'rules' => ['required', 'in:1'],
'note' => 'Required by the current account workflow and cannot be disabled.',
'locked' => true
],
'logo_required' => [
'label' => 'Require Shop Logo',
'type' => 'boolean',
'default' => false,
'rules' => ['required', 'boolean'],
'note' => 'Applies to seller applications. Existing shop avatars remain unchanged.'
],
'logo_max_size' => [
'label' => 'Maximum Shop Logo Size (KB)',
'type' => 'number',
'default' => 2048,
'rules' => ['required', 'integer', 'min:64', 'max:10240'],
'note' => 'Applied to seller application uploads. Existing shop-edit upload limits remain unchanged.'
]
]
],
'logistics' => [
'label' => 'Logistics',
'description' => 'Account application controls preserve existing Logistics and Rider roles and workflows.',
'fields' => [
'rider_applications' => [
'label' => 'Rider Applications Enabled',
'type' => 'boolean',
'default' => true,
'rules' => ['required', 'boolean'],
'note' => 'Controls the public application form and resubmissions.'
],
'require_rider_approval' => [
'label' => 'Require Rider Approval',
'type' => 'boolean',
'default' => true,
'rules' => ['required', 'in:1'],
'note' => 'Required by the current workflow and cannot be disabled.',
'locked' => true
],
'manual_assignment' => [
'label' => 'Allow Manual Rider Assignment',
'type' => 'boolean',
'default' => true,
'rules' => ['required', 'boolean'],
'note' => 'Saved preference only. Current assignment operations remain available.'
],
'tracking_enabled' => [
'label' => 'Delivery Tracking Enabled',
'type' => 'boolean',
'default' => true,
'rules' => ['required', 'boolean'],
'note' => 'Shares the existing Logistics buyer-tracking preference. Saved only; current tracking visibility is unchanged.'
]
]
],
'notifications' => [
'label' => 'Notifications',
'description' => 'Preferences below control matching existing in-app notifications only. Email decisions, assignment alerts, and other notifications remain unchanged.',
'fields' => [
'seller_application' => [
'label' => 'New Seller Application',
'type' => 'boolean',
'default' => true,
'rules' => ['required', 'boolean'],
'note' => ''
],
'rider_application' => [
'label' => 'New Rider Application',
'type' => 'boolean',
'default' => true,
'rules' => ['required', 'boolean'],
'note' => ''
],
'new_order' => [
'label' => 'New Order',
'type' => 'boolean',
'default' => true,
'rules' => ['required', 'boolean'],
'note' => ''
],
'order_status' => [
'label' => 'Order Status Changes',
'type' => 'boolean',
'default' => true,
'rules' => ['required', 'boolean'],
'note' => ''
],
'reports' => [
'label' => 'Reports / Moderation',
'type' => 'boolean',
'default' => true,
'rules' => ['required', 'boolean'],
'note' => 'Applies to existing report and moderation notifications. Does not disable report submission or review.'
],
'low_stock' => [
'label' => 'Low Stock Alerts',
'type' => 'boolean',
'default' => true,
'rules' => ['required', 'boolean'],
'note' => 'Saved preference only; no existing low-stock notification event was found.'
]
]
],
'maintenance' => [
'label' => 'Maintenance',
'description' => 'System information and safe application cache maintenance.',
'fields' => [

]
]
]
];
