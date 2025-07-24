# Chamber Boss

This repository contains both the website for the Chamber Boss WordPress plugin and the plugin itself. The website is a static site hosted on Netlify that showcases the plugin features, provides documentation, and offers download links.

## Project Structure

```
.
├── site/                           # Static website files
│   ├── index.html                  # Main HTML file
│   ├── documentation.html          # Plugin documentation
│   ├── css/                        # Stylesheets
│   │   └── styles.css              # Main stylesheet
│   ├── js/                         # JavaScript files
│   │   └── main.js                 # Main JavaScript file
│   └── assets/                     # Images and other assets
│       ├── plugin-preview.svg      # Plugin preview image
│       ├── demo-screenshot.svg     # Demo screenshot
│       └── chamber-membership-manager.zip  # Plugin download
├── plugin/                         # WordPress plugin files
│   └── chamber-membership-manager/ # Plugin directory
│       ├── chamber-membership-manager.php  # Main plugin file
│       ├── readme.txt              # Plugin documentation
│       ├── includes/               # Plugin includes
│       │   ├── stripe-webhook-handler.php  # Stripe webhook handler
│       │   ├── membership-renewal-notifications.php  # Renewal notifications
│       │   └── security.php        # Security utilities
│       ├── templates/              # Plugin templates
│       ├── languages/              # Translation files
│       │   └── chamber-boss.pot    # POT file for translations
│       └── assets/                 # Plugin assets
│           ├── css/                # Plugin styles
│           ├── js/                 # Plugin scripts
│           ├── banner-1200x600.svg # Plugin banner
│           └── icon.svg            # Plugin icon
├── prd.md                          # Product Requirements Document
├── progress.md                     # Project progress report
├── SUMMARY.md                      # Project summary
├── netlify.toml                    # Netlify configuration
└── README.md                       # This file
```

## Development

To run the site locally, you can use any static file server. For example:

```bash
# Using Python 3
cd site && python3 -m http.server 8000

# Using Node.js (if you have http-server installed)
npx http-server site
```

Then open http://localhost:8000 in your browser.

## Deployment

This site is configured for automatic deployment on Netlify. Simply push to the repository and Netlify will build and deploy the site automatically.

## Plugin Installation

1. Download the plugin ZIP file from the website or from `site/assets/chamber-membership-manager.zip`
2. Go to Plugins > Add New in your WordPress admin
3. Click "Upload Plugin" and select the ZIP file
4. Click "Install Now" and then "Activate Plugin"
5. Go to Chamber Members > Settings to configure your Stripe API keys and MailPoet lists

## Plugin Features

The Chamber Boss plugin includes:

- Membership management system with custom user roles
- Business listing creation and management via custom post types
- Public business directory with search functionality using a shortcode
- Stripe payment processing integration with webhook handling
- MailPoet newsletter integration with automatic list management
- Membership renewal notifications
- Encrypted storage for sensitive API keys
- Multilingual support with .pot file for translations

## Customization

To customize the site:

1. Update the content in `site/index.html` and `site/documentation.html`
2. Modify styles in `site/css/styles.css`
3. Add or replace images in `site/assets/`
4. Update the Netlify configuration in `netlify.toml` if needed

To customize the plugin:

1. Update the code in `plugin/chamber-membership-manager/`
2. Repackage the plugin by running:
   ```bash
   cd plugin && zip -r chamber-membership-manager.zip chamber-membership-manager
   ```
3. Move the new ZIP file to `site/assets/` to update the download