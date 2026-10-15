<?php

return [
    'reference' => 'KUSUG-SYS-2026-001',
    'client' => 'Kabankalan United Sugarcane Growers and Planters Association',
    'location' => 'Kabankalan City, Negros Occidental, Philippines',
    'overview' => [
        'Weekly planter delivery records and proceeds in one place',
        'Check vouchers calculated with current Quedan and Molasses prices',
        'Loans and cash advances deducted from planter proceeds',
        'Role-based staff access, reports, dashboard analytics, and audit trails',
    ],
    'modules' => [
        [
            'title' => 'Dashboard & Analytics',
            'summary' => 'A high-level view of current operations for administrators and management.',
            'features' => [
                'KPIs for planter deliveries, proceeds, and active loans',
                'Recent activity log and top planter rankings by volume or proceeds',
                'Quedan and Molasses price updates, charts, and trend summaries',
            ],
        ],
        [
            'title' => 'Check / Voucher Printing System',
            'summary' => 'Generates and prints bank-ready planter vouchers after mill deliveries.',
            'features' => [
                'Supports 1,000+ planter records and automatically computes net proceeds',
                'Deducts loans, cash advances, and applicable fees',
                'Custom print size, preview, and batch printing by week or planter',
                'Uses current-week Quedan and Molasses prices',
            ],
        ],
        [
            'title' => 'Quedan and Molasses Registry / Buying',
            'summary' => 'Tracks sugar certificates and molasses buying records.',
            'features' => [
                'Central Quedan certificate and molasses buying registries',
                'Records linked to planter profiles and weekly deliveries',
                'Crop-year and week history with duplicate and error validation',
            ],
        ],
        [
            'title' => 'Weekly Uploads / Updates',
            'summary' => 'Imports weekly deliveries and their financial components.',
            'features' => [
                'Uploads and stores planter delivery summaries',
                'Handles proceeds, trucking allowance, fresh cane incentives, fuel, rentals, underloading, and transloading',
                'Validates data and prevents duplicate uploads',
            ],
        ],
        [
            'title' => 'Crop and Week Management',
            'summary' => 'Organizes transactions by active crop year and delivery week.',
            'features' => [
                'Manages active crop years and week numbers',
                'Closes finalized weeks to prevent unauthorized changes',
                'Shows historical seasons and validates overlapping or incorrect weeks',
            ],
        ],
        [
            'title' => 'Price Management',
            'summary' => 'Maintains the prices used in planter proceeds and vouchers.',
            'features' => [
                'Updates Quedan and Molasses prices',
                'Records date and user for each price change',
            ],
        ],
        [
            'title' => 'Report Generation',
            'summary' => 'Produces operational and financial reports for review and compliance.',
            'features' => [
                'Reports across weekly uploaded data categories',
                'Filters by week, crop year, or planter',
                'Summary and detailed formats for PDF, Excel, and printing',
                'Common prebuilt report templates',
            ],
        ],
        [
            'title' => 'Planter Profile Management',
            'summary' => 'Maintains the association’s official planter registry.',
            'features' => [
                'Registers profiles and manages active or inactive status',
                'Planter IDs for system-wide reference',
                'No fixed limit on planter records',
            ],
        ],
        [
            'title' => 'Loans & Cash Advance Management (Premium module)',
            'summary' => 'Manages planter borrowing through repayment and voucher deduction.',
            'features' => [
                'Records loans and advances with approval and transaction history',
                'Flexible schedules and amortization terms per planter',
                'Deducts from proceeds when a planter harvests, regardless of week',
                'Tracks balances and ledgers in real time',
                'Checks active membership eligibility and handles partial payments, multiple loans, and carry-over balances',
            ],
        ],
        [
            'title' => 'User Management & Access Control',
            'summary' => 'Governs staff accounts and permissions.',
            'features' => [
                'Creates, deactivates, and suspends user accounts',
                'Assigns roles and module-level permissions',
                'Records login and access activity',
            ],
        ],
        [
            'title' => 'Audit Logs',
            'summary' => 'Keeps a trace of user activity and system changes.',
            'features' => [
                'Logs creates, updates, and deletes with user and timestamp',
                'Filters by date, user, module, or action and exports audit trails',
                'Regular users cannot alter or delete log records',
            ],
        ],
    ],
    'hosting' => [
        'Cloud VPS hosting and uptime',
        'SSL certificate and domain name management',
        'Automated daily cloud backups',
        'Billed monthly from deployment while the system remains online',
    ],
    'subscription' => [
        'Bug fixes and minor system updates during Year 1',
        'Remote technical support by message or call',
        'System monitoring and performance checks',
        'Software licensing for the subscription period',
    ],
    'inclusions' => [
        'Design, development, testing, and delivery of all 11 modules',
        'One on-site or remote onboarding and staff training session',
        'Cloud deployment; hosting includes SSL and domain management',
        'Issue resolution during the active Year 1 subscription',
    ],
    'exclusions' => [
        'Local server hardware and on-premises setup',
        'Computers, printers, network equipment, and client internet access',
        'Major new features or modules beyond the agreed scope; quoted separately',
        'Additional on-site visits beyond initial onboarding; may be billed separately',
    ],
    'delivery' => [
        'Estimated delivery: 45–60 business days after downpayment and required client data are received.',
        'KUSUG-PA provides planter data, required fields, and sample documents such as the voucher template within five business days of signing.',
    ],
    'ownership' => [
        'KUSUG-PA exclusively owns data entered and stored in the system.',
        'The developer will not share, sell, or disclose client data to third parties.',
        'A perpetual software use license is granted after full payment of the one-time development fee.',
        'Source code remains the development team’s intellectual property; the software may not be sold, sublicensed, or redistributed by the client.',
    ],
    'continuity' => [
        'Year 2 software support and license renewal is optional and is not automatic; notice is given 30 days before Year 1 ends.',
        'Year 2 renewal is ₱7,000 per month, with monthly or annual prepayment available; hosting continues at ₱500 per month.',
        'If hosting is unpaid, access may be suspended after 14 days’ notice. The proposal specifies data retention for up to 60 days after hosting lapses.',
    ],
    'security' => [
        'Encryption for planter and financial information, HTTPS connections, and passwords that are never stored in plain text',
        'Role-based access, automatic logout after inactivity, and temporary lock after repeated failed logins',
        'Automated daily backups and activity logs for changes and access',
        'Issue notification within 24 hours, investigation and proposed solutions within 48 hours; fixes are covered during an active subscription',
    ],
    'client_responsibilities' => [
        'Use strong passwords and do not share login credentials.',
        'Keep office devices free from malware and maintain reliable internet access.',
    ],
];
