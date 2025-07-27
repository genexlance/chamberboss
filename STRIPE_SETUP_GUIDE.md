# 🚀 ChamberBoss Stripe Setup Guide

## Quick Fix for Registration Form Payment Fields

### Problem
The member registration form shows "Payment processing not configured" instead of payment fields.

### Solution
1. Install Stripe PHP SDK dependencies (Step 0 below)
2. Add Stripe test API keys to enable payment fields (Steps 1-2 below)

## Step 0: Install Dependencies ⚠️ REQUIRED

**Before configuring Stripe keys, you MUST install the Stripe PHP SDK:**

```bash
cd /path/to/wp-content/plugins/chamberboss
composer install --no-dev
```

**Alternative:** If Composer not available, see [README.md](plugin/chamberboss/README.md) for manual installation instructions.

**⚠️ Without this step, payment fields will not work even with valid API keys!**

## Step 1: Get Stripe Test Keys

### Option A: Create Free Stripe Account (Recommended)
1. **Sign up**: https://dashboard.stripe.com/register
2. **Navigate to**: Dashboard → Developers → API Keys
3. **Copy your test keys**:
   - Test Publishable Key: `pk_test_...`
   - Test Secret Key: `sk_test_...`

### Option B: Use Example Keys (Limited Testing)
For quick testing, you can find official Stripe test keys in their documentation:
- Visit: https://docs.stripe.com/keys#test-and-live-keys
- Look for the table with "randomly generated examples"
- Copy the test keys (pk_test_... and sk_test_...)

*Note: Example keys work for basic testing but won't process actual payments*

## Step 2: Configure ChamberBoss

1. **Open WordPress Admin** → **ChamberBoss** → **Settings** → **Stripe**
2. **Set Mode**: Select "Test Mode"
3. **Add Keys** (use your actual keys or the example keys above):
   - Test Publishable Key: `pk_test_...`
   - Test Secret Key: `sk_test_...`
4. **Save Settings**

## Step 3: Test Registration

1. **Visit**: `http://localhost:10005/test-shortcode.php`
2. **Verify**: Debug box should show "Stripe Configured: YES"
3. **Confirm**: Payment section appears with Stripe Elements
4. **Test**: Use test card `4242 4242 4242 4242` (any future date, any CVC)

## Expected Results

### ✅ With Stripe Configured:
- Payment fields appear in registration form
- Can process test payments
- Creates WordPress user account after payment
- Sends welcome email with login credentials

### ✅ Without Stripe Configured:
- Shows "Registration is currently free"
- Creates user account immediately
- Still sends welcome email

## Test Cards

| Card Number | Description |
|-------------|-------------|
| `4242 4242 4242 4242` | Visa - Success |
| `4000 0000 0000 0002` | Visa - Declined |
| `4000 0000 0000 9995` | Visa - Insufficient funds |

Use any future expiry date and any 3-digit CVC.

## Troubleshooting

If payment fields still don't appear:
1. Clear any caching plugins
2. Check browser console for JavaScript errors
3. Verify debug box shows "Stripe Configured: YES"
4. Ensure you're logged out when testing registration

## Real Stripe Account Setup

For production use, create a real Stripe account:

1. **Sign up**: https://dashboard.stripe.com/register
2. **Get your keys**: Dashboard → Developers → API Keys
3. **Test mode keys**: Can reveal unlimited times
4. **Live mode keys**: Only shown once (store safely)

## Need Help?

- Check debug logs: `/wp-content/debug.log`
- Test page: `http://localhost:10005/test-shortcode.php`
- Settings: `http://localhost:10005/wp-admin/admin.php?page=chamberboss-settings&tab=stripe` 