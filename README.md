# 📘 Atma Facebook Sync for Magento 2

[![Magento](https://img.shields.io/badge/Magento-2.4.x-orange.svg)](https://magento.com/)
[![PHP](https://img.shields.io/badge/PHP-8.1+-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-Proprietary-red.svg)]()

**Automatically post your new Magento products to Facebook with beautiful images and descriptions!**

This Magento 2 module automatically synchronizes your new products to your Facebook Page, posting them with product images, descriptions, prices, and direct links to your store.

---

## ✨ Features

- 🔄 **Automatic Daily Sync** - Posts all new products created each day at 17:00
- 📸 **Image Support** - Automatically posts product images to Facebook
- 🎯 **Smart Filtering** - Only posts enabled, visible products
- 🚫 **No Duplicates** - Tracks posted products to avoid duplicate posts
- 📊 **Status Tracking** - Monitors success/failure of each post with detailed logging
- 🔐 **Secure Configuration** - Encrypted storage of Facebook access tokens
- 📝 **Detailed Logging** - Complete audit trail in Magento system logs

---

## 📋 Requirements

- **Magento**: 2.4.x
- **PHP**: 8.1 or higher
- **Facebook**: Business Page with admin access
- **Facebook App**: With `pages_manage_posts` and `pages_read_engagement` permissions

---

## 🚀 Installation

### Method 1: Manual Installation

1. **Upload the module:**
   ```bash
   mkdir -p app/code/Atma/FacebookSync
   cp -r /path/to/module/* app/code/Atma/FacebookSync/
   ```

2. **Enable the module:**
   ```bash
   php bin/magento module:enable Atma_FacebookSync
   php bin/magento setup:upgrade
   php bin/magento setup:di:compile
   php bin/magento cache:flush
   ```

### Method 2: Composer Installation

```bash
composer require atma/module-facebook-sync
php bin/magento module:enable Atma_FacebookSync
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:flush
```

---

## ⚙️ Configuration

### Step 1: Get Facebook Credentials

#### 1.1 Get Your Facebook Page ID

1. Go to your Facebook Page
2. Click **About** in the left menu
3. Scroll down to find your **Page ID** (numeric value)
4. Copy this ID - you'll need it for configuration

#### 1.2 Get Your Access Token

**Option A: Quick Test Token (1-2 hours validity)**

1. Go to [Facebook Graph API Explorer](https://developers.facebook.com/tools/explorer)
2. Click **"Get Token"** → **"Get User Access Token"**
3. Select permissions: `pages_manage_posts`, `pages_read_engagement`
4. Click **"Generate Access Token"**
5. ⚠️ This token expires in 1-2 hours - use for testing only!

**Option B: Long-Lived Page Token (Recommended for Production)**

1. In Graph API Explorer, get a User Access Token (as above)
2. Click the **"ℹ️"** icon next to the token
3. Click **"Extend Access Token"** to get a 60-day token
4. From the page dropdown, select your Facebook Page
5. Click **"Get Token"** → **"Get Page Access Token"**
6. This Page token **never expires** (unless revoked)
7. Copy this token for configuration

### Step 2: Configure in Magento Admin

1. **Login to Magento Admin**
2. Navigate to **Stores** → **Configuration** → **ATMA** → **Facebook Sync**
3. Configure the following settings:

   | Setting | Description | Example |
   |---------|-------------|---------|
   | **Enable FB Sync** | Enable/disable automatic posting | `Yes` |
   | **Facebook Page ID** | Your Facebook Page's numeric ID | `123456789012345` |
   | **Facebook Access Token** | Long-lived Page Access Token | `EAABsbCS1iHgBO...` |

4. Click **Save Config**
5. Flush cache: `php bin/magento cache:flush`

### Step 3: Verify Cron Configuration

The module uses Magento's cron system to run daily at **17:00**.

```bash
# Verify cron is configured
php bin/magento cron:install

# Check cron status
php bin/magento cron:status
```

---

## 🔄 How It Works

### Automatic Daily Sync Process

Every day at **17:00**, the module automatically:

1. ✅ **Checks if sync is enabled** in configuration
2. ✅ **Retrieves Facebook credentials** (Page ID and Access Token)
3. ✅ **Finds all products created today** (from 00:00:00 to 23:59:59)
4. ✅ **Filters products** to only include:
   - Status: Enabled
   - Visibility: Not "Not Visible Individually"
   - Not previously posted to Facebook
5. ✅ **Posts each product** to Facebook with:
   - 📸 Product image
   - 📝 Product name and description
   - 💶 Product price in EUR
   - 🔗 Direct link to product page
6. ✅ **Tracks the status** of each post (Published/Failed)
7. ✅ **Logs all activity** to Magento system logs

### Database Tracking

The module maintains a `fb_products` table with the following information:

| Column | Description |
|--------|-------------|
| `sku` | Product SKU |
| `product_name` | Product name |
| `product_type` | Product type (simple, configurable, etc.) |
| `image_url` | URL of the posted image |
| `post_id` | Facebook post ID (after successful posting) |
| `status` | Status: pending, published, or failed |
| `message` | The message posted to Facebook |
| `error_message` | Error details if posting failed |
| `scheduled_at` | When the post was scheduled |
| `published_at` | When the post was published to Facebook |

### Facebook Post Format

Each product is posted with the following format:

```
🆕 [Product Name] 🏠

[Product Description]

💶 Preț: [Price] EUR
🔗 Vezi detalii: [Product URL]
```

---

## 🔧 Troubleshooting

### Products Not Posting

**Issue**: No products are being posted to Facebook

**Solutions**:
1. ✅ Check if module is enabled in configuration
2. ✅ Verify Facebook Page ID and Access Token are correct
3. ✅ Ensure products were created today
4. ✅ Check if products are enabled and visible
5. ✅ Review logs: `var/log/system.log`
6. ✅ Verify cron is running: `php bin/magento cron:status`

### Access Token Expired

**Issue**: "Error posting to Facebook: Invalid OAuth access token"

**Solution**:
1. Get a new long-lived Page Access Token (see Configuration → Step 1.2)
2. Update the token in Magento Admin configuration
3. Save and flush cache

### Image Not Posting

**Issue**: Posts appear without images

**Solutions**:
1. ✅ Verify product has a valid image assigned
2. ✅ Check image URL is publicly accessible
3. ✅ Ensure image meets Facebook requirements:
   - Format: JPG, PNG, GIF, BMP
   - Size: At least 200x200 pixels
   - File size: Under 8MB
4. ✅ Check logs for specific image errors

### Permission Errors

**Issue**: "Error posting to Facebook: (#200) Provide valid app ID"

**Solution**:
1. Ensure you're using a **Page Access Token**, not a User Access Token
2. Verify the token has `pages_manage_posts` permission
3. Confirm you have admin/editor role on the Facebook Page

---

## 📊 Admin Features

### View Sync History

Access the sync history in the admin panel:

**Navigation**: *[To be implemented: Admin grid for viewing sync history]*

---

## 📝 Changelog

### Version 1.0.0
- Initial release
- Daily automatic sync at 17:00
- Product image posting
- Status tracking
- Admin configuration
- Logging and error handling

---

## 🤝 Support

For support, please contact:
- **Email**: atma.business2020@gmail.com
- **Website**: https://atma-development.com/

---

## 📄 License

Proprietary - All rights reserved

---

## 👨‍💻 Author

**Atma Development Team**

---

## ⚠️ Important Notes

- Always use a **long-lived Page Access Token** for production
- Monitor the `var/log/system.log` file for sync activity
- The module only posts products created on the current day
- Products are posted only once (duplicates are prevented)
- Ensure your Facebook Page and App remain active
- Review Facebook's API rate limits if posting many products

---

**Made with ❤️ for automated Facebook product posting**
